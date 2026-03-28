<?php
// Use the internal URL provided by Render for speed/security
$dbUrl = getenv('INTERNAL_DATABASE_URL'); 

if (!$dbUrl) {
    die("Database URL not found in environment variables.");
}

$dbopts = parse_url($dbUrl);

$host = $dbopts["host"];
$port = $dbopts["port"];
$user = $dbopts["user"];
$pass = $dbopts["pass"];
$name = ltrim($dbopts["path"], '/');

$dsn = "pgsql:host=$host;port=$port;dbname=$name";

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}