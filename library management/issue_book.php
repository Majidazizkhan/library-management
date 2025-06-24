<?php
require_once 'config.php';
requireLogin();

// Check if user has permission to issue books
if (getUserRole() != 'Admin' && !isset($_SESSION['librarian_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';
$selected_book = null;
$selected_user = null;

// Get book details if book_id is provided
if (isset($_GET['book_id'])) {
    $book_id = intval($_GET['book_id']);
    $stmt = $conn->prepare("SELECT * FROM Book WHERE BookID = ? AND Status = 'Available'");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $selected_book = $result->fetch_assoc();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $book_id = intval($_POST['book_id']);
    $user_id = intval($_POST['user_id']);
    $due_date = sanitize_input($_POST['due_date']);
    $librarian_id = isset($_SESSION['librarian_id']) ? $_SESSION['librarian_id'] : 1; // Default to first librarian if admin
    
    if (empty($book_id) || empty($user_id) || empty($due_date)) {
        $error = "All fields are required.";
    } else {
        // Validate due date is in the future
        if (strtotime($due_date) <= time()) {
            $error = "Due date must be in the future.";
        } else {
            // Check if book is available
            $stmt = $conn->prepare("SELECT Status FROM Book WHERE BookID = ?");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $book = $result->fetch_assoc();
            
            if (!$book || $book['Status'] != 'Available') {
                $error = "Selected book is not available for issue.";
            } else {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Insert transaction record
                    $issue_date = date('Y-m-d');
                    $stmt = $conn->prepare("INSERT INTO Transaction (UserID, BookID, LibrarianID, IssueDate, DueDate, Status) VALUES (?, ?, ?, ?, ?, 'Issued')");
                    $stmt->bind_param("iiiss", $user_id, $book_id, $librarian_id, $issue_date, $due_date);
                    $stmt->execute();
                    
                    // Update book status
                    $stmt = $conn->prepare("UPDATE Book SET Status = 'Issued' WHERE BookID = ?");
                    $stmt->bind_param("i", $book_id);
                    $stmt->execute();
                    
                    $conn->commit();
                    $success = "Book issued successfully!";
                    
                    // Clear form data
                    $selected_book = null;
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Failed to issue book. Please try again.";
                }
            }
        }
    }
}

// Get available books
$available_books = $conn->query("SELECT BookID, Title, Author FROM Book WHERE Status = 'Available' ORDER BY Title");

// Get all users
$users = $conn->query("SELECT UserID, Name, Email, Role FROM User ORDER BY Name");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Book - Library Management System</title>
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
                <h2>Issue Book</h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="book_id">Select Book:</label>
                        <select name="book_id" id="book_id" required onchange="updateBookInfo()">
                            <option value="">Choose a book...</option>
                            <?php while ($book = $available_books->fetch_assoc()): ?>
                                <option value="<?php echo $book['BookID']; ?>" 
                                        data-title="<?php echo htmlspecialchars($book['Title']); ?>"
                                        data-author="<?php echo htmlspecialchars($book['Author']); ?>"
                                        <?php echo ($selected_book && $selected_book['BookID'] == $book['BookID']) ? 'selected' : ''; ?>>
                                    #<?php echo $book['BookID']; ?> - <?php echo htmlspecialchars($book['Title']); ?> by <?php echo htmlspecialchars($book['Author']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div id="book-info" style="display: none; background: #f8f9fa; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;">
                        <h4>Selected Book Details:</h4>
                        <p><strong>Title:</strong> <span id="book-title"></span></p>
                        <p><strong>Author:</strong> <span id="book-author"></span></p>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_id">Select User:</label>
                        <select name="user_id" id="user_id" required>
                            <option value="">Choose a user...</option>
                            <?php 
                            // Reset the result pointer
                            $users->data_seek(0);
                            while ($user = $users->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $user['UserID']; ?>">
                                    <?php echo htmlspecialchars($user['Name']); ?> - <?php echo htmlspecialchars($user['Email']); ?> (<?php echo $user['Role']; ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date:</label>
                        <input type="date" name="due_date" id="due_date" required 
                               value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        <small>Default: 14 days from today</small>
                    </div>
                    
                    <button type="submit" class="btn btn-full">Issue Book</button>
                </form>
            </div>

            <div class="table-container">
                <h3 style="padding: 1rem;">Recently Issued Books</h3>
                <?php
                $recent_issues = $conn->query("
                    SELECT t.TransactionID, u.Name as UserName, b.Title, b.Author, t.IssueDate, t.DueDate
                    FROM Transaction t
                    JOIN User u ON t.UserID = u.UserID
                    JOIN Book b ON t.BookID = b.BookID
                    WHERE t.Status = 'Issued'
                    ORDER BY t.CreatedAt DESC
                    LIMIT 10
                ");
                ?>
                
                <?php if ($recent_issues->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>User</th>
                            <th>Book</th>
                            <th>Author</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Days Left</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($issue = $recent_issues->fetch_assoc()): ?>
                        <?php
                        $days_left = (strtotime($issue['DueDate']) - time()) / (60 * 60 * 24);
                        $days_left = ceil($days_left);
                        ?>
                        <tr>
                            <td>#<?php echo $issue['TransactionID']; ?></td>
                            <td><?php echo htmlspecialchars($issue['UserName']); ?></td>
                            <td><?php echo htmlspecialchars($issue['Title']); ?></td>
                            <td><?php echo htmlspecialchars($issue['Author']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($issue['IssueDate'])); ?></td>
                            <td><?php echo date('M d, Y', strtotime($issue['DueDate'])); ?></td>
                            <td>
                                <?php if ($days_left < 0): ?>
                                    <span class="status-badge status-overdue"><?php echo abs($days_left); ?> days overdue</span>
                                <?php elseif ($days_left <= 3): ?>
                                    <span class="status-badge status-issued"><?php echo $days_left; ?> days left</span>
                                <?php else: ?>
                                    <span class="status-badge status-available"><?php echo $days_left; ?> days left</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="padding: 2rem; text-align: center;">
                    <p>No books currently issued.</p>
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
        function updateBookInfo() {
            const select = document.getElementById('book_id');
            const bookInfo = document.getElementById('book-info');
            const bookTitle = document.getElementById('book-title');
            const bookAuthor = document.getElementById('book-author');
            
            if (select.value) {
                const selectedOption = select.options[select.selectedIndex];
                bookTitle.textContent = selectedOption.getAttribute('data-title');
                bookAuthor.textContent = selectedOption.getAttribute('data-author');
                bookInfo.style.display = 'block';
            } else {
                bookInfo.style.display = 'none';
            }
        }

        // Initialize book info display if a book is already selected
        document.addEventListener('DOMContentLoaded', function() {
            updateBookInfo();
        });
    </script>
</body>
</html>