<?php
// Database Connection
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Set timezone
date_default_timezone_set('Asia/Manila');
$con->query("SET time_zone = '+08:00'"); // Ensure database uses the same timezone

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "";
$message_type = "";
$token = "";

// Debug logging
error_log("=== New Password Reset Request ===");
$timezone = new DateTimeZone('Asia/Manila');
$current_time = new DateTime('now', $timezone);
error_log("Current server time (Asia/Manila): " . $current_time->format('Y-m-d H:i:s'));

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    error_log("Processing token from URL: " . $token);
    
    // Get current time in the correct timezone
    $current_time = new DateTime('now', $timezone);
    
    // Get the token details with timezone conversion
    $stmt = $con->prepare("SELECT email, reset_expires, 
                         CONVERT_TZ(reset_expires, @@session.time_zone, '+08:00') as local_reset_expires 
                         FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        error_log("No matching token found in database");
        $message = "Invalid or expired reset token. Please request a new password reset link.";
        $message_type = "error";
    } else {
        $row = $result->fetch_assoc();
        
        // Log the raw and converted times for debugging
        error_log("Raw reset_expires from DB: " . $row['reset_expires']);
        error_log("Converted reset_expires: " . $row['local_reset_expires']);
        
        // Create DateTime objects for comparison
        $expiry_time = new DateTime($row['local_reset_expires'], $timezone);
        
        error_log("Token found for email: " . $row['email']);
        error_log("Token expiry time (Asia/Manila): " . $expiry_time->format('Y-m-d H:i:s'));
        error_log("Current time (Asia/Manila): " . $current_time->format('Y-m-d H:i:s'));
        
        // Add a 5-minute grace period to account for any clock skew
        $grace_period = new DateInterval('PT5M');
        $expiry_time_with_grace = clone $expiry_time;
        $expiry_time_with_grace->add($grace_period);
        
        if ($expiry_time_with_grace < $current_time) {
            error_log("Token has expired (with grace period). Expiry: " . $expiry_time->format('Y-m-d H:i:s') . ", Current: " . $current_time->format('Y-m-d H:i:s'));
            $message = "This reset link has expired. Please request a new one.";
            $message_type = "error";
        } else {
            error_log("Token is valid");
            // Token is valid, continue with the form
        }
    }
    $stmt->close();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reset_token = $_POST['reset_token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in all fields.";
        $message_type = "error";
    } 
    elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match!";
        $message_type = "error";
    }
    elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $message_type = "error";
    }
    elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $message = "Password must contain at least one uppercase letter and one number.";
        $message_type = "error";
    }
    else {
        // Get current time for verification
        $current_time = new DateTime('now', $timezone);
        $current_time_str = $current_time->format('Y-m-d H:i:s');
        
        // Verify the token is still valid
        $stmt = $con->prepare("SELECT email FROM users WHERE reset_token = ? AND CONVERT_TZ(reset_expires, @@session.time_zone, '+08:00') > ?");
        $stmt->bind_param("ss", $reset_token, $current_time_str);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $email = $row['email'];
            
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update the password and clear the reset token
            $update = $con->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE email = ?");
            if ($update->bind_param("ss", $hashed_password, $email) && $update->execute()) {
                $message = "Your password has been reset successfully! You can now <a href='login.php' class='text-success'>login</a> with your new password.";
                $message_type = "success";
                $token = ""; // Clear the token to hide the form
                $_POST = array(); // Clear the form
            } else {
                $message = "Error updating password. Please try again.";
                $message_type = "error";
            }
            $update->close();
        } else {
            $message = "Invalid or expired reset token. Please request a new password reset link.";
            $message_type = "error";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Voltech</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #27ae60;
            --primary-dark: #219150;
            --secondary: #333333;
            --dark: #222222;
            --darker: #1a1a1a;
            --light: #f0f0f0;
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
                radial-gradient(circle at 10% 20%, rgba(0, 0, 0, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 90% 80%, rgba(0, 0, 0, 0.3) 0%, transparent 50%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow-x: hidden;
            color: var(--light);
            padding: 20px;
        }
        
        .reset-container {
            width: 100%;
            max-width: 500px;
        }
        
        .reset-panel {
            background: var(--dark);
            color: var(--light);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .reset-panel h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--light);
            text-align: center;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .reset-subtitle {
            color: #cccccc;
            margin-bottom: 20px;
            font-size: 1rem;
            font-weight: 500;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 15px;
            position: relative;
        }
        
        .form-control {
            height: 50px;
            background-color: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #ffffff;
            font-weight: 500;
            font-size: 1rem;
            padding: 10px 15px 10px 45px;
            transition: var(--transition);
            width: 100%;
        }
        
        .form-control:focus {
            background-color: rgba(255,255,255,0.08);
            border-color: var(--primary);
            color: #ffffff;
            box-shadow: 0 0 0 3px rgba(39, 174, 96, 0.2);
            outline: none;
        }
        
        .form-control::placeholder {
            color: #aaaaaa;
            opacity: 1;
        }
        
        .form-icon {
            position: absolute;
            left: 15px;
            top: 15px;
            color: #aaaaaa;
            font-size: 1.1rem;
            transition: var(--transition);
        }
        
        .form-control:focus + .form-icon {
            color: var(--primary);
        }
        
        .btn-reset {
            height: 50px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            width: 100%;
            margin: 15px 0;
            transition: var(--transition);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2);
        }
        
        .btn-reset:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-reset:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .error-msg, .success-msg {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            border-left: 4px solid transparent;
        }
        
        .error-msg {
            background-color: rgba(231, 76, 60, 0.15);
            color: var(--error);
            border-left-color: var(--error);
        }
        
        .success-msg {
            background-color: rgba(46, 204, 113, 0.15);
            color: var(--success);
            border-left-color: var(--success);
        }
        
        .success-msg a {
            color: #fff;
            text-decoration: underline;
            font-weight: 600;
            margin-left: 5px;
        }
        
        .success-msg a:hover {
            text-decoration: none;
        }
        
        .back-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            font-size: 0.95rem;
        }
        
        .back-link i {
            margin-right: 5px;
        }
        
        .back-link:hover {
            color: #fff;
            text-decoration: underline;
        }
        
        /* Password strength meter */
        .password-strength {
            height: 4px;
            background: rgba(255,255,255,0.1);
            border-radius: 2px;
            margin: 8px 0 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0;
            background: #e74c3c;
            transition: width 0.3s ease, background 0.3s ease;
        }
        
        .password-requirements {
            color: #bbbbbb;
            font-size: 0.8rem;
            margin: 5px 0 0;
            line-height: 1.4;
        }
        
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 3px;
        }
        
        .requirement i {
            margin-right: 5px;
            font-size: 0.7rem;
            width: 12px;
            text-align: center;
        }
        
        .requirement.valid {
            color: #2ecc71;
        }
        
        .password-match {
            font-size: 0.85rem;
            margin: 5px 0 0;
            min-height: 20px;
            display: flex;
            align-items: center;
            color: #bbbbbb;
        }
        
        .password-match i {
            margin-right: 5px;
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-left: 10px;
            vertical-align: middle;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive adjustments */
        @media (max-width: 576px) {
            .reset-panel {
                padding: 25px 20px;
            }
            
            .reset-panel h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-panel">
            <h2>Reset Your Password</h2>
            <p class="reset-subtitle">Enter your new password below</p>
            
            <?php if (!empty($message)): ?>
                <div class="<?php echo $message_type === 'error' ? 'error-msg' : 'success-msg'; ?>">
                    <i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                    <span style="margin-left: 10px;"><?php echo $message; ?></span>
                </div>
            <?php endif; ?>
            
            <?php if (empty($token) && $message_type !== 'success'): ?>
                
            <?php elseif ($message_type !== 'success' && $message_type !== 'error'): ?>
                <form id="resetForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="reset_token" value="<?php echo htmlspecialchars($token ?? ''); ?>">
                    
                    <div class="form-group">
                        <input type="password" 
                               name="new_password" 
                               id="new_password" 
                               class="form-control" 
                               placeholder="New Password" 
                               required 
                               minlength="8"
                               autocomplete="new-password">
                        <i class="fas fa-lock form-icon"></i>
                        <div class="password-strength">
                            <div class="password-strength-bar" id="passwordStrengthBar"></div>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement" id="length-req">
                                <i class="fas fa-circle"></i>
                                <span>At least 8 characters</span>
                            </div>
                            <div class="requirement" id="uppercase-req">
                                <i class="fas fa-circle"></i>
                                <span>At least 1 uppercase letter</span>
                            </div>
                            <div class="requirement" id="number-req">
                                <i class="fas fa-circle"></i>
                                <span>At least 1 number</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <input type="password" 
                               name="confirm_password" 
                               id="confirm_password" 
                               class="form-control" 
                               placeholder="Confirm New Password" 
                               required 
                               minlength="8"
                               autocomplete="new-password">
                        <i class="fas fa-lock form-icon"></i>
                        <div id="passwordMatch" class="password-match">
                            <i class="fas fa-info-circle"></i>
                            <span>Passwords must match</span>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-reset" id="resetButton">
                        <span id="buttonText">Reset Password</span>
                        <div class="spinner" id="spinner" style="display: none;"></div>
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="text-center">
                <a href="login.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resetForm');
            if (!form) return;

            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordMatch = document.getElementById('passwordMatch');
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            const lengthReq = document.getElementById('length-req');
            const uppercaseReq = document.getElementById('uppercase-req');
            const numberReq = document.getElementById('number-req');
            const resetButton = document.getElementById('resetButton');
            const buttonText = document.getElementById('buttonText');
            const spinner = document.getElementById('spinner');

            function updateRequirements(password) {
                // Update length requirement
                if (password.length >= 8) {
                    lengthReq.classList.add('valid');
                    lengthReq.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    lengthReq.classList.remove('valid');
                    lengthReq.querySelector('i').className = 'fas fa-circle';
                }
                
                // Update uppercase requirement
                if (/[A-Z]/.test(password)) {
                    uppercaseReq.classList.add('valid');
                    uppercaseReq.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    uppercaseReq.classList.remove('valid');
                    uppercaseReq.querySelector('i').className = 'fas fa-circle';
                }
                
                // Update number requirement
                if (/\d/.test(password)) {
                    numberReq.classList.add('valid');
                    numberReq.querySelector('i').className = 'fas fa-check-circle';
                } else {
                    numberReq.classList.remove('valid');
                    numberReq.querySelector('i').className = 'fas fa-circle';
                }
            }
            
            function checkPasswordMatch() {
                if (!confirmPassword.value) {
                    passwordMatch.innerHTML = '<i class="fas fa-info-circle"></i><span>Passwords must match</span>';
                    passwordMatch.style.color = '#bbbbbb';
                    return false;
                } else if (newPassword.value !== confirmPassword.value) {
                    passwordMatch.innerHTML = '<i class="fas fa-times-circle"></i><span>Passwords do not match</span>';
                    passwordMatch.style.color = '#e74c3c';
                    return false;
                } else {
                    passwordMatch.innerHTML = '<i class="fas fa-check-circle"></i><span>Passwords match</span>';
                    passwordMatch.style.color = '#2ecc71';
                    return true;
                }
            }
            
            function updateStrengthBar(password) {
                let strength = 0;
                if (password.length >= 8) strength += 1;
                if (/[A-Z]/.test(password)) strength += 1;
                if (/\d/.test(password)) strength += 1;
                if (/[^A-Za-z0-9]/.test(password)) strength += 1;
                
                const width = (strength / 4) * 100;
                if (passwordStrengthBar) {
                    passwordStrengthBar.style.width = width + '%';
                    
                    if (strength <= 1) {
                        passwordStrengthBar.style.background = '#e74c3c'; // Red
                    } else if (strength <= 2) {
                        passwordStrengthBar.style.background = '#f39c12'; // Orange
                    } else if (strength <= 3) {
                        passwordStrengthBar.style.background = '#3498db'; // Blue
                    } else {
                        passwordStrengthBar.style.background = '#2ecc71'; // Green
                    }
                }
            }
            
            if (newPassword && confirmPassword) {
                // Password strength indicator and requirements
                newPassword.addEventListener('input', function() {
                    const password = this.value;
                    updateRequirements(password);
                    updateStrengthBar(password);
                    checkPasswordMatch();
                });
                
                // Password match checker
                confirmPassword.addEventListener('input', checkPasswordMatch);
                
                // Form submission
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const password = newPassword.value;
                    const confirm = confirmPassword.value;
                    
                    // Basic validation
                    if (password !== confirm) {
                        checkPasswordMatch();
                        confirmPassword.focus();
                        return;
                    }
                    
                    if (password.length < 8 || !/[A-Z]/.test(password) || !/\d/.test(password)) {
                        alert('Please ensure your password meets all requirements.');
                        return;
                    }
                    
                    // Show loading state
                    resetButton.disabled = true;
                    buttonText.textContent = 'Resetting...';
                    spinner.style.display = 'inline-block';
                    
                    // Submit the form
                    form.submit();
                });
            }
        });
    </script>
</body>
</html>