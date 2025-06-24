<?php
require_once 'config.php';
requireLogin();

// Check if user is admin
if (getUserRole() != 'Admin') {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add_user') {
            $name = sanitize_input($_POST['name']);
            $email = sanitize_input($_POST['email']);
            $password = sanitize_input($_POST['password']);
            $role = sanitize_input($_POST['role']);
            
            if (empty($name) || empty($email) || empty($password) || empty($role)) {
                $error = "All fields are required.";
            } else {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT Email FROM User WHERE Email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "Email already exists.";
                } else {
                    $hashed_password = md5($password);
                    $stmt = $conn->prepare("INSERT INTO User (Name, Email, Password, Role) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
                    
                    if ($stmt->execute()) {
                        $success = "User added successfully!";
                    } else {
                        $error = "Failed to add user.";
                    }
                }
            }
        } elseif ($action == 'add_librarian') {
            $name = sanitize_input($_POST['name']);
            $email = sanitize_input($_POST['email']);
            $phone = sanitize_input($_POST['phone']);
            $shift_time = sanitize_input($_POST['shift_time']);
            $password = sanitize_input($_POST['password']);
            
            if (empty($name) || empty($email) || empty($password)) {
                $error = "Name, email, and password are required.";
            } else {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT Email FROM Librarian WHERE Email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $error = "Email already exists.";
                } else {
                    $hashed_password = md5($password);
                    $stmt = $conn->prepare("INSERT INTO Librarian (Name, Email, Phone, ShiftTime, Password) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $name, $email, $phone, $shift_time, $hashed_password);
                    
                    if ($stmt->execute()) {
                        $success = "Librarian added successfully!";
                    } else {
                        $error = "Failed to add librarian.";
                    }
                }
            }
        } elseif ($action == 'delete_user') {
            $user_id = intval($_POST['user_id']);
            
            // Check if user has active transactions
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Transaction WHERE UserID = ? AND Status = 'Issued'");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $error = "Cannot delete user with active book issues.";
            } else {
                $stmt = $conn->prepare("DELETE FROM User WHERE UserID = ?");
                $stmt->bind_param("i", $user_id);
                
                if ($stmt->execute()) {
                    $success = "User deleted successfully!";
                } else {
                    $error = "Failed to delete user.";
                }
            }
        } elseif ($action == 'delete_librarian') {
            $librarian_id = intval($_POST['librarian_id']);
            
            $stmt = $conn->prepare("DELETE FROM Librarian WHERE LibrarianID = ?");
            $stmt->bind_param("i", $librarian_id);
            
            if ($stmt->execute()) {
                $success = "Librarian deleted successfully!";
            } else {
                $error = "Failed to delete librarian.";
            }
        }
    }
}

// Get all users
$users = $conn->query("SELECT UserID, Name, Email, Role, CreatedAt FROM User ORDER BY Name");

// Get all librarians
$librarians = $conn->query("SELECT LibrarianID, Name, Email, Phone, ShiftTime, CreatedAt FROM Librarian ORDER BY Name");

