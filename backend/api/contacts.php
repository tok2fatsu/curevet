<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/available_slots.php';
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- Handle available slots request ---
    if (isset($_GET['action']) && $_GET['action'] === 'availableSlots' && !empty($_GET['date'])) {
        $date = $_GET['date'];

        // All slots 09:00–17:00 with 1-hour gaps
        $slots = [];
        for ($hour = 9; $hour <= 17; $hour++) {
            $slots[] = sprintf("%02d:00", $hour);
        }

        // Fetch booked slots
        $stmt = $pdo->prepare("SELECT booking_time FROM contact_form WHERE booking_date = ?");
        $stmt->execute([$date]);
        $booked = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Exclude booked
        $available = array_values(array_diff($slots, $booked));

        echo json_encode(['success' => true, 'slots' => $available]);
        exit;
    }

    // --- Handle booking submission ---
    $required = ['name','email','service','booking_date','booking_time','message','consent'];
    $errors = [];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $errors[] = "Missing: $field";
        }
    }

    if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email address";
    }

    if ($errors) {
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }

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

    // Receipt email to user
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
