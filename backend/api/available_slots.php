<?php
header('Content-Type: application/json');

require_once __DIR__ . '../config.php';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!isset($_GET['date']) || empty($_GET['date'])) {
        echo json_encode(['error' => 'Missing date']);
        exit;
    }

    $date = $_GET['date'];

    // Define working hours (example: 9am–5pm, 1-hour slots)
    $start = new DateTime('09:00');
    $end   = new DateTime('17:00');
    $interval = new DateInterval('PT1H');
    $slots = [];

    for ($time = clone $start; $time < $end; $time->add($interval)) {
        $slots[] = $time->format('H:i:s');
    }

    // Fetch booked slots for the given date
    $stmt = $pdo->prepare("SELECT booking_time FROM contact_form WHERE booking_date = ?");
    $stmt->execute([$date]);
    $booked = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Filter out booked times
    $available = array_values(array_diff($slots, $booked));

    echo json_encode(['available_slots' => $available]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

