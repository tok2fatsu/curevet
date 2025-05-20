<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    $requiredFields = ['name', 'email', 'phone', 'pet_type', 'service', 'date'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            exit;
        }
    }

    // Sanitize data
    $appointment = [
        'name' => htmlspecialchars($input['name']),
        'email' => filter_var($input['email'], FILTER_SANITIZE_EMAIL),
        'phone' => htmlspecialchars($input['phone']),
        'pet_type' => htmlspecialchars($input['pet_type']),
        'service' => htmlspecialchars($input['service']),
        'date' => htmlspecialchars($input['date']),
        'message' => htmlspecialchars($input['message'] ?? ''),
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Save to file (replace with database in production)
    file_put_contents('appointments.txt', json_encode($appointment).PHP_EOL, FILE_APPEND);

    // Send confirmation email (configure SMTP in production)
    $to = $appointment['email'];
    $subject = 'Appointment Confirmation';
    $message = "Dear {$appointment['name']},\n\n";
    $message .= "Your appointment for {$appointment['service']} ";
    $message .= "on {$appointment['date']} has been received.\n";
    $message .= "We'll contact you shortly to confirm.\n\n";
    $message .= "Best regards,\nCure Veterinary Team";
    
    mail($to, $subject, $message);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
