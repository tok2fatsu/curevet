<?php
/**
 * contacts.php — secure contact form handler (production-safe)
 * Uses PHP mail() instead of SMTP
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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_GET['action'] === 'availableSlots') {
    $date = $_GET['date'] ?? null;
    if (!$date) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing date']);
        exit;
    }

    // TODO: Pull available slots from your DB or static schedule
    $slots = ['09:00 AM', '10:30 AM', '12:00 PM', '02:00 PM', '03:30 PM'];

    header('Content-Type: application/json');
    echo json_encode(['slots' => $slots]);
    exit;
}


// -----------------------------------------------------------------------------
// 5. Sanitize and validate inputs
// -----------------------------------------------------------------------------
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');
$service = trim($_POST['service'] ?? '');
$date    = trim($_POST['booking_date'] ?? '');
$time    = trim($_POST['booking_time'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $service === '' || $date === '' || $time === '') {
    respond(false, 'Please fill all required fields.', 400);
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
        if ($count === 1) $redis->expire($key, 60);
        if ($count > 5) respond(false, 'Too many submissions. Please wait a minute.', 429);
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

// --- Check for existing booking on same date/time/service ---

    $checkStmt = $pdo->prepare("
        SELECT id FROM contact_form
        WHERE service = :service AND booking_date = :booking_date AND booking_time = :booking_time
        LIMIT 1
    ");
    $checkStmt->execute([
        ':service' => $service,
        ':booking_date' => $date,
        ':booking_time' => $time
    ]);
    if ($checkStmt->fetch()) {
        respond(false, "That time slot for $service on $date at $time is already booked. Please choose another.", 409);
    }

// --- Insert new booking ---

    $stmt = $pdo->prepare("
        INSERT INTO contact_form (name, email, phone, service, booking_date, booking_time, message, created_at)
        VALUES (:name, :email, :phone, :service, :booking_date, :booking_time, :message, NOW())
    ");
    $stmt->execute([
        ':name'         => $name,
        ':email'        => $email,
        ':phone'        => $phone,
        ':service'      => $service,
        ':booking_date' => $date,
        ':booking_time' => $time,
        ':message'      => $message,
    ]);
    $booking_id = $pdo->lastInsertId();

} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    respond(false, 'Could not save your booking. Please try again later.', 500);
}

// -----------------------------------------------------------------------------
// 8. Send emails via PHP mail()
// -----------------------------------------------------------------------------
$admin_to = "bookings@curevet.org";
$admin_subject = "New Booking Request - CureVet";
$admin_body = "New appointment booked:\n\n".
              "Booking ID: $booking_id\n".
              "Name: $name\n".
              "Email: $email\n".
              "Phone: $phone\n".
              "Service: $service\n".
              "Date: $date $time\n".
              "Message: $message\n\n".
              "-- CureVet System";

$admin_headers = "From: bookings@curevet.org\r\n";
$admin_headers .= "Reply-To: $email\r\n";
$admin_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (!@mail($admin_to, $admin_subject, $admin_body, $admin_headers)) {
    error_log("Admin email failed for booking #$booking_id ($email)");
}

// --- Receipt to user ---
$user_subject = "Your Booking Confirmation - CureVet";
$user_body = "Dear $name,\n\n".
             "Thank you for booking with CureVet. Here are your details:\n\n".
             "Booking ID: $booking_id\n".
             "Service: $service\n".
             "Date: $date at $time\n\n".
             "We look forward to seeing you!\n\n".
             "— CureVet Team";

$user_headers = "From: bookings@curevet.org\r\n";
$user_headers .= "Reply-To: bookings@curevet.org\r\n";
$user_headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

if (!@mail($email, $user_subject, $user_body, $user_headers)) {
    error_log("User confirmation email failed for booking #$booking_id ($email)");
}

// -----------------------------------------------------------------------------
// 9. Optional cache/log
// -----------------------------------------------------------------------------
if ($redis) {
    try {
        $redis->rpush('contact_submissions', json_encode([
            'ip'       => $ip,
            'name'     => $name,
            'email'    => $email,
            'service'  => $service,
            'datetime' => date('Y-m-d H:i:s'),
        ]));
    } catch (Exception $e) {
        error_log('Redis logging failed: ' . $e->getMessage());
    }
}

// -----------------------------------------------------------------------------
// 10. Response
// -----------------------------------------------------------------------------
respond(true, 'Your booking has been successfully submitted!');

