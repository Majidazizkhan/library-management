<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = sanitize_input($_POST['password']);
    $user_type = sanitize_input($_POST['user_type']);
    
    if (empty($email) || empty($password) || empty($user_type)) {
        $error = "All fields are required.";
    } else {
        $hashed_password = md5($password);
        
        if ($user_type == 'user') {
            // Check in User table
            $stmt = $conn->prepare("SELECT UserID, Name, Email, Role FROM User WHERE Email = ? AND Password = ?");
            $stmt->bind_param("ss", $email, $hashed_password);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                $_SESSION['user_id'] = $user['UserID'];
                $_SESSION['name'] = $user['Name'];
                $_SESSION['email'] = $user['Email'];
                $_SESSION['role'] = $user['Role'];
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        } elseif ($user_type == 'librarian') {
            // Check in Librarian table
            $stmt = $conn->prepare("SELECT LibrarianID, Name, Email FROM Librarian WHERE Email = ? AND Password = ?");
            $stmt->bind_param("ss", $email, $hashed_password);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows == 1) {
                $librarian = $result->fetch_assoc();
                $_SESSION['librarian_id'] = $librarian['LibrarianID'];
                $_SESSION['name'] = $librarian['Name'];
                $_SESSION['email'] = $librarian['Email'];
                $_SESSION['role'] = 'Librarian';
                header("Location: librarian_dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Library Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1>📚 Library Management System</h1>
                </div>
                <nav>
                    <ul class="nav-links">
                        <li><a href="index.php">Home</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="form-container">
                <h2>Login to Your Account</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="user_type">Login As:</label>
                        <select name="user_type" id="user_type" required>
                            <option value="">Select User Type</option>
                            <option value="user">Student/Faculty</option>
                            <option value="librarian">Librarian</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" name="email" id="email" required 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" name="password" id="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-full">Login</button>
                </form>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
                
                <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                    <h4>Demo Credentials:</h4>
                    <p><strong>Admin:</strong> admin@library.com / admin123</p>
                    <p><strong>Librarian:</strong> librarian@library.com / librarian123</p>
                    <p><strong>Student:</strong> majid@student.com / student123</p>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Library Management System</p>
        </div>
    </footer>
</body>
</html>