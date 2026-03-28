<?php
require_once('../../src/db.php');
session_start();
if (!isset($_SESSION['authenticated'])) { header("Location: index.php"); exit; }

$types = ['characters', 'strengths', 'weaknesses', 'situations'];
$current_type = $_GET['type'] ?? 'characters';

// Add Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
    $stmt = $pdo->prepare("INSERT INTO $current_type (description) VALUES (?)");
    $stmt->execute([$_POST['content']]);
}

// Fetch Logic
$cards = $pdo->query("SELECT * FROM $current_type ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head><title>Card Manager</title></head>
<body>
    <nav>
        <?php foreach($types as $t): ?>
            <a href="?type=<?= $t ?>"><?= ucfirst($t) ?></a> |
        <?php endforeach; ?>
        <a href="logout.php">Logout</a>
    </nav>

    <h1>Editing <?= ucfirst($current_type) ?></h1>

    <form method="POST">
        <textarea name="content" placeholder="Enter card text..." required></textarea><br>
        <button type="submit">Add Card</button>
    </form>

    <hr>

    <ul>
        <?php foreach($cards as $card): ?>
            <li>
                <?= htmlspecialchars($card['description']) ?> 
                <a href="delete.php?type=<?= $current_type ?>&id=<?= $card['id'] ?>" 
                   onclick="return confirm('Delete this card?')">[Delete]</a>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>