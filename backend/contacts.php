<?php
// php/contacts.php - receives booking requests; uses Redis rate-limiting and PHPMailer SMTP
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . 'db.php';
require __DIR__ . 'redis.php';
$config = require __DIR__ . '../config.php';

// Require PHPMailer via composer vendor/autoload
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    error_log('Missing composer dependencies: run composer require phpmailer/phpmailer predis/predis');
    http_response_code(500);
    echo json_encode(['error' => 'Server configuration error']);
    exit;
}
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// RATE LIMIT via Redis (if available)
$canProceed = true;
if ($client) {
    try {
        // Predis interface or phpredis; normalize commands
        $rlKey = "rl:bookings:{$ip}";
        if ($client instanceof Predis\Client) {
            $count = $client->get($rlKey);
            if (!$count) {
                $client->setex($rlKey, $config['RATE_LIMIT_WINDOW_SEC'], 1);
            } else {
                if ((int)$count >= $config['RATE_LIMIT_MAX']) $canProceed = false;
                else $client->incr($rlKey);
            }
        } else {
            // phpredis
            $count = $client->get($rlKey);
            if (!$count) {
                $client->setex($rlKey, $config['RATE_LIMIT_WINDOW_SEC'], 1);
            } else {
                if ((int)$count >= $config['RATE_LIMIT_MAX']) $canProceed = false;
                else $client->incr($rlKey);
            }
        }
    } catch (Exception $e) {
        error_log('Redis RL error: ' . $e->getMessage());
    }
}

if (!$canProceed) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many attempts. Please wait and try later.']);
    exit;
}

// Read POST data
$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');
$booking_date = $_POST['booking_date'] ?? '';
$booking_time = $_POST['booking_time'] ?? '';
$slot_duration = 60;

$errors = [];
if (!$name) $errors[] = 'Name required';
if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required';
if (!$booking_date) $errors[] = 'Booking date required';
if (!$booking_time) $errors[] = 'Booking time required';
if (!empty($errors)) { http_response_code(400); echo json_encode(['errors'=>$errors]); exit; }

// Optional captcha verification
if (!empty($config['RECAPTCHA_SECRET_KEY'])) {
    $rec = $_POST['g-recaptcha-response'] ?? '';
    if (!$rec) { http_response_code(400); echo json_encode(['error'=>'Captcha required']); exit; }
    $verify = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . urlencode($config['RECAPTCHA_SECRET_KEY']) . '&response=' . urlencode($rec) . '&remoteip=' . $ip);
    $v = json_decode($verify, true);
    if (!isset($v['success']) || !$v['success']) { http_response_code(400); echo json_encode(['error'=>'Captcha verification failed']); exit; }
}

// Normalize date/time in clinic timezone
try {
    $tz = new DateTimeZone('Africa/Addis_Ababa');
    $dt = new DateTime($booking_date . ' ' . $booking_time, $tz);
    $bdate = $dt->format('Y-m-d');
    $btime = $dt->format('H:i:00');
} catch (Exception $e) {
    http_response_code(400); echo json_encode(['error'=>'Invalid date or time']); exit;
}

// Validate slot: must start on the hour and fall within business hours
$minute = (int)$dt->format('i');
if ($minute !== 0) { http_response_code(400); echo json_encode(['error'=>'Bookings must start on the hour']); exit; }
$hour = (int)$dt->format('H');
$openHour = 9; $closeHour = 17; // configurable
if ($hour < $openHour || $hour >= $closeHour) { http_response_code(400); echo json_encode(['error'=>'Selected time is outside business hours']); exit; }

// Insert booking with DB UNIQUE constraint protecting against race conditions
try {
    $stmt = $pdo->prepare('INSERT INTO bookings (name, email, phone, message, booking_date, booking_time, slot_duration_minutes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$name, $email, $phone, $message, $bdate, $btime, $slot_duration]);
    $bookingId = $pdo->lastInsertId();
} catch (PDOException $e) {
    // SQLSTATE 23000 = integrity constraint violation (unique)
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(['error' => 'This slot has just been booked. Please choose another.']);
        exit;
    }
    error_log('DB insert error: '.$e->getMessage());
    http_response_code(500); echo json_encode(['error'=>'Database error']); exit;
}

// Send notification email via PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = $config['SMTP_HOST'];
    $mail->SMTPAuth = true;
    $mail->Username = $config['SMTP_USER'];
    $mail->Password = $config['SMTP_PASS'];
    $mail->SMTPSecure = $config['SMTP_SECURE'] ?? 'tls';
    $mail->Port = $config['SMTP_PORT'] ?? 587;

    $mail->setFrom($config['MAIL_FROM'], 'CureVet Booking');
    $mail->addAddress($config['MAIL_TO']);
    $mail->addReplyTo($email, $name);

    $mail->isHTML(false);
    $mail->Subject = "New Booking: {$name} on {$bdate} at {$dt->format('H:i') }";
    $body = "New booking details:\n\n" .
            "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\nDate: {$bdate}\nTime: {$dt->format('H:i')}\nMessage:\n{$message}\nBooking ID: {$bookingId}\n";
    $mail->Body = $body;
    $mail->send();
} catch (Exception $e) {
    error_log('Mail error: ' . ($mail->ErrorInfo ?? $e->getMessage()));
    // Booking persisted; inform client that mail failed but booking OK
    echo json_encode(['warning' => 'Booking saved, but email failed to send.']);
    exit;
}

echo json_encode(['success' => true, 'booking_id' => $bookingId]);

