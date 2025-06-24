<?php
require_once 'config.php';
requireLogin();

// Check if user has permission to return books
if (getUserRole() != 'Admin' && !isset($_SESSION['librarian_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$selected_transaction = null;

// Get transaction details if transaction_id is provided
if (isset($_GET['transaction_id'])) {
    $transaction_id = intval($_GET['transaction_id']);
    $stmt = $conn->prepare("
        SELECT t.*, u.Name as UserName, u.Email, b.Title, b.Author 
        FROM Transaction t
        JOIN User u ON t.UserID = u.UserID
        JOIN Book b ON t.BookID = b.BookID
        WHERE t.TransactionID = ? AND t.Status = 'Issued'
    ");
    $stmt->bind_param("i", $transaction_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_transaction = $result->fetch_assoc();
}

// Get book details if book_id is provided
if (isset($_GET['book_id']) && !$selected_transaction) {
    $book_id = intval($_GET['book_id']);
    $stmt = $conn->prepare("
        SELECT t.*, u.Name as UserName, u.Email, b.Title, b.Author 
        FROM Transaction t
        JOIN User u ON t.UserID = u.UserID
        JOIN Book b ON t.BookID = b.BookID
        WHERE t.BookID = ? AND t.Status = 'Issued'
    ");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_transaction = $result->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $transaction_id = intval($_POST['transaction_id']);
    $return_date = sanitize_input($_POST['return_date']);
    
    if (empty($transaction_id) || empty($return_date)) {
        $error = "All fields are required.";
    } else {
        // Get transaction details
        $stmt = $conn->prepare("
            SELECT t.*, b.BookID 
            FROM Transaction t
            JOIN Book b ON t.BookID = b.BookID
            WHERE t.TransactionID = ? AND t.Status = 'Issued'
        ");
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        
        if (!$transaction) {
            $error = "Transaction not found or book already returned.";
        } else {
            // Calculate fine
            $fine = calculateFine($transaction['DueDate'], $return_date);
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Update transaction record
                $stmt = $conn->prepare("UPDATE Transaction SET ReturnDate = ?, Fine = ?, Status = 'Returned' WHERE TransactionID = ?");
                $stmt->bind_param("sdi", $return_date, $fine, $transaction_id);
                $stmt->execute();
                
                // Update book status
                $stmt = $conn->prepare("UPDATE Book SET Status = 'Available' WHERE BookID = ?");
                $stmt->bind_param("i", $transaction['BookID']);
                $stmt->execute();
                
                $conn->commit();
                
                if ($fine > 0) {
                    $success = "Book returned successfully! Fine: Rs. " . number_format($fine, 2);
                } else {
                    $success = "Book returned successfully! No fine applied.";
                }
                
                // Clear form data
                $selected_transaction = null;
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to return book. Please try again.";
            }
        }
    }
}

// Get all issued transactions
$issued_transactions = $conn->query("
    SELECT t.TransactionID, u.Name as UserName, b.Title, b.Author, t.IssueDate, t.DueDate,
           DATEDIFF(CURDATE(), t.DueDate) as DaysOverdue,
           CASE WHEN t.DueDate < CURDATE() THEN (DATEDIFF(CURDATE(), t.DueDate) * 10) ELSE 0 END as PotentialFine
    FROM Transaction t
    JOIN User u ON t.UserID = u.UserID
    JOIN Book b ON t.BookID = b.BookID
    WHERE t.Status = 'Issued'
    ORDER BY t.DueDate ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Return Book - Library Management System</title>
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
                        <li><a href="<?php echo isset($_SESSION['librarian_id']) ? 'librarian_dashboard.php' : 'dashboard.php'; ?>">Dashboard</a></li>
                        <li><a href="search_books.php">Search Books</a></li>
                        <li><a href="manage_books.php">Manage Books</a></li>
                        <li><a href="issue_book.php">Issue Book</a></li>
                        <li><a href="return_book.php">Return Book</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="form-container">
                <h2>Return Book</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="transaction_id">Select Transaction:</label>
                        <select name="transaction_id" id="transaction_id" required onchange="updateTransactionInfo()">
                            <option value="">Choose a transaction...</option>
                            <?php 
                            // Reset the result pointer for the dropdown
                            $issued_transactions->data_seek(0);
                            while ($trans = $issued_transactions->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $trans['TransactionID']; ?>" 
                                        data-user="<?php echo htmlspecialchars($trans['UserName']); ?>"
                                        data-book="<?php echo htmlspecialchars($trans['Title']); ?>"
                                        data-author="<?php echo htmlspecialchars($trans['Author']); ?>"
                                        data-issue-date="<?php echo $trans['IssueDate']; ?>"
                                        data-due-date="<?php echo $trans['DueDate']; ?>"
                                        data-fine="<?php echo $trans['PotentialFine']; ?>"
                                        <?php echo ($selected_transaction && $selected_transaction['TransactionID'] == $trans['TransactionID']) ? 'selected' : ''; ?>>
                                    #<?php echo $trans['TransactionID']; ?> - <?php echo htmlspecialchars($trans['UserName']); ?> - <?php echo htmlspecialchars($trans['Title']); ?>
                                    <?php if ($trans['DaysOverdue'] > 0): ?>
                                        (OVERDUE - <?php echo $trans['DaysOverdue']; ?> days)
                                    <?php endif; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div id="transaction-info" style="display: none; background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                        <h4>Transaction Details:</h4>
                        <p><strong>User:</strong> <span id="trans-user"></span></p>
                        <p><strong>Book:</strong> <span id="trans-book"></span> by <span id="trans-author"></span></p>
                        <p><strong>Issue Date:</strong> <span id="trans-issue-date"></span></p>
                        <p><strong>Due Date:</strong> <span id="trans-due-date"></span></p>
                        <p><strong>Potential Fine:</strong> Rs. <span id="trans-fine"></span></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="return_date">Return Date:</label>
                        <input type="date" name="return_date" id="return_date" required 
                               value="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d'); ?>">
                        <small>Today's date is pre-filled</small>
                    </div>
                    
                    <button type="submit" class="btn btn-full">Process Return</button>
                </form>
            </div>

            <div class="table-container">
                <h3 style="padding: 1rem;">All Issued Books</h3>
                <?php
                // Reset the result pointer for the table
                $issued_transactions->data_seek(0);
                ?>
                
                <?php if ($issued_transactions->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>User</th>
                            <th>Book</th>
                            <th>Author</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Potential Fine</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($trans = $issued_transactions->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $trans['TransactionID']; ?></td>
                            <td><?php echo htmlspecialchars($trans['UserName']); ?></td>
                            <td><?php echo htmlspecialchars($trans['Title']); ?></td>
                            <td><?php echo htmlspecialchars($trans['Author']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($trans['IssueDate'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($trans['DueDate'])); ?></td>
                            <td>
                                <?php if ($trans['DaysOverdue'] > 0): ?>
                                    <span class="status-badge status-overdue">
                                        <?php echo $trans['DaysOverdue']; ?> days overdue
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge status-issued">On time</span>
                                <?php endif; ?>
                            </td>
                            <td>Rs. <?php echo number_format($trans['PotentialFine'], 2); ?></td>
                            <td>
                                <a href="return_book.php?transaction_id=<?php echo $trans['TransactionID']; ?>" 
                                   class="btn btn-sm btn-success">Return</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="padding: 2rem; text-align: center;">
                    <p>No books currently issued.</p>
                    <p>All books have been returned.</p>
                </div>
                <?php endif; ?>
            </div>

            <div class="table-container">
                <h3 style="padding: 1rem;">Recent Returns</h3>
                <?php
                $recent_returns = $conn->query("
                    SELECT t.TransactionID, u.Name as UserName, b.Title, t.ReturnDate, t.Fine
                    FROM Transaction t
                    JOIN User u ON t.UserID = u.UserID
                    JOIN Book b ON t.BookID = b.BookID
                    WHERE t.Status = 'Returned'
                    ORDER BY t.ReturnDate DESC
                    LIMIT 10
                ");
                ?>
                
                <?php if ($recent_returns->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>User</th>
                            <th>Book</th>
                            <th>Return Date</th>
                            <th>Fine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($return = $recent_returns->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $return['TransactionID']; ?></td>
                            <td><?php echo htmlspecialchars($return['UserName']); ?></td>
                            <td><?php echo htmlspecialchars($return['Title']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($return['ReturnDate'])); ?></td>
                            <td>
                                <?php if ($return['Fine'] > 0): ?>
                                    <span class="status-badge status-overdue">Rs. <?php echo number_format($return['Fine'], 2); ?></span>
                                <?php else: ?>
                                    <span class="status-badge status-available">No Fine</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="padding: 2rem; text-align: center;">
                    <p>No recent returns found.</p>
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

    <script>
        function updateTransactionInfo() {
            const select = document.getElementById('transaction_id');
            const transInfo = document.getElementById('transaction-info');
            
            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                document.getElementById('trans-user').textContent = selectedOption.getAttribute('data-user');
                document.getElementById('trans-book').textContent = selectedOption.getAttribute('data-book');
                document.getElementById('trans-author').textContent = selectedOption.getAttribute('data-author');
                document.getElementById('trans-issue-date').textContent = new Date(selectedOption.getAttribute('data-issue-date')).toLocaleDateString();
                document.getElementById('trans-due-date').textContent = new Date(selectedOption.getAttribute('data-due-date')).toLocaleDateString();
                document.getElementById('trans-fine').textContent = parseFloat(selectedOption.getAttribute('data-fine')).toFixed(2);
                transInfo.style.display = 'block';
            } else {
                transInfo.style.display = 'none';
            }
        }

        // Initialize transaction info display if a transaction is already selected
        document.addEventListener('DOMContentLoaded', function() {
            updateTransactionInfo();
        });
    </script>
</body>
</html>