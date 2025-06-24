<?php
require_once 'config.php';
requireLogin();

// Get user statistics
$stats = [];

// Total books
$result = $conn->query("SELECT COUNT(*) as total FROM Book");
$stats['total_books'] = $result->fetch_assoc()['total'];

// Available books
$result = $conn->query("SELECT COUNT(*) as available FROM Book WHERE Status = 'Available'");
$stats['available_books'] = $result->fetch_assoc()['available'];

// Issued books
$result = $conn->query("SELECT COUNT(*) as issued FROM Book WHERE Status = 'Issued'");
$stats['issued_books'] = $result->fetch_assoc()['issued'];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM User");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Overdue books
$result = $conn->query("SELECT COUNT(*) as overdue FROM Transaction WHERE Status = 'Issued' AND DueDate < CURDATE()");
$stats['overdue_books'] = $result->fetch_assoc()['overdue'];

// Recent transactions
$recent_transactions = $conn->query("
    SELECT t.TransactionID, u.Name as UserName, b.Title, t.IssueDate, t.DueDate, t.Status
    FROM Transaction t
    JOIN User u ON t.UserID = u.UserID
    JOIN Book b ON t.BookID = b.BookID
    ORDER BY t.CreatedAt DESC
    LIMIT 5
");

// User's issued books (if student/faculty)
$user_books = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_books = $conn->query("
        SELECT b.Title, b.Author, t.IssueDate, t.DueDate, t.Fine,
               CASE WHEN t.DueDate < CURDATE() THEN 1 ELSE 0 END as IsOverdue
        FROM Transaction t
        JOIN Book b ON t.BookID = b.BookID
        WHERE t.UserID = $user_id AND t.Status = 'Issued'
        ORDER BY t.DueDate ASC
    ");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Library Management System</title>
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
                        <?php if (getUserRole() == 'Admin'): ?>
                            <li><a href="manage_users.php">Manage Users</a></li>
                            <li><a href="manage_books.php">Manage Books</a></li>
                            <li><a href="reports.php">Reports</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="welcome-section">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h2>
                <p>Role: <strong><?php echo htmlspecialchars(getUserRole()); ?></strong></p>
            </div>

            <div class="dashboard">
                <div class="dashboard-card">
                    <h3>📚 Total Books</h3>
                    <div class="number"><?php echo $stats['total_books']; ?></div>
                    <p>Books in library</p>
                </div>
                
                <div class="dashboard-card">
                    <h3>✅ Available Books</h3>
                    <div class="number"><?php echo $stats['available_books']; ?></div>
                    <p>Ready to issue</p>
                </div>
                
                <div class="dashboard-card">
                    <h3>📖 Issued Books</h3>
                    <div class="number"><?php echo $stats['issued_books']; ?></div>
                    <p>Currently issued</p>
                </div>
                
                <div class="dashboard-card">
                    <h3>⚠️ Overdue Books</h3>
                    <div class="number"><?php echo $stats['overdue_books']; ?></div>
                    <p>Need attention</p>
                </div>
            </div>

            <?php if ($user_books && $user_books->num_rows > 0): ?>
            <div class="table-container">
                <h3 style="padding: 1rem;">Your Issued Books</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Fine</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $user_books->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($book['Title']); ?></td>
                            <td><?php echo htmlspecialchars($book['Author']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($book['IssueDate'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($book['DueDate'])); ?></td>
                            <td>Rs. <?php echo number_format($book['Fine'], 2); ?></td>
                            <td>
                                <?php if ($book['IsOverdue']): ?>
                                    <span class="status-badge status-overdue">Overdue</span>
                                <?php else: ?>
                                    <span class="status-badge status-issued">On Time</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if (getUserRole() == 'Admin'): ?>
            <div class="table-container">
                <h3 style="padding: 1rem;">Recent Transactions</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>User</th>
                            <th>Book Title</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($transaction = $recent_transactions->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $transaction['TransactionID']; ?></td>
                            <td><?php echo htmlspecialchars($transaction['UserName']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['Title']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($transaction['IssueDate'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($transaction['DueDate'])); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($transaction['Status']); ?>">
                                    <?php echo $transaction['Status']; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div style="text-align: center; margin-top: 2rem;">
                <a href="search_books.php" class="btn">Search Books</a>
                <?php if (getUserRole() == 'Admin'): ?>
                    <a href="manage_books.php" class="btn btn-secondary" style="margin-left: 1rem;">Manage Books</a>
                    <a href="reports.php" class="btn btn-success" style="margin-left: 1rem;">View Reports</a>
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