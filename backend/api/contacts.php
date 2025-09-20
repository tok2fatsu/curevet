<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- 1. Handle available slots request ---
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'availableSlots') {
        $date = $_GET['date'] ?? null;
        if (!$date) {
            echo json_encode(['success' => false, 'errors' => ['Missing date']]);
            exit;
        }

        // Get booked times for that date
        $stmt = $pdo->prepare("SELECT booking_time FROM contact_form WHERE booking_date = ?");
        $stmt->execute([$date]);
        $bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Generate slots (9:00–17:00, 1 hour apart)
        $slots = [];
        $start = new DateTime("09:00");
        $end = new DateTime("17:00");
        while ($start < $end) {
            $time = $start->format("H:i");
            if (!in_array($time, $bookedTimes)) {
                $slots[] = $time;
            }
            $start->modify("+1 hour");
        }

        echo json_encode(['success' => true, 'slots' => $slots]);
        exit;
    }

    // --- 2. Handle booking submission ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        // Insert booking
        $stmt = $pdo->prepare("INSERT INTO contact_form 
            (name, email, phone, service, booking_date, booking_time, message, created_at) 
            VALUES (?,?,?,?,?,?,?,NOW())");

        try {
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
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                echo json_encode(['success' => false, 'errors' => ['This time slot is already booked.']]);
                exit;
            } else {
                throw $e;
            }
        }

        // Email to clinic
        $to = "bookings@curevet.org";
        $subject = "New Booking Request - CureVet";
        $body = "New appointment booked:\n\n".
                "Booking ID: $booking_id\n".
                "Name: {$_POST['name']}\n".
                "Email: {$_POST['email']}\n".
                "Phone: {$_POST['phone']}\n".
                "Service: {$_POST['service']}\n".
                "Date: {$_POST['booking_date']} {$_POST['booking_time']}\n".
                "Message: {$_POST['message']}";
        $headers = "From: bookings@curevet.org\r\nReply-To: {$_POST['email']}";

        @mail($to, $subject, $body, $headers);

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
        exit;
    }

    echo json_encode(['success' => false, 'errors' => ['Invalid request']]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'errors' => [$e->getMessage()]]);
}
