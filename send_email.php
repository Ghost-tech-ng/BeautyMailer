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
    $emailType = !empty($_POST['email_type']) ? $_POST['email_type'] : 'business';
    
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
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
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

    // Embed the profile picture (company logo) if it exists
    if (!empty($profilePicturePath) && file_exists($profilePicturePath)) {
        $profileCid = 'logo-' . time() . '-' . uniqid();
        $mail->addEmbeddedImage($profilePicturePath, $profileCid);
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
        $profileCid
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
 * Generate beautiful HTML email template based on selected style
 */
function generateBeautyMailTemplate($companyName, $userName, $content, $emailType, $logoCid) {
    // Format the content with proper paragraphs
    $formattedContent = '';
    $paragraphs = explode("\n", $content);
    foreach ($paragraphs as $paragraph) {
        if (trim($paragraph) !== '') {
            $formattedContent .= "<p style='margin: 0 0 15px 0; line-height: 1.6; color: inherit;'>" . htmlspecialchars(trim($paragraph)) . "</p>";
        }
    }

    // Current date and time
    $timestamp = date('F j, Y \a\t g:i A');
    $year = date('Y');
    
    // Company logo/profile picture
    $logoHtml = '';
    if (!empty($logoCid)) {
        $logoHtml = '<img src="cid:' . $logoCid . '" alt="' . htmlspecialchars($companyName) . '" style="width: 60px; height: 60px; border-radius: 10px; margin-bottom: 20px; background: rgba(255,255,255,0.2); padding: 8px;">';
    } else {
        // Default logo placeholder
        $firstLetter = strtoupper(substr($companyName, 0, 1));
        $logoHtml = '<div style="width: 60px; height: 60px; border-radius: 10px; margin: 0 auto 20px; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: bold; color: white;">' . $firstLetter . '</div>';
    }
    
    // Template color schemes
    $templateStyles = [
        'business' => [
            'primary' => '#1e40af',
            'secondary' => '#3b82f6',
            'background' => '#f8faff',
            'text' => '#1f2937',
            'accent' => '#dbeafe'
        ],
        'creative' => [
            'primary' => '#db2777',
            'secondary' => '#ec4899',
            'background' => '#fdf2f8',
            'text' => '#831843',
            'accent' => '#fce7f3'
        ],
        'minimal' => [
            'primary' => '#6b7280',
            'secondary' => '#9ca3af',
            'background' => '#f9fafb',
            'text' => '#374151',
            'accent' => '#f3f4f6'
        ],
        'modern' => [
            'primary' => '#059669',
            'secondary' => '#10b981',
            'background' => '#f0fdf4',
            'text' => '#065f46',
            'accent' => '#d1fae5'
        ]
    ];
    
    $colors = $templateStyles[$emailType] ?? $templateStyles['business'];
    
    // Generate the HTML email template
    $emailHtml = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>' . htmlspecialchars($companyName) . ' - Email</title>
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
                background-color: #f5f5f5;
            }
            
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
            }
            
            .header {
                background: linear-gradient(135deg, ' . $colors['primary'] . ', ' . $colors['secondary'] . ');
                padding: 40px 30px;
                text-align: center;
                border-radius: 12px 12px 0 0;
            }
            
            .header h1 {
                color: white;
                font-size: 28px;
                font-weight: 700;
                margin: 0;
                text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            }
            
            .content {
                padding: 40px 30px;
                background-color: #ffffff;
            }
            
            .greeting {
                color: ' . $colors['primary'] . ';
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 20px;
            }
            
            .message-content {
                color: ' . $colors['text'] . ';
                font-size: 16px;
                line-height: 1.6;
                margin-bottom: 30px;
            }
            
            .signature {
                border-top: 2px solid ' . $colors['accent'] . ';
                padding-top: 20px;
                margin-top: 30px;
                color: ' . $colors['text'] . ';
            }
            
            .footer {
                background-color: ' . $colors['background'] . ';
                padding: 30px;
                text-align: center;
                border-radius: 0 0 12px 12px;
                border: 1px solid #e5e7eb;
                border-top: none;
            }
            
            .footer p {
                margin: 5px 0;
                color: #6b7280;
                font-size: 14px;
            }
            
            .footer .timestamp {
                color: #9ca3af;
                font-size: 12px;
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #e5e7eb;
            }
            
            /* Mobile responsive */
            @media only screen and (max-width: 600px) {
                .email-container {
                    width: 100% !important;
                    margin: 0 !important;
                }
                
                .header, .content, .footer {
                    padding: 20px !important;
                }
                
                .header h1 {
                    font-size: 24px !important;
                }
                
                .greeting {
                    font-size: 16px !important;
                }
                
                .message-content {
                    font-size: 14px !important;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <!-- Header Section -->
            <div class="header">
                ' . $logoHtml . '
                <h1>' . htmlspecialchars($companyName) . '</h1>
            </div>
            
            <!-- Content Section -->
            <div class="content">
                <div class="greeting">
                    Hello ' . htmlspecialchars($userName) . ',
                </div>
                
                <div class="message-content">
                    ' . $formattedContent . '
                </div>
                
                <div class="signature">
                    <p style="margin: 0 0 10px 0; color: ' . $colors['text'] . '; font-weight: 500;">
                        Best regards,
                    </p>
                    <p style="margin: 0; color: ' . $colors['primary'] . '; font-weight: 600; font-size: 16px;">
                        ' . htmlspecialchars($companyName) . ' Team
                    </p>
                </div>
            </div>
            
            <!-- Footer Section -->
            <div class="footer">
                <p style="font-weight: 500; color: ' . $colors['text'] . ';">
                    Thank you for choosing ' . htmlspecialchars($companyName) . '
                </p>
                <p>
                    This is an automated email from our system. Please do not reply directly to this message.
                </p>
                <p>
                    If you have any questions, please contact our support team.
                </p>
                
                <div class="timestamp">
                    <p>
                        © ' . $year . ' ' . htmlspecialchars($companyName) . '. All rights reserved.<br>
                        Email sent on ' . $timestamp . '
                    </p>
                </div>
            </div>
        </div>
    </body>
    </html>';

    return $emailHtml;
}

/**
 * Sanitize and validate input data
 */
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Log email activity (optional)
 */
function logEmailActivity($toEmail, $subject, $status, $message = '') {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'to_email' => $toEmail,
        'subject' => $subject,
        'status' => $status,
        'message' => $message,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    
    $logFile = 'logs/email_log.json';
    
    // Create logs directory if it doesn't exist
    if (!file_exists('logs')) {
        mkdir('logs', 0777, true);
    }
    
    // Append to log file
    $existingLogs = [];
    if (file_exists($logFile)) {
        $existingLogs = json_decode(file_get_contents($logFile), true) ?? [];
    }
    
    $existingLogs[] = $logEntry;
    
    // Keep only last 1000 entries to prevent file from getting too large
    if (count($existingLogs) > 1000) {
        $existingLogs = array_slice($existingLogs, -1000);
    }
    
    file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
}
?>