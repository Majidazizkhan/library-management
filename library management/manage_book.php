<?php
require_once 'config.php';
requireLogin();

// Check if user has permission to manage books
if (getUserRole() != 'Admin' && !isset($_SESSION['librarian_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action == 'add') {
            $title = sanitize_input($_POST['title']);
            $author = sanitize_input($_POST['author']);
            $isbn = sanitize_input($_POST['isbn']);
            $category = sanitize_input($_POST['category']);
            
            if (empty($title) || empty($author) || empty($category)) {
                $error = "Title, Author, and Category are required.";
            } else {
                // Check if ISBN already exists
                if (!empty($isbn)) {
                    $stmt = $conn->prepare("SELECT BookID FROM Book WHERE ISBN = ?");
                    $stmt->bind_param("s", $isbn);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $error = "A book with this ISBN already exists.";
                    }
                }
                
                if (empty($error)) {
                    $stmt = $conn->prepare("INSERT INTO Book (Title, Author, ISBN, Category) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("ssss", $title, $author, $isbn, $category);
                    
                    if ($stmt->execute()) {
                        $success = "Book added successfully!";
                    } else {
                        $error = "Failed to add book. Please try again.";
                    }
                }
            }
        } elseif ($action == 'update') {
            $book_id = intval($_POST['book_id']);
            $title = sanitize_input($_POST['title']);
            $author = sanitize_input($_POST['author']);
            $isbn = sanitize_input($_POST['isbn']);
            $category = sanitize_input($_POST['category']);
            $status = sanitize_input($_POST['status']);
            
            if (empty($title) || empty($author) || empty($category)) {
                $error = "Title, Author, and Category are required.";
            } else {
                $stmt = $conn->prepare("UPDATE Book SET Title = ?, Author = ?, ISBN = ?, Category = ?, Status = ? WHERE BookID = ?");
                $stmt->bind_param("sssssi", $title, $author, $isbn, $category, $status, $book_id);
                
                if ($stmt->execute()) {
                    $success = "Book updated successfully!";
                } else {
                    $error = "Failed to update book. Please try again.";
                }
            }
        } elseif ($action == 'delete') {
            $book_id = intval($_POST['book_id']);
            
            // Check if book is currently issued
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Transaction WHERE BookID = ? AND Status = 'Issued'");
            $stmt->bind_param("i", $book_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $error = "Cannot delete book that is currently issued.";
            } else {
                $stmt = $conn->prepare("DELETE FROM Book WHERE BookID = ?");
                $stmt->bind_param("i", $book_id);
                
                if ($stmt->execute()) {
                    $success = "Book deleted successfully!";
                } else {
                    $error = "Failed to delete book. Please try again.";
                }
            }
        }
    }
}

// Get book for editing if edit parameter is set
$edit_book = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM Book WHERE BookID = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_book = $result->fetch_assoc();
}

// Get all books
$books = $conn->query("SELECT * FROM Book ORDER BY Title");

// Get categories for dropdown
$categories_result = $conn->query("SELECT DISTINCT Category FROM Book WHERE Category IS NOT NULL ORDER BY Category");
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['Category'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Library Management System</title>
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
                        <?php if (isset($_SESSION['librarian_id'])): ?>
                            <li><a href="issue_book.php">Issue Book</a></li>
                            <li><a href="return_book.php">Return Book</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <div class="container">
            <div class="form-container">
                <h2><?php echo $edit_book ? 'Edit Book' : 'Add New Book'; ?></h2>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="<?php echo $edit_book ? 'update' : 'add'; ?>">
                    <?php if ($edit_book): ?>
                        <input type="hidden" name="book_id" value="<?php echo $edit_book['BookID']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="title">Book Title:</label>
                        <input type="text" name="title" id="title" required 
                               value="<?php echo $edit_book ? htmlspecialchars($edit_book['Title']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="author">Author:</label>
                        <input type="text" name="author" id="author" required 
                               value="<?php echo $edit_book ? htmlspecialchars($edit_book['Author']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="isbn">ISBN:</label>
                        <input type="text" name="isbn" id="isbn" 
                               value="<?php echo $edit_book ? htmlspecialchars($edit_book['ISBN']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="category">Category:</label>
                        <input type="text" name="category" id="category" list="categories" required
                               value="<?php echo $edit_book ? htmlspecialchars($edit_book['Category']) : ''; ?>">
                        <datalist id="categories">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                            <?php endforeach; ?>
                            <option value="Computer Science">
                            <option value="Literature">
                            <option value="Mathematics">
                            <option value="Science">
                            <option value="History">
                            <option value="Fiction">
                            <option value="Non-Fiction">
                        </datalist>
                    </div>
                    
                    <?php if ($edit_book): ?>
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" required>
                            <option value="Available" <?php echo ($edit_book['Status'] == 'Available') ? 'selected' : ''; ?>>Available</option>
                            <option value="Issued" <?php echo ($edit_book['Status'] == 'Issued') ? 'selected' : ''; ?>>Issued</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-full">
                        <?php echo $edit_book ? 'Update Book' : 'Add Book'; ?>
                    </button>
                    
                    <?php if ($edit_book): ?>
                        <a href="manage_books.php" class="btn btn-secondary btn-full" style="margin-top: 1rem;">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="table-container">
                <h3 style="padding: 1rem;">All Books (<?php echo $books->num_rows; ?> total)</h3>
                
                <?php if ($books->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $books->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $book['BookID']; ?></td>
                            <td><?php echo htmlspecialchars($book['Title']); ?></td>
                            <td><?php echo htmlspecialchars($book['Author']); ?></td>
                            <td><?php echo htmlspecialchars($book['ISBN']); ?></td>
                            <td><?php echo htmlspecialchars($book['Category']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($book['Status']); ?>">
                                    <?php echo $book['Status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="manage_books.php?edit=<?php echo $book['BookID']; ?>" 
                                       class="btn btn-sm btn-secondary">Edit</a>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Are you sure you want to delete this book?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="book_id" value="<?php echo $book['BookID']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                    
                                    <?php if ($book['Status'] == 'Available'): ?>
                                        <a href="issue_book.php?book_id=<?php echo $book['BookID']; ?>" 
                                           class="btn btn-sm">Issue</a>
                                    <?php else: ?>
                                        <a href="return_book.php?book_id=<?php echo $book['BookID']; ?>" 
                                           class="btn btn-sm btn-success">Return</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="padding: 2rem; text-align: center;">
                    <p>No books found in the library.</p>
                    <p>Use the form above to add your first book.</p>
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