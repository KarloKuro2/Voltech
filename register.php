<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
$con = new mysqli("localhost", "root", "", "voltech2");
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

$error_message = "";
$success_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if all required fields exist
    if (isset($_POST['firstname']) && isset($_POST['lastname']) && isset($_POST['email']) && 
        isset($_POST['password']) && isset($_POST['confirm_password']) && isset($_POST['terms']) && isset($_POST['user_role'])) {
        
        $firstname = mysqli_real_escape_string($con, $_POST['firstname']);
        $lastname = mysqli_real_escape_string($con, $_POST['lastname']);
        $email = mysqli_real_escape_string($con, $_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $user_role = $_POST['user_role'];

        // Check if passwords match
        if ($password !== $confirm_password) {
            $error_message = "Passwords do not match!";
        } elseif (empty($user_role) || !in_array($user_role, [3, 4])) {
            $error_message = "Please select a valid account type";
        } else {
            // Check if email already exists
            $stmt_check = $con->prepare("SELECT email FROM users WHERE email = ?");
            $stmt_check->bind_param("s", $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error_message = "Email already exists! Please use a different email.";
            } else {
                // Securely hash password & generate verification code
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $verification_code = bin2hex(random_bytes(16));
                
                // Insert new user with default user level 3 (regular user)
                $stmt = $con->prepare("INSERT INTO users (firstname, lastname, email, password, verification_code, is_verified, user_level) VALUES (?, ?, ?, ?, ?, 0, ?)");
                $stmt->bind_param("sssssi", $firstname, $lastname, $email, $hashed_password, $verification_code, $user_role);
                
                if ($stmt->execute()) {
                    // Send verification email
                    $mail = new PHPMailer(true);
                    try {
                        //Server settings
                        $mail->isSMTP();
                        $mail->Host       = 'smtp.gmail.com';
                        $mail->SMTPAuth   = true;
                        $mail->Username   = 'your-email@gmail.com'; // Replace with your email
                        $mail->Password   = 'your-app-password';    // Replace with your app password
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port       = 587;

                        //Recipients
                        $mail->setFrom('your-email@gmail.com', 'Voltech System');
                        $mail->addAddress($email, $firstname . ' ' . $lastname);

                        // Content
                        $mail->isHTML(true);
                        $mail->Subject = 'Verify Your Email Address';
                        $verification_link = "http://localhost/Voltech2/verify.php?code=" . $verification_code;
                        $mail->Body = "Hello $firstname,<br><br>Please click the link below to verify your email address:<br><br>
                                      <a href='$verification_link'>Verify Email</a><br><br>
                                      If you didn't request this, you can ignore this email.<br><br>
                                      Thanks,<br>Voltech System";

                        $mail->send();
                        $success_message = "Registration successful! Please check your email to verify your account.";
                    } catch (Exception $e) {
                        // Update error message to indicate admin verification is needed
                        $success_message = "Your registration was successful. However, your account is pending verification and requires admin approval. Please wait for confirmation.";
                        // Log the actual error for debugging
                        error_log("Email sending failed during registration: " . $e->getMessage());
                    }
                } else {
                    $error_message = "Error: " . $stmt->error;
                }
                $stmt->close();
            }
            $stmt_check->close();
        }
    } else {
        $error_message = "All fields are required!";
    }
}
$con->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Voltech</title>
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
        
        .register-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 550px;
            padding: 20px;
        }
        
        .register-panel {
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
        
        .register-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary), #2ecc71);
        }
        
        .register-panel h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--light);
        }
        
        .register-subtitle {
            color: #dddddd;
            margin-bottom: 30px;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 20px;
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
        
        .row {
            margin: 0 -10px;
            display: flex;
            flex-wrap: wrap;
        }
        
        .col-md-6 {
            padding: 0 10px;
            flex: 0 0 50%;
            max-width: 50%;
        }
        
        .btn-register {
            height: 55px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            width: 100%;
            margin-top: 15px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn-register::before {
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
        
        .btn-register:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn-register:hover::before {
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
        
        .divider {
            display: flex;
            align-items: center;
            margin: 25px 0;
            color: #777;
            font-size: 0.9rem;
        }
        
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background-color: #444;
        }
        
        .divider span {
            padding: 0 15px;
        }
        
        .login-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .login-link:hover {
            color: #fff;
            text-decoration: underline;
        }
        
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 17px;
            color: #aaaaaa;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .toggle-password:hover {
            color: #fff;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .register-panel {
                padding: 30px 20px;
            }
            
            .register-panel h2 {
                font-size: 1.7rem;
            }
            
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
                margin-bottom: 15px;
            }
            
            .row {
                margin-bottom: 0;
            }
        }
        
        @media (max-width: 480px) {
            .register-panel {
                padding: 25px 15px;
            }
            
            .form-control {
                height: 50px;
                font-size: 0.95rem;
            }
            
            .btn-register {
                height: 50px;
                font-size: 1rem;
            }
        }
        
        /* Select styles */
        .form-group select.form-control {
            padding-left: 50px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-color: var(--darker);
            color: var(--light);
            border: 1px solid #444;
            border-radius: 8px;
            height: 55px;
            width: 100%;
            font-size: 1rem;
            cursor: pointer;
        }

        .form-group select.form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(39, 174, 96, 0.25);
            outline: none;
        }
        
        .form-group select.form-control:required:invalid {
            color: #6c757d; /* Same as other placeholders */
        }

        .form-group select.form-control option:not(:disabled) {
            color: var(--light); /* Normal text color for selectable options */
        }
        
        /* Mobile view enhancements for terms and privacy modal */
        @media (max-width: 767.98px) {
            .modal-dialog {
                margin: 0.5rem;
                max-width: 100%;
            }
            
            .modal-content {
                border-radius: 12px;
                min-height: 90vh;
                max-height: 90vh;
                display: flex;
                flex-direction: column;
            }
            
            .modal-body {
                padding: 0;
                overflow: hidden;
                flex: 1;
            }
            
            .modal-body .d-flex {
                flex-direction: column;
                height: 100%;
            }
            
            .modal-body .bg-darker {
                width: 100% !important;
                border-right: none !important;
                border-bottom: 1px solid #444;
                padding: 1rem !important;
            }
            
            .modal-body .bg-darker .nav {
                flex-direction: row !important;
                overflow-x: auto;
                white-space: nowrap;
                padding-bottom: 0.5rem;
                margin-bottom: -0.5rem;
            }
            
            .modal-body .bg-darker .nav-item {
                margin-right: 1rem;
            }
            
            .modal-body .bg-darker .nav-link {
                padding: 0.5rem 1rem;
                border-radius: 20px;
                white-space: nowrap;
                margin-bottom: 0;
            }
            
            .modal-body .tab-content {
                height: calc(100% - 60px);
                overflow-y: auto;
            }
            
            .tab-pane {
                padding: 1.25rem !important;
            }
            
            .modal-footer {
                padding: 1rem;
                border-top: 1px solid #444;
            }
            
            .modal-footer .btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
        
        /* Add smooth scrolling for better mobile experience */
        .tab-content {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Style the scrollbar for better mobile experience */
        .tab-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .tab-content::-webkit-scrollbar-track {
            background: #333;
        }
        
        .tab-content::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 3px;
        }
        
        .tab-content::-webkit-scrollbar-thumb:hover {
            background: #666;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 767.98px) {
            /* Reduce gap between form groups */
            .form-group {
                margin-bottom: 12px;
            }
            
            /* Make name fields have less bottom margin */
            #firstname, #lastname {
                margin-bottom: 5px;
            }
            
            /* Make all form controls consistent height */
            .form-control, .form-select {
                height: 46px;
                padding: 8px 12px 8px 40px;
                font-size: 0.95rem;
            }
            
            /* Specific adjustments for select dropdown */
            .form-group select.form-control {
                height: 46px;
                padding-left: 40px;
                background-position: right 0.75rem center;
                padding-right: 2rem;
            }
            
            /* Adjust icon positioning */
            .form-icon {
                top: 13px;
                left: 12px;
                font-size: 0.95rem;
            }
            
            /* Reduce space between form rows */
            .row {
                margin: 0 -6px;
            }
            
            .col-md-6 {
                padding: 0 6px;
            }
        }
        
        /* Extra small devices */
        @media (max-width: 400px) {
            .form-group {
                margin-bottom: 10px;
            }
            
            #firstname, #lastname {
                margin-bottom: 4px;
            }
            
            .form-control, .form-select, 
            .form-group select.form-control {
                height: 44px;
                padding: 6px 10px 6px 38px;
                font-size: 0.9rem;
            }
            
            .form-icon {
                left: 10px;
                top: 12px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-panel">
            <h2 class="text-center">Create Account</h2>
            <p class="register-subtitle text-center">Join Voltech Electrical Construction</p>
            
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
            
            <form method="post" action="register.php" id="registerForm">
                <div class="form-group">
                    <select class="form-control" id="user_role" name="user_role" required>
                        <option value="" selected disabled>Select account type</option>
                        <option value="3">Project Manager</option>
                        <option value="4">Procurement Officer</option>
                    </select>
                    <i class="fas fa-user-tag form-icon"></i>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <input type="text" name="firstname" class="form-control" id="firstname" placeholder="First Name" required value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>">
                            <i class="fas fa-user form-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <input type="text" name="lastname" class="form-control" id="lastname" placeholder="Last Name" required value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>">
                            <i class="fas fa-user form-icon"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <input type="email" name="email" class="form-control" id="email" placeholder="Email Address" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <i class="fas fa-envelope form-icon"></i>
                </div>
                
                <div class="form-group">
                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                    <i class="fas fa-lock form-icon"></i>
                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                </div>
                
                <div class="form-group">
                    <input type="password" name="confirm_password" class="form-control" id="confirm_password" placeholder="Confirm Password" required>
                    <i class="fas fa-lock form-icon"></i>
                    <i class="fas fa-eye toggle-password" id="toggleConfirmPassword"></i>
                </div>
                
                <div class="form-group form-check mb-4">
                    <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#termsModal" data-type="terms">Terms & Conditions</a> and 
                        <a href="#" class="text-primary" data-bs-toggle="modal" data-bs-target="#termsModal" data-type="privacy">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn-register" id="registerBtn">
                    Register <i class="fas fa-user-plus ms-2"></i>
                </button>
                
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <p class="text-center" style="color: #fff;">
                    Already have an account? <a href="login.php" class="login-link">Sign In</a>
                </p>
            </form>
        </div>
    </div>
    
    <!-- Terms and Privacy Policy Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" role="dialog" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content bg-dark text-light" style="border: 1px solid var(--primary);">
                <div class="modal-header border-secondary position-relative">
                    <h2 class="modal-title h5 d-flex align-items-center" id="termsModalLabel">
                        <i class="fas fa-file-contract me-2" id="modalIcon"></i>
                        <span id="modalTitle">Terms & Conditions</span>
                    </h2>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="d-flex flex-column flex-md-row" style="height: 60vh;">
                        <!-- Sidebar Navigation -->
                        <div class="bg-darker p-3" style="width: 200px; border-right: 1px solid #444;">
                            <ul class="nav nav-pills flex-column" id="termsTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active d-flex align-items-center" id="terms-tab" data-bs-toggle="pill" href="#terms-content" role="tab">
                                        <i class="fas fa-file-alt me-2"></i><span>Terms</span>
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link d-flex align-items-center" id="privacy-tab" data-bs-toggle="pill" href="#privacy-content" role="tab">
                                        <i class="fas fa-shield-alt me-2"></i><span>Privacy</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        
                        <div class="tab-content flex-grow-1" style="overflow-y: auto;">
                            <!-- Terms Content -->
                            <div class="tab-pane fade show active p-4" id="terms-content" role="tabpanel">
                                <h4 class="mb-4 text-primary">Terms & Conditions</h4>
                                <p class="text-muted small mb-4">Last updated: May 25, 2025</p>
                                
                                <div class="terms-content">
                                    <div class="mb-4">
                                        <h5 class="d-flex align-items-center">
                                            <i class="fas fa-check-circle text-success me-2"></i>
                                            <span>1. Acceptance of Terms</span>
                                        </h5>
                                        <p class="ms-4 mt-2">By accessing and using Voltech's services, you accept and agree to be bound by these Terms and Conditions. Your use of our services constitutes your agreement to all such terms, conditions, and notices.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5 class="d-flex align-items-center">
                                            <i class="fas fa-user-shield text-info me-2"></i>
                                            <span>2. User Account</span>
                                        </h5>
                                        <p class="ms-4 mt-2">You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You agree to immediately notify us of any unauthorized use of your account or any other security breaches.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5 class="d-flex align-items-center">
                                            <i class="fas fa-ban text-danger me-2"></i>
                                            <span>3. Prohibited Uses</span>
                                        </h5>
                                        <p class="ms-4 mt-2">You may not use our services for any illegal or unauthorized purpose. This includes, but is not limited to, violating any laws, infringing on intellectual property rights, or transmitting harmful code.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5 class="d-flex align-items-center">
                                            <i class="fas fa-sync-alt text-warning me-2"></i>
                                            <span>4. Changes to Terms</span>
                                        </h5>
                                        <p class="ms-4 mt-2">We reserve the right to modify these terms at any time. We will notify you of any changes by updating the "Last updated" date. Your continued use of the service after such modifications constitutes your acceptance of the revised terms.</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Privacy Content -->
                            <div class="tab-pane fade p-4" id="privacy-content" role="tabpanel">
                                <h4 class="mb-4 text-primary">Privacy Policy</h4>
                                <p class="text-muted small mb-4">Last updated: May 25, 2025</p>
                                
                                <div class="privacy-content">
                                    <div class="mb-4">
                                        <h5 class="d-flex align-items-center">
                                            <i class="fas fa-database text-info me-2"></i>
                                            <span>1. Information We Collect</span>
                                        </h5>
                                        <p class="ms-4 mt-2">We collect personal information such as name, email, contact details, and usage data when you register or use our services. This includes information you provide directly and data collected automatically through cookies and similar technologies.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5 class="d-flex align-items-center">
                                            <i class="fas fa-cogs text-primary me-2"></i>
                                            <span>2. How We Use Your Information</span>
                                        </h5>
                                        <p class="ms-4 mt-2">We use your information to provide, maintain, and improve our services, process transactions, communicate with you, and ensure the security of our platform. We may also use aggregated data for analytics and research purposes.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5 class="d-flex align-items-center">
                                            <i class="fas fa-lock text-success me-2"></i>
                                            <span>3. Data Security</span>
                                        </h5>
                                        <p class="ms-4 mt-2">We implement industry-standard security measures to protect your personal information. This includes encryption, access controls, and regular security audits. However, no method of transmission over the internet is 100% secure.</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <h5 class="d-flex align-items-center">
                                            <i class="fas fa-user-edit text-warning me-2"></i>
                                            <span>4. Your Rights</span>
                                        </h5>
                                        <p class="ms-4 mt-2">You have the right to access, correct, or delete your personal information at any time. You may also object to or restrict certain processing activities. To exercise these rights, please contact us using the information provided in our contact section.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary d-flex flex-column flex-md-row justify-content-between align-items-center">
                    <div class="form-check form-check-inline align-items-center mb-2 mb-md-0">
                        <input class="form-check-input" type="checkbox" id="agreeCheckbox">
                        <label class="form-check-label small ms-2" for="agreeCheckbox">I have read and agree to the terms</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="confirmAgree" disabled>I Agree</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Modal elements
            const termsModal = document.getElementById('termsModal');
            const modal = new bootstrap.Modal(termsModal);
            const modalTitle = document.getElementById('modalTitle');
            const modalIcon = document.getElementById('modalIcon');
            const acceptBtn = document.getElementById('confirmAgree');
            const agreeCheckbox = document.getElementById('agreeCheckbox');
            const termsCheckbox = document.getElementById('terms');
            
            // Track if user has viewed both tabs
            let hasViewedTerms = false;
            let hasViewedPrivacy = false;
            
            // Tab change handler
            const tabElms = document.querySelectorAll('a[data-bs-toggle="pill"]');
            tabElms.forEach(tabEl => {
                tabEl.addEventListener('shown.bs.tab', function (event) {
                    const target = event.target.getAttribute('href');
                    if (target === '#terms-content') {
                        hasViewedTerms = true;
                        modalTitle.textContent = 'Terms & Conditions';
                        modalIcon.className = 'fas fa-file-contract me-2';
                    }
                    if (target === '#privacy-content') {
                        hasViewedPrivacy = true;
                        modalTitle.textContent = 'Privacy Policy';
                        modalIcon.className = 'fas fa-shield-alt me-2';
                    }
                    
                    // Update accept button state
                    updateAcceptButtonState();
                });
            });
            
            // Update accept button state based on checkbox and tabs viewed
            function updateAcceptButtonState() {
                const allRead = hasViewedTerms && hasViewedPrivacy;
                agreeCheckbox.checked = allRead;
                acceptBtn.disabled = !allRead;
            }
            
            // Agree checkbox change
            agreeCheckbox.addEventListener('change', function() {
                acceptBtn.disabled = !this.checked;
            });
            
            // Accept button click
            acceptBtn.addEventListener('click', function() {
                if (!agreeCheckbox.checked) return;
                
                termsCheckbox.checked = true;
                modal.hide();
            });
            
            // Modal show event
            termsModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const type = button.getAttribute('data-type');
                
                // Remove aria-hidden when modal is shown
                termsModal.removeAttribute('aria-hidden');
                
                if (type === 'terms') {
                    // Show terms tab
                    const termsTab = new bootstrap.Tab(document.getElementById('terms-tab'));
                    termsTab.show();
                    modalTitle.textContent = 'Terms & Conditions';
                    modalIcon.className = 'fas fa-file-contract me-2';
                } else if (type === 'privacy') {
                    // Show privacy tab
                    const privacyTab = new bootstrap.Tab(document.getElementById('privacy-tab'));
                    privacyTab.show();
                    modalTitle.textContent = 'Privacy Policy';
                    modalIcon.className = 'fas fa-shield-alt me-2';
                }
                
                // Reset states
                hasViewedTerms = false;
                hasViewedPrivacy = false;
                agreeCheckbox.checked = false;
                acceptBtn.disabled = true;
            });
            
            // Set initial focus when modal is shown
            termsModal.addEventListener('shown.bs.modal', function() {
                // Set focus to the modal dialog
                const modalDialog = termsModal.querySelector('.modal-dialog');
                if (modalDialog) {
                    modalDialog.setAttribute('role', 'document');
                    modalDialog.setAttribute('aria-modal', 'true');
                }
                
                // Set focus to the first focusable element in the modal
                const firstFocusable = termsModal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (firstFocusable) {
                    firstFocusable.focus();
                }
            });
            
            // Handle modal hidden event
            termsModal.addEventListener('hidden.bs.modal', function () {
                // Clean up attributes when modal is hidden
                const modalDialog = termsModal.querySelector('.modal-dialog');
                if (modalDialog) {
                    modalDialog.removeAttribute('aria-modal');
                }
                
                // Return focus to the button that opened the modal
                const openButton = document.querySelector('[data-bs-toggle="modal"][data-bs-target="#termsModal"]');
                if (openButton) {
                    openButton.focus();
                }
            });
            
            // Handle tab key navigation within modal
            termsModal.addEventListener('keydown', function(e) {
                // Only handle tab key events when modal is shown
                if (!termsModal.classList.contains('show')) return;
                
                if (e.key === 'Tab') {
                    const focusableElements = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
                    const focusableContent = termsModal.querySelectorAll(focusableElements);
                    const firstFocusableElement = focusableContent[0];
                    const lastFocusableElement = focusableContent[focusableContent.length - 1];
                    
                    // If no focusable elements, prevent tabbing
                    if (focusableContent.length === 0) {
                        e.preventDefault();
                        return;
                    }
                    
                    // Handle shift + tab
                    if (e.shiftKey) {
                        if (document.activeElement === firstFocusableElement) {
                            lastFocusableElement.focus();
                            e.preventDefault();
                        }
                    } else { // Handle tab
                        if (document.activeElement === lastFocusableElement) {
                            firstFocusableElement.focus();
                            e.preventDefault();
                        }
                    }
                }
                
                // Close modal on Escape key
                if (e.key === 'Escape') {
                    modal.hide();
                }
            });
            
            // Password visibility toggle
            const togglePassword = document.getElementById('togglePassword');
            const toggleConfirmPassword = document.getElementById('toggleConfirmPassword');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            togglePassword.addEventListener('click', function() {
                const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                password.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            toggleConfirmPassword.addEventListener('click', function() {
                const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPassword.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
            
            // Password match validation
            confirmPassword.addEventListener('input', function() {
                if (password.value !== this.value) {
                    this.setCustomValidity("Passwords don't match");
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Loading state for register button
            const registerForm = document.getElementById('registerForm');
            const registerBtn = document.getElementById('registerBtn');
            
            registerForm.addEventListener('submit', function() {
                registerBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin me-2"></i> Registering...';
                registerBtn.disabled = true;
            });
        });
    </script>
</body>
</html>