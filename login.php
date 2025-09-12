<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit();
}

$error_message = '';

// Include authentication functions
require_once 'includes/auth.php';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        // Authenticate with database
        if (authenticateUser($username, $password)) {
            // Redirect to originally requested page or dashboard
            $redirect_url = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect_url);
            exit();
        } else {
            $error_message = 'Invalid username or password. Please try again.';
        }
    }
}

$page_title = 'Login - CASSIA DENTAL CARE Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="Login to CASSIA DENTAL CARE management system">
    
    <!-- Favicon - Tooth Icon (Same as main system) -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path fill='%232563eb' d='M443.4 92.7C443.4 41.5 401.9 0 350.7 0c-31.1 0-58.7 15.4-75.7 38.9C258 15.4 230.4 0 199.3 0 148.1 0 106.6 41.5 106.6 92.7c0 22.1 7.4 42.5 19.9 58.8 16.4 21.4 38.7 43.6 46.4 70.9 6.3 22.4 8.5 46.1 8.5 70.1 0 75.8-19.9 136.8-19.9 212.6 0 55.1 44.9 100 100 100s100-44.9 100-100c0-75.8-19.9-136.8-19.9-212.6 0-24 2.2-47.7 8.5-70.1 7.7-27.3 30-49.5 46.4-70.9 12.5-16.3 19.9-36.7 19.9-58.8z'/></svg>">
    <link rel="shortcut icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 448 512'><path fill='%232563eb' d='M443.4 92.7C443.4 41.5 401.9 0 350.7 0c-31.1 0-58.7 15.4-75.7 38.9C258 15.4 230.4 0 199.3 0 148.1 0 106.6 41.5 106.6 92.7c0 22.1 7.4 42.5 19.9 58.8 16.4 21.4 38.7 43.6 46.4 70.9 6.3 22.4 8.5 46.1 8.5 70.1 0 75.8-19.9 136.8-19.9 212.6 0 55.1 44.9 100 100 100s100-44.9 100-100c0-75.8-19.9-136.8-19.9-212.6 0-24 2.2-47.7 8.5-70.1 7.7-27.3 30-49.5 46.4-70.9 12.5-16.3 19.9-36.7 19.9-58.8z'/></svg>">
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        /* Login Page Styles - Consistent with Medical Theme */
        :root {
            /* Exact same color palette as main system */
            --primary-blue: #2563eb;
            --light-blue: #dbeafe;
            --accent-blue: #3b82f6;
            --dark-blue: #1e40af;
            --white: #ffffff;
            --light-gray: #f8fafc;
            --text-dark: #1f2937;
            --text-medium: #4b5563;
            --border-color: #e2e8f0;
            --success-green: #10b981;
            --error-red: #ef4444;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--light-blue) 0%, var(--white) 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: var(--white);
            border-radius: 15px;
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            color: var(--white);
            padding: 40px 30px;
            text-align: center;
        }

        .login-header .logo {
            font-size: 3rem;
            margin-bottom: 10px;
            color: var(--white);
        }

        .login-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .login-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .login-form {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-group input::placeholder {
            color: var(--text-medium);
        }

        .login-button {
            width: 100%;
            background: linear-gradient(135deg, var(--primary-blue), var(--accent-blue));
            color: var(--white);
            border: none;
            padding: 14px 24px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .login-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .login-button:active {
            transform: translateY(0);
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--error-red);
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-info {
            background: var(--light-gray);
            border-top: 1px solid var(--border-color);
            padding: 20px 30px;
            text-align: center;
            color: var(--text-medium);
            font-size: 0.9rem;
        }

        .login-info .demo-credentials {
            background: var(--white);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid var(--border-color);
        }

        .login-info .demo-credentials strong {
            color: var(--text-dark);
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                margin: 10px;
                max-width: none;
            }

            .login-header {
                padding: 30px 20px;
            }

            .login-form {
                padding: 30px 20px;
            }

            .login-info {
                padding: 15px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Login Header -->
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-tooth"></i>
            </div>
            <h1>CANINEHUB SDN BHD</h1>
            <p>CASSIA DENTAL CARE Management System</p>
        </div>

        <!-- Login Form -->
        <div class="login-form">
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="username">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Enter your username"
                        required
                        autocomplete="username"
                        value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <button type="submit" class="login-button">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>
        </div>

        <!-- Login Information -->
        <div class="login-info">
            <p><i class="fas fa-info-circle"></i> Professional CASSIA DENTAL CARE management access</p>
            <p><i class="fas fa-shield-alt"></i> Secure authentication required</p>
        </div>
    </div>

    <script>
        // Auto-focus username field on page load
        document.addEventListener('DOMContentLoaded', function() {
            const usernameField = document.getElementById('username');
            if (usernameField && !usernameField.value) {
                usernameField.focus();
            }
        });

        // Handle form submission with loading state
        document.querySelector('form').addEventListener('submit', function() {
            const button = document.querySelector('.login-button');
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
            button.disabled = true;
        });
    </script>
</body>
</html>