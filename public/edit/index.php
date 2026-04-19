<?php
session_start();
$admin_pass = getenv('ADMIN_PASSWORD'); // Set this in Render settings

if (isset($_POST['password'])) {
    if ($_POST['password'] === $admin_pass) {
        $_SESSION['authenticated'] = true;
        header("Location: manage.php");
        exit;
    }
    $error = "Access Denied.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Perks n' Perils</title>
    
    <link rel="icon" type="image/png" href="/img/favicon.png">
    
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <form method="POST">
        <h2>Card Editor Login</h2>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Enter</button>
        <?php if(isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
    </form>
</body>
</html>