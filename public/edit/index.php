<?php
<link rel="icon" type="image/png" href="/img/favicon.png">

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
<html>
<body>
    <form method="POST">
        <h2>Card Editor Login</h2>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Enter</button>
        <?php if(isset($error)) echo "<p>$error</p>"; ?>
    </form>
</body>
</html>