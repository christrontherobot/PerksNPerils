<?php
require_once('../../src/db.php');
session_start();
if (!isset($_SESSION['authenticated'])) { header("Location: index.php"); exit; }

$types = ['characters', 'strengths', 'weaknesses', 'situations'];
$current_type = $_GET['type'] ?? 'characters';

// Handle Adding Data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['description'])) {
    if ($current_type === 'characters') {
        $stmt = $pdo->prepare("INSERT INTO characters (description, image_url) VALUES (?, ?)");
        $stmt->execute([$_POST['description'], $_POST['image_url'] ?? '']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO $current_type (description) VALUES (?)");
        $stmt->execute([$_POST['description']]);
    }
    // Refresh to clear post data
    header("Location: manage.php?type=$current_type");
    exit;
}

// Fetch Items
$items = $pdo->query("SELECT * FROM $current_type ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perks n' Perils | Editor</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <nav>
        <strong>PERKS N' PERILS</strong>
        <?php foreach($types as $t): ?>
            <a href="?type=<?= $t ?>" class="<?= $current_type == $t ? 'active' : '' ?>">
                <?= ucfirst($t) ?>
            </a>
        <?php endforeach; ?>
        <a href="logout.php" style="margin-left: auto; color: #666;">Logout</a>
    </nav>

    <div class="card-editor">
        <h2>Add New <?= ucfirst(substr($current_type, 0, -1)) ?></h2>
        <form method="POST">
            <textarea name="description" placeholder="Enter card text..." rows="3" required></textarea>
            <?php if($current_type === 'characters'): ?>
                <input type="text" name="image_url" placeholder="Direct Image URL (optional)">
            <?php endif; ?>
            <button type="submit">Add to Deck</button>
        </form>
    </div>

    <div class="item-list">
        <?php if(empty($items)): ?>
            <p style="grid-column: 1/-1; text-align: center; color: #666;">No items found in this category.</p>
        <?php endif; ?>

        <?php foreach($items as $item): ?>
            <div class="item-card">
                <a class="delete-btn" title="Delete Card" href="delete.php?type=<?= $current_type ?>&id=<?= $item['id'] ?>" onclick="return confirm('Permanently delete this card?')">✖</a>
                
                <?php if($current_type === 'characters'): ?>
                    <?php if(!empty($item['image_url'])): ?>
                        <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="Portrait">
                    <?php else: ?>
                        <div style="height: 180px; background: #1a1a1a; display: flex; align-items: center; justify-content: center; border-radius: 8px; margin-bottom: 15px; color: #444;">No Image</div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <p><?= htmlspecialchars($item['description']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>