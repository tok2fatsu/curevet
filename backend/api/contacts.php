<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'DB connection failed']);
    exit;
}

// ----------------------------------------------------------------------------
// Handle GET request for available slots
// ----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'availableSlots') {
    $date = $_GET['date'] ?? null;
    if (!$date) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing date']);
        exit;
    }

    // Generate 1-hour slots (09:00–17:00)
    $all_slots = [];
    $start = strtotime('09:00');
    $end = strtotime('17:00');
    while ($start <= $end) {
        $all_slots[] = date('H:i', $start);
        $start = strtotime('+1 hour', $start);
    }

    // Fetch booked slots for this date
    $stmt = $pdo->prepare("SELECT booking_time FROM contact_form WHERE booking_date = ?");
    $stmt->execute([$date]);
    $booked = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Filter out booked slots
    $available_slots = array_values(array_diff($all_slots, $booked));

    echo json_encode([
        'success' => true,
        'slots' => $available_slots
    ]);
    exit;
}

// ----------------------------------------------------------------------------
// Handle POST request for booking submission
// ----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $required = ['name', 'email', 'service', 'booking_date', 'booking_time', 'message', 'consent'];
    $errors = [];

    foreach ($required as $field) {
        if (empty($_POST[$field])) $errors[] = "Missing: $field";
    }

    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address";
    }

    if ($errors) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO contact_form 
            (name, email, phone, service, booking_date, booking_time, message, created_at) 
            VALUES (?,?,?,?,?,?,?,NOW())");
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['phone'] ?? null,
            $_POST['service'],
            $_POST['booking_date'],
            $_POST['booking_time'],
            $_POST['message']
        ]);

        $booking_id = $pdo->lastInsertId();

        // Notify clinic
        $clinic_to = "bookings@curevet.org";
        $clinic_subject = "New Booking - CureVet";
        $clinic_body = "New booking received:\n\n"
            . "Booking ID: $booking_id\n"
            . "Name: {$_POST['name']}\n"
            . "Email: {$_POST['email']}\n"
            . "Phone: {$_POST['phone']}\n"
            . "Service: {$_POST['service']}\n"
            . "Date: {$_POST['booking_date']} {$_POST['booking_time']}\n"
            . "Message: {$_POST['message']}";
        @mail($clinic_to, $clinic_subject, $clinic_body, "From: bookings@curevet.org");

        // Send receipt to user
        $user_to = $_POST['email'];
        $user_subject = "CureVet Appointment Confirmation";
        $user_body = "Dear {$_POST['name']},\n\nThank you for booking with CureVet.\n\n"
            . "Booking ID: $booking_id\n"
            . "Service: {$_POST['service']}\n"
            . "Date: {$_POST['booking_date']} at {$_POST['booking_time']}\n\n"
            . "We look forward to seeing you.\n\n– The CureVet Team";
        @mail($user_to, $user_subject, $user_body, "From: bookings@curevet.org");

        echo json_encode(['success' => true, 'booking_id' => $booking_id]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            echo json_encode(['success' => false, 'errors' => ['This time slot is already booked.']]);
        } else {
            echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
        }
    }
    exit;
}

// ----------------------------------------------------------------------------
// Invalid request fallback
// ----------------------------------------------------------------------------
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
exit;
?>

