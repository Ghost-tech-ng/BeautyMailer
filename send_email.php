<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Set response header to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle OPTIONS request for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize response array
$response = array(
    'success' => false,
    'message' => '',
    'debug' => []
);

try {
    // Check if all required fields are present
    if (empty($_POST['to_email'])) {
        throw new Exception('Recipient email is required');
    }

    // Validate email
    $toEmail = filter_var($_POST['to_email'], FILTER_VALIDATE_EMAIL);
    if (!$toEmail) {
        throw new Exception('Invalid email format: ' . $_POST['to_email']);
    }

    // Get form data with defaults
    $userName = !empty($_POST['user_name']) ? $_POST['user_name'] : 'Valued Customer';
    $subject = !empty($_POST['subject']) ? $_POST['subject'] : 'No Subject';
    $companyName = !empty($_POST['company_name']) ? $_POST['company_name'] : 'BeautyMail';
    $emailContent = !empty($_POST['email_content']) ? $_POST['email_content'] : '';
    $emailType = !empty($_POST['email_type']) ? $_POST['email_type'] : 'executive';
    
    // Email options
    $showAutomatedMessage = isset($_POST['show_automated_message']) && $_POST['show_automated_message'] == '1';
    $includeUnsubscribe = isset($_POST['include_unsubscribe']) && $_POST['include_unsubscribe'] == '1';
    
    // Social media links
    $socialLinks = array(
        'facebook' => !empty($_POST['facebook']) ? $_POST['facebook'] : '',
        'twitter' => !empty($_POST['twitter']) ? $_POST['twitter'] : '',
        'instagram' => !empty($_POST['instagram']) ? $_POST['instagram'] : '',
        'linkedin' => !empty($_POST['linkedin']) ? $_POST['linkedin'] : '',
        'youtube' => !empty($_POST['youtube']) ? $_POST['youtube'] : '',
        'website' => !empty($_POST['website']) ? $_POST['website'] : ''
    );
    
    // SMTP Configuration from form
    $smtpHost = !empty($_POST['smtp_host']) ? $_POST['smtp_host'] : 'smtp.hostinger.com';
    $smtpPort = !empty($_POST['smtp_port']) ? (int)$_POST['smtp_port'] : 587;
    $smtpUsername = !empty($_POST['smtp_username']) ? $_POST['smtp_username'] : '';
    $smtpPassword = !empty($_POST['smtp_password']) ? $_POST['smtp_password'] : '';
    $smtpSecurity = !empty($_POST['smtp_security']) ? $_POST['smtp_security'] : 'tls';

    // Validate SMTP credentials
    if (empty($smtpUsername) || empty($smtpPassword)) {
        throw new Exception('SMTP username and password are required');
    }

    // Handle profile picture upload
    $profilePicturePath = '';
    $profileCid = '';
    if (!empty($_FILES['profile_picture']['tmp_name'])) {
        // Create uploads directory if it doesn't exist
        if (!file_exists('uploads')) {
            mkdir('uploads', 0777, true);
        }
        
        // Check file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
        $fileType = $_FILES['profile_picture']['type'];
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Only JPG, PNG, and GIF files are allowed for profile picture');
        }
        
        // Check file size (max 5MB)
        if ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
            throw new Exception('Profile picture size should not exceed 5MB');
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $profilePicturePath = 'uploads/logo_' . time() . '_' . uniqid() . '.' . $fileExtension;
        
        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $profilePicturePath)) {
            throw new Exception('Failed to upload profile picture');
        }
    }

    // Handle document attachments
    $documentPaths = [];
    if (!empty($_FILES['documents']['tmp_name'][0])) {
        // Create uploads directory if it doesn't exist
        if (!file_exists('uploads/documents')) {
            mkdir('uploads/documents', 0777, true);
        }

        $allowedDocumentTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/jpg',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'text/plain',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ];

        foreach ($_FILES['documents']['tmp_name'] as $index => $tmpName) {
            if (!empty($tmpName)) {
                // Check file type
                $fileType = $_FILES['documents']['type'][$index];
                if (!in_array($fileType, $allowedDocumentTypes)) {
                    throw new Exception('Invalid file type for document: ' . $_FILES['documents']['name'][$index]);
                }

                // Check file size (max 10MB per file)
                if ($_FILES['documents']['size'][$index] > 10 * 1024 * 1024) {
                    throw new Exception('Document size should not exceed 10MB for: ' . $_FILES['documents']['name'][$index]);
                }

                // Generate unique filename
                $fileExtension = pathinfo($_FILES['documents']['name'][$index], PATHINFO_EXTENSION);
                $documentPath = 'uploads/documents/doc_' . time() . '_' . $index . '_' . uniqid() . '.' . $fileExtension;
                
                if (!move_uploaded_file($tmpName, $documentPath)) {
                    throw new Exception('Failed to upload document: ' . $_FILES['documents']['name'][$index]);
                }
                
                $documentPaths[] = [
                    'path' => $documentPath,
                    'name' => $_FILES['documents']['name'][$index]
                ];
            }
        }
    }

    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    // Server settings
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    
    // Set encryption based on security setting
    switch ($smtpSecurity) {
        case 'ssl':
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            break;
        case 'tls':
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            break;
        default:
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
            break;
    }
    
    $mail->Port = $smtpPort;

    // Enable debug output for troubleshooting (remove in production)
    // $mail->SMTPDebug = SMTP::DEBUG_SERVER;

    // Recipients
    $mail->setFrom($smtpUsername, $companyName);
    $mail->addAddress($toEmail, $userName);
    $mail->addReplyTo($smtpUsername, $companyName);

    // Embed the profile picture (company logo) if it exists - Fixed for Gmail compatibility
    if (!empty($profilePicturePath) && file_exists($profilePicturePath)) {
        $profileCid = 'company-logo-' . time() . '-' . uniqid();
        $mail->addEmbeddedImage($profilePicturePath, $profileCid, 'logo.' . pathinfo($profilePicturePath, PATHINFO_EXTENSION), 'base64', mime_content_type($profilePicturePath));
    }

    // Attach documents
    foreach ($documentPaths as $doc) {
        $mail->addAttachment($doc['path'], $doc['name']);
    }

    // Generate email HTML content
    $emailHtml = generateBeautyMailTemplate(
        $companyName, 
        $userName, 
        $emailContent, 
        $emailType, 
        $profileCid,
        $socialLinks,
        $showAutomatedMessage,
        $includeUnsubscribe
    );

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body = $emailHtml;
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $emailContent));

    // Send email
    $mail->send();
    
    $response['success'] = true;
    $response['message'] = 'Email sent successfully to ' . $toEmail;
    
    // Clean up uploaded files after sending
    if (!empty($profilePicturePath) && file_exists($profilePicturePath)) {
        unlink($profilePicturePath);
    }
    
    foreach ($documentPaths as $doc) {
        if (file_exists($doc['path'])) {
            unlink($doc['path']);
        }
    }

} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
    $response['debug'][] = 'Exception: ' . $e->getMessage();
    
    // Log error for debugging
    error_log('BeautyMail Error: ' . $e->getMessage());
}

