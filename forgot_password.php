<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Initialize variables
$error_message = "";
$success_message = "";
$conn = null;

try {
    // Database connection
    $con = new mysqli("localhost", "root", "", "voltech2");
    if ($con->connect_error) {
        throw new Exception("Connection failed: " . $con->connect_error);
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
        $email = mysqli_real_escape_string($con, $_POST['email']);
        
        // Check if email exists
        $stmt = $con->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($user_id);
            $stmt->fetch();
            
            // Generate reset token
            $reset_token = bin2hex(random_bytes(16));
            
            // Set timezone for expiration
            $timezone = new DateTimeZone('Asia/Manila');
            $now = new DateTime('now', $timezone);
            $now->add(new DateInterval('PT1H')); // Add 1 hour
            $reset_expires = $now->format('Y-m-d H:i:s');
            
            // Log the generated expiration time for debugging
            error_log("Generated reset token for $email. Expires at: $reset_expires (Asia/Manila)");
            
            // Update user with reset token
            $update_stmt = $con->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $update_stmt->bind_param("ssi", $reset_token, $reset_expires, $user_id);
            
            if ($update_stmt->execute()) {
                // Send email with reset link
                $mail = new PHPMailer(true);
                try {
                    //Server settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'VoltechElectricalConstruction0@gmail.com';
                    $mail->Password = 'sban pumy bmia wwal';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    //Recipients
                    $mail->setFrom('VoltechElectricalConstruction0@gmail.com', 'VOLTECH');
                    $mail->addAddress($email);

                    // Content
$mail->isHTML(true);
$mail->Subject = 'Reset Your Password';
$reset_link = "http://voltechelectricalconstruction.com/reset_password_form.php?token=" . $reset_token;
$mail->Body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reset Your Password</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            line-height: 1.6; 
            color: #333; 
            margin: 0;
            padding: 0;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            padding: 20px; 
        }
        .header { 
            text-align: center; 
            padding: 20px 0; 
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }
        .logo { 
            max-width: 180px; 
            height: auto; 
            margin-bottom: 15px;
        }
        .content { 
            background-color: #ffffff; 
            padding: 30px; 
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #2c3e50;
            margin-top: 0;
        }
        .button {
            background-color: #007bff;
            color: white !important;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin: 20px 0;
            font-weight: bold;
            font-size: 16px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        .button-container {
            text-align: center;
            margin: 25px 0;
        }
        .expiry-note {
            color: #6c757d;
            font-size: 14px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <h2>Reset Your Password</h2>
            <p>We received a request to reset your password. If you didn\'t make this request, you can safely ignore this email.</p>
            <p>To reset your password, click the button below:</p>
            
            <div class="button-container">
                <a href="' . $reset_link . '" class="button">Reset Password</a>
            </div>
            
            <p class="expiry-note">This link will expire in 1 hour for security reasons.</p>
            
            <p>If the button above doesn\'t work, copy and paste this link into your browser:</p>
            <p><a href="' . $reset_link . '" style="word-break: break-all; color: #007bff; text-decoration: none;">' . $reset_link . '</a></p>
            
            <p>If you have any questions or need assistance, please contact our support team.</p>
            
            <div class="footer">
                <p>© ' . date('Y') . ' Voltech. All rights reserved.</p>
                <p>This is an automated message, please do not reply to this email.</p>
            </div>
        </div>
    </div>
</body>
</html>';

$mail->AltBody = "We received a request to reset your password.\n\n" .
               "Please click the following link to reset your password:\n" .
               $reset_link . "\n\n" .
               "This link will expire in 1 hour.\n\n" .
               "If you didn't request this, please ignore this email.";
                    $mail->send();
                    $success_message = "Password reset link has been sent to your email address.";
                } catch (Exception $e) {
                    $error_message = "Email could not be sent. Error: {$mail->ErrorInfo}";
                }
            } else {
                $error_message = "Error updating reset token.";
            }
            $update_stmt->close();
        } else {
            // For security reasons, don't reveal if email exists or not
            $success_message = "If your email exists in our system, you will receive a password reset link.";
        }
        $stmt->close();
    }
} catch (Exception $e) {
    $error_message = "An error occurred: " . $e->getMessage();
    error_log($e->getMessage());
} finally {
    // Only try to close the connection if it was successfully created
    if ($conn !== null) {
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password - Voltech</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #27ae60;
            --primary-dark: #219150;
            --secondary: #333333;
            --dark: #222222;
            --darker: #1a1a1a;
            --light: #ffffff;
            --error: #e74c3c;
            --success: #2ecc71;
            --accent: #f1c40f;
            --transition: all 0.3s ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            background-color: #444444;
            background-image: 
                radial-gradient(circle at 10% 20%, rgba(0, 0, 0, 0.2) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(0, 0, 0, 0.2) 0%, transparent 50%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
        }
        
        .forgot-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 450px;
            padding: 20px;
        }
        
        .forgot-panel {
            flex: 1;
            max-width: 100%;
            background: var(--dark);
            color: var(--light);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .forgot-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary), #2ecc71);
        }
        
        .forgot-panel h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--light);
        }
        
        .forgot-subtitle {
            color: #dddddd;
            margin-bottom: 30px;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        
        .form-control {
            height: 55px;
            background-color: var(--darker);
            border: 1px solid #444;
            border-radius: 10px;
            color: #ffffff;
            font-weight: 500;
            font-size: 1rem;
            padding: 10px 15px 10px 45px;
            transition: var(--transition);
        }
        
        .form-control::placeholder {
            color: #aaaaaa;
            opacity: 1;
        }
        
        .form-control:focus {
            background-color: var(--darker);
            border-color: var(--primary);
            color: var(--light);
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.2);
        }
        
        .form-icon {
            position: absolute;
            left: 15px;
            top: 17px;
            color: #aaaaaa;
            font-size: 1.1rem;
            transition: var(--transition);
        }
        
        .form-control:focus + .form-icon {
            color: var(--primary);
        }
        
        .btn-reset {
            height: 55px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            width: 100%;
            margin-bottom: 25px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-reset::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.2), transparent);
            transition: all 0.6s ease;
            z-index: -1;
        }
        
        .btn-reset:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn-reset:hover::before {
            left: 100%;
        }
        
        .error-msg {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--error);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            border-left: 4px solid var(--error);
            display: flex;
            align-items: center;
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }
        
        .success-msg {
            background-color: rgba(46, 204, 113, 0.1);
            color: var(--success);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            border-left: 4px solid var(--success);
            display: flex;
            align-items: center;
        }
        
        @keyframes shake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-3px); }
            40%, 60% { transform: translateX(3px); }
        }
        
        .error-msg i, .success-msg i {
            margin-right: 10px;
            font-size: 1rem;
        }
        
        .back-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
        }
        
        .back-link i {
            margin-right: 5px;
        }
        
        .back-link:hover {
            color: #fff;
            text-decoration: underline;
        }
        
        /* Responsive Design */
        @media (max-width: 480px) {
            .forgot-panel {
                padding: 30px 20px;
            }
            
            .forgot-panel h2 {
                font-size: 1.5rem;
            }
            
            .form-control {
                height: 50px;
                font-size: 0.95rem;
            }
            
            .btn-reset {
                height: 50px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-panel">
            <h2 class="text-center">Forgot Password</h2>
            <p class="forgot-subtitle text-center">Enter your email to receive a password reset link</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-msg">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-msg">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" action="forgot_password.php" id="forgotForm">
                <div class="form-group">
                    <input type="email" name="email" class="form-control" id="email" placeholder="Email Address" required autofocus>
                    <i class="fas fa-envelope form-icon"></i>
                </div>
                
                <button type="submit" class="btn-reset" id="resetBtn">
                    Send Reset Link <i class="fas fa-paper-plane ms-2"></i>
                </button>
                
                <div class="text-center">
                    <a href="login.php" class="back-link">
                        <i class="fas fa-arrow-left"></i> Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Loading state for reset button
            const forgotForm = document.getElementById('forgotForm');
            const resetBtn = document.getElementById('resetBtn');
            
            forgotForm.addEventListener('submit', function() {
                resetBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> Sending...';
                resetBtn.disabled = true;
            });
        });
    </script>
</body>
</html>