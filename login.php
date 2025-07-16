<?php
session_start();
try {
    $con = new mysqli("localhost", "root", "", "voltech2");
    
    if ($con->connect_error) {
        throw new Exception("Connection failed: " . $con->connect_error);
    }
} catch (Exception $e) {
    $errormsg = "Database connection error: " . $e->getMessage();
    $con = null;
}

$errormsg = $errormsg ?? ""; // Ensure it's always defined
$timeout = isset($_GET['timeout']) ? true : false;

if ($con && $_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $con->prepare("SELECT id, password, is_verified, user_level, firstname, lastname FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {  
        $stmt->bind_result($user_id, $hashed_password, $is_verified, $user_level, $firstname, $lastname);
        $stmt->fetch();  

        if ($is_verified == 0) {
            $errormsg = "Please verify your email before logging in.";
        } elseif (password_verify($password, $hashed_password)) {
            $_SESSION['email'] = $email;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_level'] = $user_level;
            $_SESSION['firstname'] = $firstname;
            $_SESSION['lastname'] = $lastname;
            $_SESSION['name'] = $firstname . ' ' . $lastname;
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time(); // Initialize last activity time

            if (!empty($_POST['remember'])) {
                setcookie('user_email', $email, time() + (86400 * 30), "/");
                setcookie('remember_token', '1', time() + (86400 * 30), "/");
            } else {
                setcookie('user_email', '', time() - 3600, "/");
                setcookie('remember_token', '', time() - 3600, "/");
            }

            switch ($user_level) {
                case 1:
                    header("Location: super_admin_dashboard.php");
                    break;
                case 2:
                    header("Location: p_admin/admin_dashboard.php");
                    break;
                case 3:
                    header("Location: projectmanager/pm_dashboard.php");
                    break;
                case 4:
                    header("Location: procurementofficer/po_dashboard.php");
                    break;
                default:
                    header("Location: projectmanager/pm_dashboard.ph");
                    break;
            }
            exit();
        } else {
            $errormsg = "Invalid email or password.";
        }
    } else {
        $errormsg = "Invalid email or password.";
    }
    if (isset($stmt)) {
        $stmt->close();
    }
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for session timeout
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 1800) {
    session_unset();
    session_destroy();
    header("Location: login.php?timeout=1");
    exit();
} else {
    $_SESSION['last_activity'] = time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voltech Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            max-width: 1200px;
            padding: 20px;
        }
        
        .logo-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-right: 40px;
            animation: fadeInLeft 0.8s ease-out;
        }
        
        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translateX(-40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .logo-section img {
            max-width: 300px;
            filter: drop-shadow(0 5px 15px rgba(0,0,0,0.3));
            transition: var(--transition);
        }
        
        .logo-section img:hover {
            transform: scale(1.05);
            filter: drop-shadow(0 8px 20px rgba(0,0,0,0.4));
        }
        
        .logo-title {
            color: var(--light);
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: 3px;
            margin-top: 30px;
            text-align: center;
            text-transform: uppercase;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            line-height: 1.3;
        }
        
        .login-panel {
            flex: 1;
            max-width: 420px;
            background: var(--dark);
            color: var(--light);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.4);
            position: relative;
            overflow: hidden;
            animation: fadeInRight 0.8s ease-out;
        }
        
        @keyframes fadeInRight {
            from {
                opacity: 0;
                transform: translateX(40px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .login-panel::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary), #2ecc71);
        }
        
        .login-panel h2 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--light);
        }
        
        .login-subtitle {
            color: #ffffff;
            margin-bottom: 30px;
            font-size: 1rem;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 24px;
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
        
        .form-check {
            display: flex;
            align-items: center;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            background-color: var(--darker);
            border: 1px solid #555;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .form-check-label {
            font-size: 0.95rem;
            color: #ffffff;
            font-weight: 500;
        }
        
        .btn-login {
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
        
        .btn-login::before {
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
        
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .btn-login:hover::before {
            left: 100%;
        }
        
        .error-msg {
            background-color: rgba(231, 76, 60, 0.1);
            color: var(--error);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 0.95rem;
            font-weight: 500;
            border-left: 4px solid var(--error);
            display: flex;
            align-items: center;
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }
        
        @keyframes shake {
            10%, 90% { transform: translateX(-1px); }
            20%, 80% { transform: translateX(2px); }
            30%, 50%, 70% { transform: translateX(-3px); }
            40%, 60% { transform: translateX(3px); }
        }
        
        .error-msg i {
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
        
        .forgot-link {
            color: #aaaaaa;
            text-decoration: none;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        
        .forgot-link:hover {
            color: var(--accent);
        }
        
        .register-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .register-link:hover {
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
        @media (max-width: 992px) {
            .login-container {
                flex-direction: column;
                padding: 30px 15px;
            }
            
            .logo-section {
                flex-direction: row;
                margin-right: 0;
                margin-bottom: 40px;
                width: 100%;
                justify-content: space-between;
                align-items: center;
            }
            
            .logo-title {
                font-size: 2rem;
                text-align: right;
                margin-top: 0;
                margin-left: 20px;
            }
            
            .login-panel {
                width: 100%;
                max-width: 450px;
            }
        }
        
        @media (max-width: 768px) {
            .logo-section {
                margin-bottom: 30px;
            }
            
            .logo-section img {
                max-width: 180px;
            }
            
            .logo-title {
                font-size: 1.8rem;
                letter-spacing: 1px;
            }
        }
        
        @media (max-width: 576px) {
            .logo-section {
                margin-bottom: 25px;
            }
            
            .logo-section img {
                max-width: 140px;
            }
            
            .logo-title {
                font-size: 1.5rem;
                letter-spacing: 0.5px;
                margin-left: 15px;
            }
            
            .login-panel {
                padding: 30px 20px;
            }
            
            .login-panel h2 {
                font-size: 1.7rem;
            }
        }
        
        @media (max-width: 400px) {
            .logo-section img {
                max-width: 120px;
            }
            
            .logo-title {
                font-size: 1.3rem;
                letter-spacing: 0;
                margin-left: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo-section">
            <img src="uploads/voltech_logo_transparent.png" alt="Voltech Logo">
            <div class="logo-title">
                VOLTECH<br>
                ELECTRICAL<br>
                CONSTRUCTION
            </div>
        </div>
        
        <div class="login-panel">
            <h2 class="text-center">Welcome Back</h2>
            <p class="login-subtitle text-center">Sign in to your account</p>
            
            <?php if ($timeout): ?>
            <div class="alert alert-warning alert-dismissible fade show mt-3" role="alert">
                Your session has expired due to inactivity. Please log in again.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($errormsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                <?php echo htmlspecialchars($errormsg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <form method="post" action="login.php" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="form-group">
                    <input type="email" name="email" class="form-control" id="email" placeholder="Email Address" required autofocus
                           value="<?php echo isset($_COOKIE['user_email']) ? htmlspecialchars($_COOKIE['user_email']) : ''; ?>">
                    <i class="fas fa-envelope form-icon"></i>
                </div>
                
                <div class="form-group">
                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                    <i class="fas fa-lock form-icon"></i>
                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="remember" id="rememberMe"
                               <?php echo isset($_COOKIE['remember_token']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="rememberMe">Remember Me</label>
                    </div>
                    <a href="forgot_password.php" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    Sign In <i class="fas fa-arrow-right ms-2"></i>
                </button>
                
                <div class="divider">
                    <span>OR</span>
                </div>
                
                <p class="text-center" style="color: #fff;">
                    Don't have an account? <a href="register.php" class="register-link">Register Now</a>
                </p>
            </form>
        </div>
    </div>
    
    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Session Timeout Script -->
    <script src="js/session-timeout.js"></script>
    
    <script>
        // Form submission handling
        $(document).ready(function() {
            $('#loginForm').on('submit', function() {
                const button = $('#loginBtn');
                const spinner = button.find('.spinner-border');
                const buttonText = button.find('.button-text');
                
                // Show loading state
                spinner.removeClass('d-none');
                buttonText.text('Signing in...');
                button.prop('disabled', true);
            });
        });
        
        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const password = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>