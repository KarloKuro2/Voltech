<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

// Update the last activity timestamp
$_SESSION['last_activity'] = time();

// Return success response
header('Content-Type: application/json');
echo json_encode(['status' => 'success', 'timestamp' => $_SESSION['last_activity']]);
exit();
?>
