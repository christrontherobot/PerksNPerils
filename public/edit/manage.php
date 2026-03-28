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
    } elseif ($current_type === 'strengths') {
        $stmt = $pdo->prepare("INSERT INTO strengths (description, points) VALUES (?, ?)");
        $stmt->execute([$_POST['description'], (int)($_POST['points'] ?? 0)]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO $current_type (description) VALUES (?)");
        $stmt->execute([$_POST['description']]);
    }
    header("Location: manage.php?type=$current_type");
    exit;
}

$items = $pdo->query("SELECT * FROM $current_type ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Perks n' Perils | Editor</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <nav>
        <strong style="width: 100%; margin-bottom: 5px;">Perks n' Perils</strong>
        <?php foreach($types as $t): ?>
            <a href="?type=<?= $t ?>" class="<?= $current_type == $t ? 'active' : '' ?>"><?= ucfirst($t) ?></a>
        <?php endforeach; ?>
    </nav>

    <div class="card-editor">
        <h3>Add <?= ucfirst(substr($current_type, 0, -1)) ?></h3>
        <form method="POST">
            <textarea name="description" placeholder="Description..." rows="2" required></textarea>
            
            <?php if($current_type === 'characters'): ?>
                <input type="text" name="image_url" placeholder="Image URL (Portrait)">
            <?php endif; ?>

            <?php if($current_type === 'strengths'): ?>
                <input type="number" name="points" placeholder="Point Value (0-10)" min="0" max="10">
            <?php endif; ?>

            <button type="submit">Save Card</button>
        </form>
    </div>

    <div class="item-list">
        <?php foreach($items as $item): ?>
            <div class="item-card">
                <a class="delete-btn" href="delete.php?type=<?= $current_type ?>&id=<?= $item['id'] ?>" onclick="return confirm('Delete?')">✖</a>
                
                <?php if($current_type === 'strengths'): ?>
                    <span class="badge">Value: <?= $item['points'] ?></span>
                <?php endif; ?>

                <?php if($current_type === 'characters' && !empty($item['image_url'])): ?>
                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="Portrait">
                <?php endif; ?>
                
                <p><?= htmlspecialchars($item['description']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>