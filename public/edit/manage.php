<?php
require_once('../../src/db.php');

session_start();
if (!isset($_SESSION['authenticated'])) { header("Location: index.php"); exit; }

$types = ['characters', 'strengths', 'weaknesses', 'situations'];
$current_type = $_GET['type'] ?? 'characters';
$edit_id = $_GET['edit'] ?? null;
$edit_item = null;

// If we are in edit mode, fetch the specific item
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM $current_type WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Form Submission (Add OR Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['description'])) {
    $desc = $_POST['description'];
    $img = $_POST['image_url'] ?? '';
    $pts = (int)($_POST['points'] ?? 0);
    $id = $_POST['id'] ?? null;

    if ($id) {
        // UPDATE Existing
        if ($current_type === 'characters') {
            $stmt = $pdo->prepare("UPDATE characters SET description = ?, image_url = ? WHERE id = ?");
            $stmt->execute([$desc, $img, $id]);
        } elseif ($current_type === 'strengths') {
            $stmt = $pdo->prepare("UPDATE strengths SET description = ?, points = ? WHERE id = ?");
            $stmt->execute([$desc, $pts, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE $current_type SET description = ? WHERE id = ?");
            $stmt->execute([$desc, $id]);
        }
    } else {
        // INSERT New
        if ($current_type === 'characters') {
            $stmt = $pdo->prepare("INSERT INTO characters (description, image_url) VALUES (?, ?)");
            $stmt->execute([$desc, $img]);
        } elseif ($current_type === 'strengths') {
            $stmt = $pdo->prepare("INSERT INTO strengths (description, points) VALUES (?, ?)");
            $stmt->execute([$desc, $pts]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO $current_type (description) VALUES (?)");
            $stmt->execute([$desc]);
        }
    }
    header("Location: manage.php?type=$current_type");
    exit;
}

$items = $pdo->query("SELECT * FROM $current_type ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/img/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <h3><?= $edit_item ? "Edit" : "Add" ?> <?= ucfirst(substr($current_type, 0, -1)) ?></h3>
        <form method="POST">
            <?php if($edit_item): ?>
                <input type="hidden" name="id" value="<?= $edit_item['id'] ?>">
            <?php endif; ?>

            <textarea name="description" placeholder="Description..." rows="2" required><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
            
            <?php if($current_type === 'characters'): ?>
                <input type="text" name="image_url" placeholder="Image URL" value="<?= htmlspecialchars($edit_item['image_url'] ?? '') ?>">
            <?php endif; ?>

            <?php if($current_type === 'strengths'): ?>
                <input type="number" name="points" placeholder="Point Value (0-10)" min="0" max="10" value="<?= $edit_item['points'] ?? 0 ?>">
            <?php endif; ?>

            <button type="submit"><?= $edit_item ? "Update Card" : "Save Card" ?></button>
            <?php if($edit_item): ?>
                <a href="manage.php?type=<?= $current_type ?>" style="display:block; text-align:center; margin-top:10px; color:#aaa; text-decoration:none;">Cancel Edit</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="item-list">
        <?php foreach($items as $item): ?>
            <div class="item-card">
                <div class="card-actions">
                    <a href="?type=<?= $current_type ?>&edit=<?= $item['id'] ?>" class="edit-btn">✎</a>
                    <a href="delete.php?type=<?= $current_type ?>&id=<?= $item['id'] ?>" class="delete-link" onclick="return confirm('Delete?')">✖</a>
                </div>
                
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