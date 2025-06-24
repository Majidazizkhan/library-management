<?php
// Database Configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "library_management";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// Start session (if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) || isset($_SESSION['librarian_id']);
}

// Function to get user role
function getUserRole() {
    return $_SESSION['role'] ?? null;
}

// Function to redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Function to calculate fine (Rs. 10 per day)
function calculateFine($dueDate, $returnDate = null) {
    $returnDate = $returnDate ?? date('Y-m-d');
    $due = new DateTime($dueDate);
    $return = new DateTime($returnDate);

    if ($return > $due) {
        $diff = $return->diff($due);
        return $diff->days * 10; // Rs. 10 per day
    }
    return 0;
}
?>