// Get user statistics
$user_stats = [];
$result = $conn->query("SELECT Role, COUNT(*) as count FROM User GROUP BY Role");
while ($row = $result->fetch_assoc()) {
    $user_stats[$row['Role']] = $row['count'];
}
$user_stats['Librarians'] = $conn->query("SELECT COUNT(*) as count FROM Librarian")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Library Management System</title>
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
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="search_books.php">Search Books</a></li>
                        <li><a href="manage_users.php">Manage Users</a></li>
                        <li><a href="manage_books.php">Manage Books</a></li>
                        <li><a href="reports.php">Reports</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="welcome-section">
                <h2>User Management</h2>
                <p>Manage students, faculty, and librarian accounts</p>
            </div>

            <!-- User Statistics -->
            <div class="dashboard">
                <div class="dashboard-card">
                    <h3>👨‍🎓 Students</h3>
                    <div class="number"><?php echo $user_stats['Student'] ?? 0; ?></div>
                    <p>Student accounts</p>
                </div>
                
                <div class="dashboard-card">
                    <h3>👨‍🏫 Faculty</h3>
                    <div class="number"><?php echo $user_stats['Faculty'] ?? 0; ?></div>
                    <p>Faculty accounts</p>
                </div>
                
                <div class="dashboard-card">
                    <h3>📚 Librarians</h3>
                    <div class="number"><?php echo $user_stats['Librarians']; ?></div>
                    <p>Librarian accounts</p>
                </div>
                
                <div class="dashboard-card">
                    <h3>👥 Total Users</h3>
                    <div class="number"><?php echo array_sum($user_stats); ?></div>
                    <p>All system users</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <!-- Add User/Librarian Forms -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <!-- Add User Form -->
                <div class="form-container" style="margin: 0;">
                    <h3>Add New User</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_user">
                        
                        <div class="form-group">
                            <label for="user_name">Full Name:</label>
                            <input type="text" name="name" id="user_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_email">Email:</label>
                            <input type="email" name="email" id="user_email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_role">Role:</label>
                            <select name="role" id="user_role" required>
                                <option value="">Select Role</option>
                                <option value="Student">Student</option>
                                <option value="Faculty">Faculty</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_password">Password:</label>
                            <input type="password" name="password" id="user_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-full">Add User</button>
                    </form>
                </div>

                <!-- Add Librarian Form -->
                <div class="form-container" style="margin: 0;">
                    <h3>Add New Librarian</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add_librarian">
                        
                        <div class="form-group">
                            <label for="lib_name">Full Name:</label>
                            <input type="text" name="name" id="lib_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="lib_email">Email:</label>
                            <input type="email" name="email" id="lib_email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="lib_phone">Phone:</label>
                            <input type="tel" name="phone" id="lib_phone">
                        </div>
                        
                        <div class="form-group">
                            <label for="lib_shift">Shift Time:</label>
                            <select name="shift_time" id="lib_shift">
                                <option value="">Select Shift</option>
                                <option value="Morning (9AM-5PM)">Morning (9AM-5PM)</option>
                                <option value="Evening (2PM-10PM)">Evening (2PM-10PM)</option>
                                <option value="Night (10PM-6AM)">Night (10PM-6AM)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="lib_password">Password:</label>
                            <input type="password" name="password" id="lib_password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-full">Add Librarian</button>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="table-container">
                <h3 style="padding: 1rem;">All Users (<?php echo $users->num_rows; ?> total)</h3>
                
                <?php if ($users->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $user['UserID']; ?></td>
                            <td><?php echo htmlspecialchars($user['Name']); ?></td>
                            <td><?php echo htmlspecialchars($user['Email']); ?></td>
                            <td>
                                <span class="status-badge status-available">
                                    <?php echo $user['Role']; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['CreatedAt'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['UserID']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="padding: 2rem; text-align: center;">
                    <p>No users found.</p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Librarians Table -->
            <div class="table-container">
                <h3 style="padding: 1rem;">All Librarians (<?php echo $librarians->num_rows; ?> total)</h3>
                
                <?php if ($librarians->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Shift</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($librarian = $librarians->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $librarian['LibrarianID']; ?></td>
                            <td><?php echo htmlspecialchars($librarian['Name']); ?></td>
                            <td><?php echo htmlspecialchars($librarian['Email']); ?></td>
                            <td><?php echo htmlspecialchars($librarian['Phone']); ?></td>
                            <td><?php echo htmlspecialchars($librarian['ShiftTime']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($librarian['CreatedAt'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('Are you sure you want to delete this librarian?');">
                                    <input type="hidden" name="action" value="delete_librarian">
                                    <input type="hidden" name="librarian_id" value="<?php echo $librarian['LibrarianID']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="padding: 2rem; text-align: center;">
                    <p>No librarians found.</p>
                </div>
                <?php endif; ?>
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