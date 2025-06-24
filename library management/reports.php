<?php
require_once 'config.php';
requireLogin();

// Check if user has permission to view reports
if (getUserRole() != 'Admin' && !isset($_SESSION['librarian_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Get statistics for reports
$stats = [];

// Total books and availability
$result = $conn->query("SELECT Status, COUNT(*) as count FROM Book GROUP BY Status");
while ($row = $result->fetch_assoc()) {
    $stats['books'][$row['Status']] = $row['count'];
}

// Total users by role
$result = $conn->query("SELECT Role, COUNT(*) as count FROM User GROUP BY Role");
while ($row = $result->fetch_assoc()) {
    $stats['users'][$row['Role']] = $row['count'];
}

// Transaction statistics
$stats['transactions']['total'] = $conn->query("SELECT COUNT(*) as count FROM Transaction")->fetch_assoc()['count'];
$stats['transactions']['issued'] = $conn->query("SELECT COUNT(*) as count FROM Transaction WHERE Status = 'Issued'")->fetch_assoc()['count'];
$stats['transactions']['returned'] = $conn->query("SELECT COUNT(*) as count FROM Transaction WHERE Status = 'Returned'")->fetch_assoc()['count'];
$stats['transactions']['overdue'] = $conn->query("SELECT COUNT(*) as count FROM Transaction WHERE Status = 'Issued' AND DueDate < CURDATE()")->fetch_assoc()['count'];

// Fine statistics
$fine_stats = $conn->query("SELECT SUM(Fine) as total_fine, AVG(Fine) as avg_fine FROM Transaction WHERE Fine > 0")->fetch_assoc();
$stats['fines']['total'] = $fine_stats['total_fine'] ?: 0;
$stats['fines']['average'] = $fine_stats['avg_fine'] ?: 0;

// Most popular books
$popular_books = $conn->query("
    SELECT b.Title, b.Author, COUNT(t.TransactionID) as IssueCount
    FROM Book b
    LEFT JOIN Transaction t ON b.BookID = t.BookID
    GROUP BY b.BookID
    ORDER BY IssueCount DESC
    LIMIT 10
");

// Most active users
$active_users = $conn->query("
    SELECT u.Name, u.Email, u.Role, COUNT(t.TransactionID) as TransactionCount
    FROM User u
    LEFT JOIN Transaction t ON u.UserID = t.UserID
    GROUP BY u.UserID
    ORDER BY TransactionCount DESC
    LIMIT 10
");

// Overdue books
$overdue_books = $conn->query("
    SELECT t.TransactionID, u.Name as UserName, b.Title, t.IssueDate, t.DueDate,
           DATEDIFF(CURDATE(), t.DueDate) as DaysOverdue,
           (DATEDIFF(CURDATE(), t.DueDate) * 10) as CurrentFine
    FROM Transaction t
    JOIN User u ON t.UserID = u.UserID
    JOIN Book b ON t.BookID = b.BookID
    WHERE t.Status = 'Issued' AND t.DueDate < CURDATE()
    ORDER BY t.DueDate ASC
");

// Monthly transaction trends (last 12 months)
$monthly_trends = $conn->query("
    SELECT 
        DATE_FORMAT(IssueDate, '%Y-%m') as Month,
        COUNT(*) as IssueCount,
        SUM(CASE WHEN Status = 'Returned' THEN 1 ELSE 0 END) as ReturnCount
    FROM Transaction
    WHERE IssueDate >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(IssueDate, '%Y-%m')
    ORDER BY Month DESC
");

// Category-wise book distribution
$category_stats = $conn->query("
    SELECT Category, COUNT(*) as BookCount
    FROM Book
    WHERE Category IS NOT NULL
    GROUP BY Category
    ORDER BY BookCount DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Library Management System</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                        <li><a href="<?php echo isset($_SESSION['librarian_id']) ? 'librarian_dashboard.php' : 'dashboard.php'; ?>">Dashboard</a></li>
                        <li><a href="search_books.php">Search Books</a></li>
                        <li><a href="manage_books.php">Manage Books</a></li>
                        <?php if (isset($_SESSION['librarian_id'])): ?>
                            <li><a href="issue_book.php">Issue Book</a></li>
                            <li><a href="return_book.php">Return Book</a></li>
                        <?php endif; ?>
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
                <h2>Library Reports & Analytics</h2>
                <p>Comprehensive overview of library operations and statistics</p>
            </div>

            <!-- Summary Statistics -->
            <div class="dashboard">
                <div class="dashboard-card">
                    <h3>📚 Total Books</h3>
                    <div class="number"><?php echo array_sum($stats['books']); ?></div>
                    <p>Available: <?php echo $stats['books']['Available'] ?? 0; ?> | Issued: <?php echo $stats['books']['Issued'] ?? 0; ?></p>
                </div>
                
                <div class="dashboard-card">
                    <h3>👥 Total Users</h3>
                    <div class="number"><?php echo array_sum($stats['users']); ?></div>
                    <p>Students: <?php echo $stats['users']['Student'] ?? 0; ?> | Faculty: <?php echo $stats['users']['Faculty'] ?? 0; ?></p>
                </div>
                
                <div class="dashboard-card">
                    <h3>📋 Total Transactions</h3>
                    <div class="number"><?php echo $stats['transactions']['total']; ?></div>
                    <p>Active: <?php echo $stats['transactions']['issued']; ?> | Completed: <?php echo $stats['transactions']['returned']; ?></p>
                </div>
                
                <div class="dashboard-card">
                    <h3>💰 Total Fines</h3>
                    <div class="number">Rs. <?php echo number_format($stats['fines']['total'], 2); ?></div>
                    <p>Average: Rs. <?php echo number_format($stats['fines']['average'], 2); ?></p>
                </div>
            </div>

            <!-- Charts Section -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div class="table-container">
                    <h3 style="padding: 1rem;">Book Status Distribution</h3>
                    <div style="padding: 2rem;">
                        <canvas id="bookStatusChart" width="400" height="300"></canvas>
                    </div>
                </div>
                
                <div class="table-container">
                    <h3 style="padding: 1rem;">Category Distribution</h3>
                    <div style="padding: 2rem;">
                        <canvas id="categoryChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Overdue Books Alert -->
            <?php if ($overdue_books->num_rows > 0): ?>
            <div class="table-container" style="border-left: 5px solid #dc3545;">
                <h3 style="padding: 1rem; background-color: #f8d7da; color: #721c24;">⚠️ Overdue Books - Immediate Attention Required</h3>
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
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Popular Books -->
            <div class="table-container">
                <h3 style="padding: 1rem;">📈 Most Popular Books</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Times Issued</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        while ($book = $popular_books->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td><?php echo htmlspecialchars($book['Title']); ?></td>
                            <td><?php echo htmlspecialchars($book['Author']); ?></td>
                            <td><?php echo $book['IssueCount']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Active Users -->
            <div class="table-container">
                <h3 style="padding: 1rem;">👑 Most Active Users</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>User Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Total Transactions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        while ($user = $active_users->fetch_assoc()): 
                        ?>
                        <tr>
                            <td><?php echo $rank++; ?></td>
                            <td><?php echo htmlspecialchars($user['Name']); ?></td>
                            <td><?php echo htmlspecialchars($user['Email']); ?></td>
                            <td><?php echo $user['Role']; ?></td>
                            <td><?php echo $user['TransactionCount']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Monthly Trends -->
            <div class="table-container">
                <h3 style="padding: 1rem;">📅 Monthly Transaction Trends</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Books Issued</th>
                            <th>Books Returned</th>
                            <th>Return Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($trend = $monthly_trends->fetch_assoc()): ?>
                        <?php 
                        $return_rate = $trend['IssueCount'] > 0 ? ($trend['ReturnCount'] / $trend['IssueCount']) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo date('F Y', strtotime($trend['Month'] . '-01')); ?></td>
                            <td><?php echo $trend['IssueCount']; ?></td>
                            <td><?php echo $trend['ReturnCount']; ?></td>
                            <td><?php echo number_format($return_rate, 1); ?>%</td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div style="text-align: center; margin-top: 2rem;">
                <button onclick="window.print()" class="btn">Print Report</button>
                <a href="<?php echo isset($_SESSION['librarian_id']) ? 'librarian_dashboard.php' : 'dashboard.php'; ?>" 
                   class="btn btn-secondary" style="margin-left: 1rem;">Back to Dashboard</a>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 Library Management System</p>
        </div>
    </footer>

    <script>
        // Book Status Chart
        const bookStatusCtx = document.getElementById('bookStatusChart').getContext('2d');
        new Chart(bookStatusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Issued'],
                datasets: [{
                    data: [<?php echo $stats['books']['Available'] ?? 0; ?>, <?php echo $stats['books']['Issued'] ?? 0; ?>],
                    backgroundColor: ['#28a745', '#ffc107'],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'bar',
            data: {
                labels: [
                    <?php 
                    $category_stats->data_seek(0);
                    while ($cat = $category_stats->fetch_assoc()): 
                    ?>
                    '<?php echo htmlspecialchars($cat['Category']); ?>',
                    <?php endwhile; ?>
                ],
                datasets: [{
                    label: 'Books',
                    data: [
                        <?php 
                        $category_stats->data_seek(0);
                        while ($cat = $category_stats->fetch_assoc()): 
                        ?>
                        <?php echo $cat['BookCount']; ?>,
                        <?php endwhile; ?>
                    ],
                    backgroundColor: '#667eea',
                    borderColor: '#5a6fd8',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>
</body>
</html>