// Return JSON response
echo json_encode($response);

/**
 * Generate beautiful HTML email template based on selected style with social media and options
 */
function generateBeautyMailTemplate($companyName, $userName, $content, $emailType, $logoCid, $socialLinks, $showAutomatedMessage, $includeUnsubscribe) {
    // Format the content with proper paragraphs
    $formattedContent = '';
    $paragraphs = explode("\n", $content);
    foreach ($paragraphs as $paragraph) {
        if (trim($paragraph) !== '') {
            $formattedContent .= "<p style='margin: 0 0 16px 0; line-height: 1.7; color: inherit; font-size: 16px;'>" . htmlspecialchars(trim($paragraph)) . "</p>";
        }
    }

    // Current date and time
    $timestamp = date('F j, Y \a\t g:i A');
    $year = date('Y');
    
    // Enhanced Company logo/profile picture - Gmail-friendly
    $logoHtml = '';
    if (!empty($logoCid)) {
        $logoHtml = '<div style="text-align: center; margin-bottom: 25px;">
                        <img src="cid:' . $logoCid . '" alt="' . htmlspecialchars($companyName) . '" 
                             style="width: 80px; height: 80px; border-radius: 12px; border: 3px solid rgba(255,255,255,0.3); 
                                    box-shadow: 0 4px 20px rgba(0,0,0,0.15); object-fit: cover; display: block;">
                     </div>';
    } else {
        // Enhanced default logo placeholder
        $firstLetter = strtoupper(substr($companyName, 0, 1));
        $logoHtml = '<div style="text-align: center; margin-bottom: 25px;">
                        <div style="width: 80px; height: 80px; border-radius: 12px; margin: 0 auto; 
                                    background: rgba(255,255,255,0.25); display: inline-flex; align-items: center; 
                                    justify-content: center; font-size: 32px; font-weight: 700; color: white; 
                                    border: 3px solid rgba(255,255,255,0.3); box-shadow: 0 4px 20px rgba(0,0,0,0.15);">
                            ' . $firstLetter . '
                        </div>
                     </div>';
    }
    
    // Enhanced Template color schemes with mature, professional colors
    $templateStyles = [
        'executive' => [
            'primary' => '#1a1a2e',
            'secondary' => '#16213e',
            'background' => '#f8faff',
            'text' => '#2c3e50',
            'accent' => '#d4af37',
            'gradient' => 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f4c75 100%)'
        ],
        'creative' => [
            'primary' => '#667eea',
            'secondary' => '#764ba2',
            'background' => '#faf5ff',
            'text' => '#4c1d95',
            'accent' => '#8b5cf6',
            'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
        ],
        'minimal' => [
            'primary' => '#2c3e50',
            'secondary' => '#34495e',
            'background' => '#f8f9fa',
            'text' => '#2c3e50',
            'accent' => '#95a5a6',
            'gradient' => 'linear-gradient(135deg, #2c3e50 0%, #34495e 100%)'
        ],
        'modern' => [
            'primary' => '#0d9488',
            'secondary' => '#14b8a6',
            'background' => '#f0fdfa',
            'text' => '#134e4a',
            'accent' => '#2dd4bf',
            'gradient' => 'linear-gradient(135deg, #0d9488 0%, #14b8a6 100%)'
        ]
    ];
    
    $colors = $templateStyles[$emailType] ?? $templateStyles['executive'];
    
    // Generate Social Media Links HTML with enhanced styling
    $socialMediaHtml = generateSocialMediaLinks($socialLinks, $colors);
    
    // Generate unsubscribe HTML if enabled
    $unsubscribeHtml = '';
    if ($includeUnsubscribe) {
        $unsubscribeHtml = '
            <div style="margin-top: 25px; padding-top: 20px; border-top: 2px solid #e5e7eb;">
                <p style="margin: 0; color: #9ca3af; font-size: 13px; text-align: center;">
                    If you no longer wish to receive these emails, you can 
                    <a href="#" style="color: ' . $colors['accent'] . '; text-decoration: none; font-weight: 500;">unsubscribe here</a>.
                </p>
            </div>';
    }
    
    // Enhanced automated message HTML
    $automatedMessageHtml = '';
    if ($showAutomatedMessage) {
        $automatedMessageHtml = '
            <div style="margin-top: 25px; padding: 18px; background: linear-gradient(135deg, rgba(156, 163, 175, 0.1), rgba(209, 213, 219, 0.1)); 
                        border-radius: 12px; border-left: 4px solid ' . $colors['accent'] . ';">
                <p style="margin: 0; color: #6b7280; font-size: 14px; line-height: 1.6;">
                    <strong style="color: ' . $colors['primary'] . ';">📧 Automated Message Notice:</strong><br>
                    This is an automated email from our system. Please do not reply directly to this message. 
                    If you have any questions or need assistance, please contact our support team.
                </p>
            </div>';
    }
    
    // Generate the enhanced HTML email template
    $emailHtml = '
    <!DOCTYPE html>
    <html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:o="urn:schemas-microsoft-com:office:office">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>' . htmlspecialchars($companyName) . ' - Professional Email</title>
        <style>
            @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap");
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                line-height: 1.6;
                color: #333333;
                background-color: #f5f7fa;
                margin: 0;
                padding: 0;
            }
            
            .email-container {
                max-width: 650px;
                margin: 0 auto;
                background-color: #ffffff;
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 10px 50px rgba(0,0,0,0.1);
            }
            
            .header {
                background: ' . $colors['gradient'] . ';
                padding: 45px 35px;
                text-align: center;
                position: relative;
            }
            
            .header::before {
                content: "";
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url("data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23ffffff\' fill-opacity=\'0.05\'%3E%3Ccircle cx=\'30\' cy=\'30\' r=\'4\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
                pointer-events: none;
            }
            
            .header h1 {
                color: white;
                font-size: 32px;
                font-weight: 700;
                margin: 15px 0 0 0;
                text-shadow: 0 2px 8px rgba(0,0,0,0.3);
                letter-spacing: -0.5px;
                position: relative;
                z-index: 1;
            }
            
            .content {
                padding: 45px 35px;
                background-color: #ffffff;
                position: relative;
            }
            
            .greeting {
                color: ' . $colors['primary'] . ';
                font-size: 20px;
                font-weight: 600;
                margin-bottom: 25px;
                border-bottom: 2px solid ' . $colors['accent'] . ';
                padding-bottom: 15px;
            }
            
            .message-content {
                color: ' . $colors['text'] . ';
                font-size: 16px;
                line-height: 1.7;
                margin-bottom: 35px;
            }
            
            .signature {
                margin-top: 35px;
                padding-top: 25px;
                border-top: 3px solid ' . $colors['accent'] . ';
                text-align: center;
            }
            
            .signature-company {
                color: ' . $colors['primary'] . ';
                font-size: 18px;
                font-weight: 700;
                margin-bottom: 8px;
            }
            
            .signature-text {
                color: ' . $colors['text'] . ';
                font-size: 15px;
                font-weight: 500;
            }
            
            .footer {
                background: linear-gradient(135deg, ' . $colors['background'] . ', rgba(255,255,255,0.8));
                padding: 35px;
                text-align: center;
                border-top: 1px solid #e5e7eb;
            }
            
            .footer p {
                margin: 8px 0;
                color: #6b7280;
                font-size: 14px;
                line-height: 1.6;
            }
            
            .footer .timestamp {
                color: #9ca3af;
                font-size: 12px;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #e5e7eb;
                font-weight: 500;
            }
            
            /* Mobile responsive */
            @media only screen and (max-width: 600px) {
                .email-container {
                    margin: 10px !important;
                    border-radius: 12px !important;
                }
                
                .header, .content, .footer {
                    padding: 25px 20px !important;
                }
                
                .header h1 {
                    font-size: 26px !important;
                }
                
                .greeting {
                    font-size: 18px !important;
                }
                
                .message-content {
                    font-size: 15px !important;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <!-- Enhanced Header Section -->
            <div class="header">
                ' . $logoHtml . '
                <h1>' . htmlspecialchars($companyName) . '</h1>
            </div>
            
            <!-- Enhanced Content Section -->
            <div class="content">
                <div class="greeting">
                    Hello ' . htmlspecialchars($userName) . ',
                </div>
                
                <div class="message-content">
                    ' . $formattedContent . '
                </div>
                
                ' . $socialMediaHtml . '
                
                <div class="signature">
                    <p class="signature-text">
                        Best regards,
                    </p>
                    <p class="signature-company">
                        ' . htmlspecialchars($companyName) . ' Team
                    </p>
                </div>
                
                ' . $automatedMessageHtml . '
                ' . $unsubscribeHtml . '
            </div>
            
            <!-- Enhanced Footer Section -->
            <div class="footer">
                <p style="font-weight: 600; color: ' . $colors['primary'] . '; font-size: 16px;">
                    Thank you for choosing ' . htmlspecialchars($companyName) . '
                </p>
                <p>
                    We appreciate your trust and look forward to serving you better.
                </p>
                
                <div class="timestamp">
                    <p>
                        © ' . $year . ' ' . htmlspecialchars($companyName) . '. All rights reserved.<br>
                        📧 Email sent on ' . $timestamp . '
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>';

    return $emailHtml;
}

/**
 * Generate enhanced social media links HTML
 */
function generateSocialMediaLinks($socialLinks, $colors) {
    $socialIcons = [
        'facebook' => ['icon' => '📘', 'color' => '#1877f2', 'name' => 'Facebook'],
        'twitter' => ['icon' => '🐦', 'color' => '#1da1f2', 'name' => 'Twitter'],
        'instagram' => ['icon' => '📷', 'color' => '#e4405f', 'name' => 'Instagram'],
        'linkedin' => ['icon' => '💼', 'color' => '#0077b5', 'name' => 'LinkedIn'],
        'youtube' => ['icon' => '📹', 'color' => '#ff0000', 'name' => 'YouTube'],
        'website' => ['icon' => '🌐', 'color' => '#6b7280', 'name' => 'Website']
    ];

    $activeSocials = array_filter($socialLinks, function($url) {
        return !empty(trim($url));
    });

    if (empty($activeSocials)) {
        return '';
    }

    $html = '
    <div style="margin: 30px 0; padding: 25px; background: linear-gradient(135deg, rgba(255,255,255,0.8), ' . $colors['background'] . '); 
                border-radius: 16px; border: 2px solid ' . $colors['accent'] . '; text-align: center;">
        <h3 style="margin: 0 0 20px 0; color: ' . $colors['primary'] . '; font-size: 18px; font-weight: 600;">
            🔗 Connect With Us
        </h3>
        <div style="display: inline-block;">
            <table cellpadding="0" cellspacing="0" border="0">
                <tr>';
    
    foreach ($activeSocials as $platform => $url) {
        if (isset($socialIcons[$platform])) {
            $social = $socialIcons[$platform];
            $html .= '
                <td style="padding: 0 8px;">
                    <a href="' . htmlspecialchars($url) . '" 
                       style="display: inline-block; width: 45px; height: 45px; background: ' . $social['color'] . '; 
                              color: white; border-radius: 12px; text-decoration: none; font-size: 20px; 
                              line-height: 45px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.2);
                              transition: transform 0.2s ease;" 
                       title="' . $social['name'] . '">
                        ' . $social['icon'] . '
                    </a>
                </td>';
        }
    }
    
    $html .= '
                </tr>
            </table>
        </div>
        <p style="margin: 15px 0 0 0; color: ' . $colors['text'] . '; font-size: 14px;">
            Stay connected and never miss an update!
        </p>
    </div>';

    return $html;
}

/**
 * Enhanced sanitize and validate input data
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Enhanced email activity logging
 */
function logEmailActivity($toEmail, $subject, $status, $message = '') {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'to_email' => $toEmail,
        'subject' => $subject,
        'status' => $status,
        'message' => $message,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ];
    
    $logFile = 'logs/beautymail_log.json';
    
    // Create logs directory if it doesn't exist
    if (!file_exists('logs')) {
        mkdir('logs', 0755, true);
    }
    
    // Append to log file
    $existingLogs = [];
    if (file_exists($logFile)) {
        $existingLogs = json_decode(file_get_contents($logFile), true) ?? [];
    }
    
    $existingLogs[] = $logEntry;
    
    // Keep only last 2000 entries to prevent file from getting too large
    if (count($existingLogs) > 2000) {
        $existingLogs = array_slice($existingLogs, -2000);
    }
    
    file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
?>