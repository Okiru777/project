<?php
$host = "sql202.infinityfree.com";
$dbname = "if0_41601021_database";
$username = "if0_41601021";  
$password = "IWMESSfwhis123";     

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>