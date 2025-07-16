<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include("config.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the start of the verification process
error_log("Verification process started. Code: " . ($_GET['code'] ?? 'No code provided'));

$message = "";
$messageType = "";

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    error_log("Verification code received: " . $code);
    
    // First, fetch all matching emails and codes into an array
    $result = $con->query("SELECT id, firstname, email, verification_code FROM users WHERE is_verified = 0");
    if (!$result) {
        error_log("Database query failed: " . $con->error);
    }
    
    $users = [];
    $userFound = false;
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
        $result->close();
    }
    
    error_log("Found " . count($users) . " unverified users");
    
    // Now check each user without an active result set
    foreach ($users as $user) {
        error_log("Checking user: " . $user['email']);
        
        if (password_verify($code, $user['verification_code'])) {
            error_log("Verification code matches for user: " . $user['email']);
            
            // Verification success → Activate user
            $update_stmt = $con->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows > 0) {
                error_log("User account activated successfully");
                
                // Send welcome email
                $mail = new PHPMailer(true);
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'VoltechElectricalConstruction0@gmail.com';
                    $mail->Password = 'sban pumy bmia wwal';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->SMTPDebug = 2; // Enable verbose debug output
                    $mail->Debugoutput = function($str, $level) {
                        error_log("PHPMailer: $str");
                    };

                    // Recipients
                    $mail->setFrom('VoltechElectricalConstruction0@gmail.com', 'VOLTECH');
                    $mail->addAddress($user['email'], $user['firstname']);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Welcome to VOLTECH - Account Verified';
                    $mail->Body = "
                        <h2>Welcome to VOLTECH, {$user['firstname']}!</h2>
                        <p>Your account has been successfully verified and is now active.</p>
                        <p>You can now log in to your account and start using our services.</p>
                        <p>If you have any questions, feel free to contact our support team.</p>
                        <br>
                        <p>Best regards,<br>VOLTECH Team</p>
                    ";

                    if ($mail->send()) {
                        error_log("Welcome email sent successfully to: " . $user['email']);
                    } else {
                        error_log("Failed to send welcome email to: " . $user['email']);
                    }
                } catch (Exception $e) {
                    error_log("Error sending welcome email: " . $e->getMessage());
                }
                
                $message = "Email verified! You can now <a href='login.php'>log in</a>.";
                $messageType = "success";
                $userFound = true;
            } else {
                error_log("Failed to update user verification status");
                $message = "Error updating your account status. Please contact support.";
                $messageType = "error";
            }
            
            $update_stmt->close();
            break;
        } else {
            error_log("Verification code does not match for user: " . $user['email']);
        }
    }
    
    if (!$userFound) {
        error_log("No matching unverified user found for the provided code");
        $message = "Invalid verification link or your account is already verified. Please <a href='login.php'>log in</a> or <a href='resend_verification.php'>request a new verification link</a>.";
        $messageType = "error";
    }
} else {
    error_log("No verification code provided");
    $message = "Invalid verification link. Please <a href='login.php'>log in</a> or <a href='resend_verification.php'>request a new verification link</a>.";
    $messageType = "error";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Email Verification</title>
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #333; color: white; }
    .form-container { width: 340px; margin: 50px auto; background-color: #444; padding: 30px; border-radius: 5px; }
    .error-msg { color: red; text-align: center; margin-bottom: 10px; }
    .success-msg { color: green; text-align: center; margin-bottom: 10px; }
  </style>
</head>
<body>
  <div class="form-container">
    <h2 class="text-center">Email Verification</h2>
    
    <?php if ($messageType == "error"): ?>
      <div class="error-msg"><?= $message; ?></div>
    <?php elseif ($messageType == "success"): ?>
      <div class="success-msg"><?= $message; ?></div>
    <?php endif; ?>
    
    <p class="text-center">
      <a href="login.php" class="text-light">Back to Login</a>
      <?php if ($messageType == "error"): ?>
        | <a href="resend_verification.php" class="text-light">Resend Verification</a>
      <?php endif; ?>
    </p>
  </div>
</body>
</html>
