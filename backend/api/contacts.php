<?php
/**
 * contacts.php — secure contact form handler
 * Production-safe version
 */

header('Content-Type: application/json');

// -----------------------------------------------------------------------------
// 1. Load configuration and dependencies
// -----------------------------------------------------------------------------
$config = require __DIR__ . '/../config.php';
$redis  = require __DIR__ . '/../includes/redis.php';

// -----------------------------------------------------------------------------
// 2. Security headers
// -----------------------------------------------------------------------------
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: interest-cohort=()");

// -----------------------------------------------------------------------------
// 3. Utility: send JSON responses
// -----------------------------------------------------------------------------
function respond($success, $message, $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
    ]);
    exit;
}

// -----------------------------------------------------------------------------
// 4. Validate request method
// -----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', 405);
}

// -----------------------------------------------------------------------------
// 5. Sanitize and validate inputs
// -----------------------------------------------------------------------------
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');
$phone   = trim($_POST['phone'] ?? '');

if ($name === '' || $email === '' || $message === '') {
    respond(false, 'Name, email, and message are required fields.', 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Invalid email format.', 400);
}
if ($phone !== '' && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) {
    respond(false, 'Invalid phone number format.', 400);
}

// -----------------------------------------------------------------------------
// 6. Rate limiting via Redis (if available)
// -----------------------------------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if ($redis) {
    try {
        $key = "contact_limit:" . sha1($ip);
        $count = $redis->incr($key);
        if ($count === 1) {
            $redis->expire($key, 60); // 1 minute
        }
        if ($count > 5) {
            respond(false, 'Too many submissions. Please wait a minute and try again.', 429);
        }
    } catch (Exception $e) {
        error_log('Redis rate-limit failed: ' . $e->getMessage());
    }
}

// -----------------------------------------------------------------------------
// 7. Save message to MySQL
// -----------------------------------------------------------------------------
try {
    $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $stmt = $pdo->prepare("
        INSERT INTO curevedd_bookings (name, email, phone, subject, message, created_at)
        VALUES (:name, :email, :phone, :subject, :message, NOW())
    ");
    $stmt->execute([
        ':name'    => $name,
        ':email'   => $email,
        ':phone'   => $phone,
        ':subject' => $subject,
        ':message' => $message,
    ]);

} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    respond(false, 'Failed to save your message. Please try again later.', 500);
}

// -----------------------------------------------------------------------------
// 8. Optional caching (for analytics or logs)
// -----------------------------------------------------------------------------
if ($redis) {
    try {
        $redis->rpush('contact_submissions', json_encode([
            'ip'       => $ip,
            'name'     => $name,
            'email'    => $email,
            'subject'  => $subject,
            'datetime' => date('Y-m-d H:i:s'),
        ]));
    } catch (Exception $e) {
        error_log('Redis cache logging failed: ' . $e->getMessage());
    }
}

// -----------------------------------------------------------------------------
// 9. Send confirmation or notification email (optional)
// -----------------------------------------------------------------------------
// You can integrate PHPMailer or SMTP here securely
// if you already have `$config['smtp_*']` defined.

$user_email = $_POST['email'];
    $receipt_subject = "Your Booking with CureVet - Confirmation";
    $receipt_body = "Dear {$_POST['name']},\n\n".
                    "Thank you for booking with CureVet. Here are your appointment details:\n\n".
                    "Booking ID: $booking_id\n".
                    "Service: {$_POST['service']}\n".
                    "Date: {$_POST['booking_date']} at {$_POST['booking_time']}\n\n".
                    "We look forward to seeing you and your pet.\n\n".
                    "— CureVet Team";
    $receipt_headers = "From: bookings@curevet.org\r\nReply-To: bookings@curevet.org";
    @mail($user_email, $receipt_subject, $receipt_body, $receipt_headers);

    echo json_encode(['success' => true, 'booking_id' => $booking_id]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
}

// -----------------------------------------------------------------------------
// 10. Success response
// -----------------------------------------------------------------------------
respond(true, 'Your message has been sent successfully!');

