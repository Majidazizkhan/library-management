<?php
require_once 'config.php';
requireLogin();

// Check if user is librarian
if (!isset($_SESSION['librarian_id'])) {
    header("Location: login.php");
    exit();
}

// Get librarian statistics
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

// Overdue books
$result = $conn->query("SELECT COUNT(*) as overdue FROM Transaction WHERE Status = 'Issued' AND DueDate < CURDATE()");
$stats['overdue_books'] = $result->fetch_assoc()['overdue'];

// Recent transactions handled by this librarian
$librarian_id = $_SESSION['librarian_id'];
$recent_transactions = $conn->query("
    SELECT t.TransactionID, u.Name as UserName, b.Title, t.IssueDate, t.DueDate, t.Status
    FROM Transaction t
    JOIN User u ON t.UserID = u.UserID
    JOIN Book b ON t.BookID = b.BookID
    WHERE t.LibrarianID = $librarian_id
    ORDER BY t.CreatedAt DESC
    LIMIT 5
");

// Overdue books that need attention
$overdue_books = $conn->query("
    SELECT t.TransactionID, u.Name as UserName, b.Title, t.IssueDate, t.DueDate,
           DATEDIFF(CURDATE(), t.DueDate) as DaysOverdue,
           (DATEDIFF(CURDATE(), t.DueDate) * 10) as CurrentFine
    FROM Transaction t
    JOIN User u ON t.UserID = u.UserID
    JOIN Book b ON t.BookID = b.BookID
    WHERE t.Status = 'Issued' AND t.DueDate < CURDATE()
    ORDER BY t.DueDate ASC
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Librarian Dashboard - Library Management System</title>
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
                        <li><a href="librarian_dashboard.php">Dashboard</a></li>
                        <li><a href="search_books.php">Search Books</a></li>
                        <li><a href="manage_books.php">Manage Books</a></li>
                        <li><a href="issue_book.php">Issue Book</a></li>
                        <li><a href="return_book.php">Return Book</a></li>
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
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h2>
                <p>Librarian Dashboard - Manage library operations efficiently</p>
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
                    <p>Need immediate attention</p>
                </div>
            </div>

            <div style="text-align: center; margin: 2rem 0;">
                <a href="issue_book.php" class="btn">Issue Book</a>
                <a href="return_book.php" class="btn btn-success" style="margin-left: 1rem;">Return Book</a>
                <a href="manage_books.php" class="btn btn-secondary" style="margin-left: 1rem;">Manage Books</a>
            </div>

            <?php if ($overdue_books->num_rows > 0): ?>
            <div class="table-container">
                <h3 style="padding: 1rem;">⚠️ Overdue Books - Urgent Attention Required</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>User</th>
                            <th>Book Title</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <th>Current Fine</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($overdue = $overdue_books->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $overdue['TransactionID']; ?></td>
                            <td><?php echo htmlspecialchars($overdue['UserName']); ?></td>
                            <td><?php echo htmlspecialchars($overdue['Title']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($overdue['IssueDate'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($overdue['DueDate'])); ?></td>
                            <td><span class="status-badge status-overdue"><?php echo $overdue['DaysOverdue']; ?> days</span></td>
                            <td>Rs. <?php echo number_format($overdue['CurrentFine'], 2); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="return_book.php?transaction_id=<?php echo $overdue['TransactionID']; ?>" 
                                       class="btn btn-sm btn-success">Process Return</a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <div class="table-container">
                <h3 style="padding: 1rem;">Recent Transactions (Your Activity)</h3>
                <?php if ($recent_transactions->num_rows > 0): ?>
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
                <?php else: ?>
                <div style="padding: 2rem; text-align: center;">
                    <p>No recent transactions found. Start issuing or returning books to see activity here.</p>
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