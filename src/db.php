<?php
// Get the Internal Database URL from Render
$dbUrl = getenv('INTERNAL_DATABASE_URL'); 

if (!$dbUrl) {
    die("Database URL not found in environment variables.");
}

// PDO can actually take a DSN-style string, but we need to format it
// from postgres://user:pass@host:port/db to pgsql:host=...
$dbopts = parse_url($dbUrl);

$host = $dbopts["host"];
$port = $dbopts["port"] ?? 5432; // Default to 5432 if port is missing
$user = $dbopts["user"];
$pass = $dbopts["pass"];
$name = ltrim($dbopts["path"], '/');

// Robust DSN construction
$dsn = "pgsql:host=$host;port=$port;dbname=$name";

try {
    $pdo = new PDO($dsn, $user, $pass);
    // This line is important for catching SQL errors early
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // If it fails, show the error (useful for debugging)
    die("Connection failed: " . $e->getMessage());
}