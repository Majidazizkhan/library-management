<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System</title>
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
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="welcome-section">
                <h2>Welcome to Our Library</h2>
                <p>Manage your library efficiently with our comprehensive Library Management System. 
                   Issue books, track returns, calculate fines automatically, and maintain complete records 
                   of all library transactions.</p>
                
                <div style="margin-top: 2rem;">
                    <a href="login.php" class="btn" style="margin-right: 1rem;">Login</a>
                    <a href="register.php" class="btn btn-secondary">Register</a>
                </div>
            </div>

            <div class="dashboard">
                <div class="dashboard-card">
                    <h3>📖 Book Management</h3>
                    <p>Comprehensive book catalog with easy search and management features. Track availability, categories, and detailed book information.</p>
                </div>
                
                <div class="dashboard-card">
                    <h3>👥 User Management</h3>
                    <p>Manage students, faculty, and library staff with role-based access control and user authentication.</p>
                </div>
                
                <div class="dashboard-card">
                    <h3>📋 Transaction Tracking</h3>
                    <p>Complete transaction history with issue dates, due dates, returns, and automatic fine calculations.</p>
                </div>
                
                <div class="dashboard-card">
                    <h3>📊 Reports & Analytics</h3>
                    <p>Generate detailed reports on library usage, overdue books, inventory status, and user activity.</p>
                </div>
            </div>

            <div class="table-container">
                <h3 style="padding: 1rem;">System Features</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Admin</th>
                            <th>Librarian</th>
                            <th>Student</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Book Search</td>
                            <td>✅</td>
                            <td>✅</td>
                            <td>✅</td>
                        </tr>
                        <tr>
                            <td>Issue/Return Books</td>
                            <td>✅</td>
                            <td>✅</td>
                            <td>❌</td>
                        </tr>
                        <tr>
                            <td>Manage Books</td>
                            <td>✅</td>
                            <td>✅</td>
                            <td>❌</td>
                        </tr>
                        <tr>
                            <td>User Management</td>
                            <td>✅</td>
                            <td>❌</td>
                            <td>❌</td>
                        </tr>
                        <tr>
                            <td>View Reports</td>
                            <td>✅</td>
                            <td>✅</td>
                            <td>❌</td>
                        </tr>
                        <tr>
                            <td>Fine Calculation</td>
                            <td>✅</td>
                            <td>✅</td>
                            <td>❌</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Library Management System. Developed by Majid Khan, Khuzaima Fariq, Waleed Babar</p>
        </div>
    </footer>
</body>
</html>