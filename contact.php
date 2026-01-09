<?php
/**
 * ManyCents Resources Contact Form Handler
 * WITH OPTIONAL AUTO-REPLY (won't break if auto-reply fails)
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Prevent direct access
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    header("Location: index.html");
    exit;
}

// Load SMTP credentials from separate file (not in git)
require_once(__DIR__ . '/credentials.php');

// Email Configuration
define('FROM_EMAIL', 'office@manycents.co.za');
define('FROM_NAME', 'ManyCents Resources');
define('TO_EMAIL', 'luhein.lategan@manycents.co.za');
define('TO_NAME', 'Luhein Lategan');
define('ENABLE_AUTO_REPLY', true); // Set to false to disable auto-reply

// Rate limiting configuration
define('MAX_SUBMISSIONS_PER_HOUR', 5);
define('RATE_LIMIT_FILE', 'rate_limit.json');

// Function to check rate limiting
function checkRateLimit($ip) {
    if (!file_exists(RATE_LIMIT_FILE)) {
        @file_put_contents(RATE_LIMIT_FILE, json_encode([]));
    }
    
    $data = @json_decode(@file_get_contents(RATE_LIMIT_FILE), true);
    if (!$data) $data = [];
    
    $currentTime = time();
    $oneHourAgo = $currentTime - 3600;
    
    $data = array_filter($data, function($timestamp) use ($oneHourAgo) {
        return $timestamp > $oneHourAgo;
    });
    
    $ipSubmissions = array_filter($data, function($timestamp, $storedIp) use ($ip) {
        return strpos($storedIp, $ip) === 0;
    }, ARRAY_FILTER_USE_BOTH);
    
    if (count($ipSubmissions) >= MAX_SUBMISSIONS_PER_HOUR) {
        return false;
    }
    
    $data[$ip . '_' . $currentTime] = $currentTime;
    @file_put_contents(RATE_LIMIT_FILE, json_encode($data));
    
    return true;
}

// Get visitor IP
function getVisitorIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $_SERVER['REMOTE_ADDR'];
}

// Check honeypot
if (!empty($_POST['website'])) {
    header("Location: index.html?error=spam");
    exit;
}

// Rate limiting check
$visitorIP = getVisitorIP();
if (!checkRateLimit($visitorIP)) {
    header("Location: index.html?error=rate_limit");
    exit;
}

// Get and sanitize form data
$name = isset($_POST["name"]) ? trim(strip_tags($_POST["name"])) : '';
$company = isset($_POST["company"]) ? trim(strip_tags($_POST["company"])) : '';
$email = isset($_POST["email"]) ? trim($_POST["email"]) : '';
$product = isset($_POST["product"]) ? trim(strip_tags($_POST["product"])) : 'Not specified';
$message = isset($_POST["message"]) ? trim(strip_tags($_POST["message"])) : '';

// Validate required fields
if (empty($name) || empty($company) || empty($email) || empty($message)) {
    header("Location: index.html?error=missing");
    exit;
}

// Validate email
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: index.html?error=invalid_email");
    exit;
}

// Build email subject for notification
$subject = "Website Inquiry from " . $name . " (" . $company . ")";

// Build HTML email content for notification to Luhein
$htmlContent = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #ffa500; }
        .value { margin-top: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h2>New Website Inquiry</h2>
        </div>
        <div class='content'>
            <div class='field'>
                <div class='label'>From:</div>
                <div class='value'>" . htmlspecialchars($name) . "</div>
            </div>
            <div class='field'>
                <div class='label'>Company:</div>
                <div class='value'>" . htmlspecialchars($company) . "</div>
            </div>
            <div class='field'>
                <div class='label'>Email:</div>
                <div class='value'>" . htmlspecialchars($email) . "</div>
            </div>
            <div class='field'>
                <div class='label'>Product Interest:</div>
                <div class='value'>" . htmlspecialchars($product) . "</div>
            </div>
            <div class='field'>
                <div class='label'>Message:</div>
                <div class='value'>" . nl2br(htmlspecialchars($message)) . "</div>
            </div>
        </div>
    </div>
</body>
</html>
";

// Build plain text version
$textContent = "New Website Inquiry\n\n";
$textContent .= "From: " . $name . "\n";
$textContent .= "Company: " . $company . "\n";
$textContent .= "Email: " . $email . "\n";
$textContent .= "Product Interest: " . $product . "\n\n";
$textContent .= "Message:\n" . $message . "\n";

// Build AUTO-REPLY email to customer
$autoReplySubject = "Thank you for contacting ManyCents Resources";
$autoReplyHTML = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.8; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { background: #ffffff; padding: 30px; border: 1px solid #ddd; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; font-size: 14px; color: #666; border-top: 1px solid #ddd; }
        .highlight { color: #ffa500; font-weight: bold; }
        .contact-info { background: #f5f5f5; padding: 15px; border-left: 4px solid #ffa500; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>ManyCents Resources</h1>
            <p style='margin: 5px 0 0 0; font-size: 14px;'>Creator of Wealth</p>
        </div>
        <div class='content'>
            <p>Dear " . htmlspecialchars($name) . ",</p>
            
            <p>Thank you for your inquiry regarding our coal products. We have received your message and appreciate your interest in ManyCents Resources.</p>
            
            <p>Our team will review your requirements and respond within <span class='highlight'>24 hours</span> during business days.</p>
            
            <p>If your inquiry is urgent, please feel free to contact us directly:</p>
            <div class='contact-info'>
                <strong>Phone:</strong> +27 60 959 2405<br>
                <strong>Email:</strong> office@manycents.co.za<br>
                <strong>CEO:</strong> Luhein Lategan
            </div>
            
            <p>We look forward to discussing how ManyCents Resources can meet your coal supply needs.</p>
            
            <p>Best regards,<br>
            <strong>ManyCents Resources Team</strong></p>
        </div>
        <div class='footer'>
            <p><strong>ManyCents Resources (Pty) Ltd</strong><br>
            18 Bubesi House, Wellington Office Park, Durbanville, 7550, South Africa<br>
            www.manycents.co.za</p>
        </div>
    </div>
</body>
</html>
";

$autoReplyText = "Dear " . $name . ",

Thank you for your inquiry regarding our coal products. We have received your message and appreciate your interest in ManyCents Resources.

Our team will review your requirements and respond within 24 hours during business days.

If your inquiry is urgent, please feel free to contact us directly:
Phone: +27 60 959 2405
Email: office@manycents.co.za
CEO: Luhein Lategan

We look forward to discussing how ManyCents Resources can meet your coal supply needs.

Best regards,
ManyCents Resources Team

---
ManyCents Resources (Pty) Ltd
18 Bubesi House, Wellington Office Park, Durbanville, 7550, South Africa
www.manycents.co.za
";

// Try PHP mail() first (simplest)
function tryPHPMail($to, $subject, $htmlContent, $textContent, $replyEmail = null, $replyName = null) {
    $boundary = md5(time());
    
    $headers = "From: ManyCents Resources <" . FROM_EMAIL . ">\r\n";
    if ($replyEmail) {
        $headers .= "Reply-To: " . ($replyName ? $replyName : $replyEmail) . " <" . $replyEmail . ">\r\n";
    }
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n";
    
    $body = "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $textContent . "\r\n\r\n";
    $body .= "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $htmlContent . "\r\n\r\n";
    $body .= "--" . $boundary . "--";
    
    return @mail($to, $subject, $body, $headers);
}

// SMTP sending function
function sendViaSMTP($to, $subject, $htmlContent, $textContent, $replyEmail = null) {
    $smtp = @fsockopen('ssl://' . SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
    
    if (!$smtp) {
        error_log("SMTP Connection Failed: $errstr ($errno)");
        return false;
    }
    
    $sendCmd = function($cmd, $expect = 250) use ($smtp) {
        fputs($smtp, $cmd . "\r\n");
        $response = fgets($smtp, 512);
        $code = substr($response, 0, 3);
        
        if ($code != $expect) {
            error_log("SMTP Error: $cmd - Expected $expect, got $code");
            return false;
        }
        return true;
    };
    
    fgets($smtp, 512);
    
    if (!$sendCmd("EHLO " . $_SERVER['SERVER_NAME'])) {
        fclose($smtp);
        return false;
    }
    
    while ($line = fgets($smtp, 512)) {
        if ($line[3] == ' ') break;
    }
    
    if (!$sendCmd("AUTH LOGIN", 334)) {
        fclose($smtp);
        return false;
    }
    
    if (!$sendCmd(base64_encode(SMTP_USERNAME), 334)) {
        fclose($smtp);
        return false;
    }
    
    if (!$sendCmd(base64_encode(SMTP_PASSWORD), 235)) {
        fclose($smtp);
        return false;
    }
    
    if (!$sendCmd("MAIL FROM:<" . FROM_EMAIL . ">")) {
        fclose($smtp);
        return false;
    }
    
    if (!$sendCmd("RCPT TO:<" . $to . ">")) {
        fclose($smtp);
        return false;
    }
    
    if (!$sendCmd("DATA", 354)) {
        fclose($smtp);
        return false;
    }
    
    $boundary = md5(time() . rand());
    $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
    if ($replyEmail) {
        $headers .= "Reply-To: " . $replyEmail . "\r\n";
    }
    $headers .= "To: " . $to . "\r\n";
    $headers .= "Subject: " . $subject . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n\r\n";
    
    $body = "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $textContent . "\r\n\r\n";
    $body .= "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $htmlContent . "\r\n\r\n";
    $body .= "--" . $boundary . "--\r\n";
    
    fputs($smtp, $headers . $body . "\r\n.\r\n");
    $response = fgets($smtp, 512);
    
    fputs($smtp, "QUIT\r\n");
    fclose($smtp);
    
    $code = substr($response, 0, 3);
    return $code == 250;
}

// STEP 1: Send notification to Luhein (PRIORITY)
$notificationSent = false;

// Try PHP mail first
if (tryPHPMail(TO_EMAIL, $subject, $htmlContent, $textContent, $email, $name)) {
    $notificationSent = true;
    error_log("Notification sent via PHP mail()");
} 
// Try SMTP if PHP mail failed
elseif (sendViaSMTP(TO_EMAIL, $subject, $htmlContent, $textContent, $email)) {
    $notificationSent = true;
    error_log("Notification sent via SMTP");
}

// STEP 2: If notification succeeded, try to send auto-reply (optional)
if ($notificationSent && ENABLE_AUTO_REPLY) {
    // Try to send auto-reply, but don't fail if it doesn't work
    if (tryPHPMail($email, $autoReplySubject, $autoReplyHTML, $autoReplyText)) {
        error_log("Auto-reply sent via PHP mail() to: $email");
    } elseif (sendViaSMTP($email, $autoReplySubject, $autoReplyHTML, $autoReplyText)) {
        error_log("Auto-reply sent via SMTP to: $email");
    } else {
        error_log("Auto-reply failed but notification succeeded for: $email");
    }
}

// Final result - base success on notification only
if ($notificationSent) {
    error_log("Contact form SUCCESS: $name ($email)");
    header("Location: index.html?success=true");
} else {
    error_log("Contact form FAILED: $name ($email)");
    
    // Save to backup file
    $backup = "\n\n=== SUBMISSION ===\n";
    $backup .= "Date: " . date('Y-m-d H:i:s') . "\n";
    $backup .= "Name: $name\nCompany: $company\nEmail: $email\n";
    $backup .= "Product: $product\nMessage: $message\n";
    @file_put_contents('form_submissions.txt', $backup, FILE_APPEND);
    
    header("Location: index.html?error=send_failed");
}

exit;
?>
