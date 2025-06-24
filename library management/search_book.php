<?php
require_once 'config.php';
requireLogin();

$search_results = [];
$search_term = '';
$search_type = 'title';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $search_term = sanitize_input($_POST['search_term']);
    $search_type = sanitize_input($_POST['search_type']);
    
    if (!empty($search_term)) {
        $sql = "SELECT BookID, Title, Author, ISBN, Category, Status FROM Book WHERE ";
        
        switch ($search_type) {
            case 'title':
                $sql .= "Title LIKE ?";
                break;
            case 'author':
                $sql .= "Author LIKE ?";
                break;
            case 'category':
                $sql .= "Category LIKE ?";
                break;
            case 'isbn':
                $sql .= "ISBN LIKE ?";
                break;
            default:
                $sql .= "Title LIKE ? OR Author LIKE ? OR Category LIKE ?";
        }
        
        $stmt = $conn->prepare($sql);
        $search_param = "%$search_term%";
        
        if ($search_type == 'all') {
            $stmt->bind_param("sss", $search_param, $search_param, $search_param);
        } else {
            $stmt->bind_param("s", $search_param);
        }
        
        $stmt->execute();
        $search_results = $stmt->get_result();
    }
} else {
    // Show all books if no search performed
    $search_results = $conn->query("SELECT BookID, Title, Author, ISBN, Category, Status FROM Book ORDER BY Title");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Books - Library Management System</title>
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
                        <?php if (getUserRole() == 'Admin' || isset($_SESSION['librarian_id'])): ?>
                            <li><a href="manage_books.php">Manage Books</a></li>
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
            <div class="search-container">
                <h2>Search Books</h2>
                <form method="POST" action="" class="search-form">
                    <div class="form-group">
                        <label for="search_term">Search Term:</label>
                        <input type="text" name="search_term" id="search_term" 
                               value="<?php echo htmlspecialchars($search_term); ?>" 
                               placeholder="Enter book title, author, or keyword...">
                    </div>
                    
                    <div class="form-group">
                        <label for="search_type">Search By:</label>
                        <select name="search_type" id="search_type">
                            <option value="title" <?php echo ($search_type == 'title') ? 'selected' : ''; ?>>Title</option>
                            <option value="author" <?php echo ($search_type == 'author') ? 'selected' : ''; ?>>Author</option>
                            <option value="category" <?php echo ($search_type == 'category') ? 'selected' : ''; ?>>Category</option>
                            <option value="isbn" <?php echo ($search_type == 'isbn') ? 'selected' : ''; ?>>ISBN</option>
                            <option value="all" <?php echo ($search_type == 'all') ? 'selected' : ''; ?>>All Fields</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Search</button>
                </form>
            </div>

            <div class="table-container">
                <h3 style="padding: 1rem;">
                    <?php 
                    if ($search_term) {
                        echo "Search Results for: \"" . htmlspecialchars($search_term) . "\"";
                        echo " (" . $search_results->num_rows . " found)";
                    } else {
                        echo "All Books (" . $search_results->num_rows . " total)";
                    }
                    ?>
                </h3>
                
                <?php if ($search_results->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Book ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>ISBN</th>
                            <th>Category</th>
                            <th>Status</th>
                            <?php if (getUserRole() == 'Admin' || isset($_SESSION['librarian_id'])): ?>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($book = $search_results->fetch_assoc()): ?>
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
                            <?php if (getUserRole() == 'Admin' || isset($_SESSION['librarian_id'])): ?>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($book['Status'] == 'Available'): ?>
                                        <a href="issue_book.php?book_id=<?php echo $book['BookID']; ?>" 
                                           class="btn btn-sm">Issue</a>
                                    <?php else: ?>
                                        <a href="return_book.php?book_id=<?php echo $book['BookID']; ?>" 
                                           class="btn btn-sm btn-success">Return</a>
                                    <?php endif; ?>
                                    <a href="manage_books.php?edit=<?php echo $book['BookID']; ?>" 
                                       class="btn btn-sm btn-secondary">Edit</a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="padding: 2rem; text-align: center;">
                    <p>No books found matching your search criteria.</p>
                    <p>Try searching with different keywords or browse all books.</p>
                </div>
                <?php endif; ?>
            </div>

            <div style="text-align: center; margin-top: 2rem;">
                <a href="<?php echo isset($_SESSION['librarian_id']) ? 'librarian_dashboard.php' : 'dashboard.php'; ?>" 
                   class="btn btn-secondary">Back to Dashboard</a>
                <?php if (getUserRole() == 'Admin' || isset($_SESSION['librarian_id'])): ?>
                    <a href="manage_books.php" class="btn" style="margin-left: 1rem;">Manage Books</a>
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