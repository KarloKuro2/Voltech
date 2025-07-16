<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include("config.php"); // Use the config file for database connection

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['email'])) {
    $email = mysqli_real_escape_string($con, $_POST['email']);
    
    // Check if the email exists and is not verified
    $check_stmt = $con->prepare("SELECT id FROM users WHERE email = ? AND is_verified = 0");
    $check_stmt->bind_param("s", $email);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $verification_code = bin2hex(random_bytes(32));
        $hashed_code = password_hash($verification_code, PASSWORD_BCRYPT);
        $expiry_time = date("Y-m-d H:i:s", strtotime("+15 minutes"));
        
        $check_stmt->close(); // Close the first statement before creating a new one
        
        $stmt = $con->prepare("UPDATE users SET verification_code = ?, verification_expires = ? WHERE email = ? AND is_verified = 0");
        $stmt->bind_param("sss", $hashed_code, $expiry_time, $email);

        if ($stmt->execute()) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'VoltechElectricalConstruction0@gmail.com';
                $mail->Password = 'sban pumy bmia wwal';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('VoltechElectricalConstruction0@gmail.com', 'VOLTECH');
                $mail->addAddress($email);
                $mail->Subject = "Resend Verification Email";
                $mail->Body = "Click the link to verify your email: http://localhost/Voltech2/verify.php?code=$verification_code";

                $mail->send();
                $success_message = "Verification email resent! Please check your inbox.";
            } catch (Exception $e) {
                $error_message = "Error sending email: " . $mail->ErrorInfo;
            }
        } else {
            $error_message = "Error updating verification code. Please try again.";
        }
        $stmt->close();
    } else {
        $error_message = "Email not found or already verified.";
        $check_stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resend Verification Email</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 50px;
        }
        .form-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .form-title {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h2 class="form-title">Resend Verification Email</h2>
            
            <?php if($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
                <div class="text-center mt-3">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php else: ?>
                <?php if($error_message): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Resend Verification Email</button>
                </form>
                
                <div class="text-center mt-3">
                    <a href="login.php">Back to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
