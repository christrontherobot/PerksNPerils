<?php
session_start();
require_once('../src/db.php');

// 1. Handle Join/Create
if (isset($_POST['action'])) {
    if ($_POST['action'] === 'create') {
        $code = strtoupper(substr(md5(time()), 0, 6));
        $stmt = $pdo->prepare("INSERT INTO lobbies (join_code, status) VALUES (?, 'waiting') RETURNING id");
        $stmt->execute([$code]);
        $lobby_id = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("INSERT INTO players (lobby_id, username) VALUES (?, ?) RETURNING id");
        $stmt->execute([$lobby_id, $_POST['username']]);
        $_SESSION['player_id'] = $stmt->fetchColumn();
        $_SESSION['lobby_id'] = $lobby_id;
    }
}

// 2. Fetch Game State
$lobby_id = $_SESSION['lobby_id'] ?? null;
$player_id = $_SESSION['player_id'] ?? null;

if ($lobby_id) {
    $lobby = $pdo->prepare("SELECT * FROM lobbies WHERE id = ?");
    $lobby->execute([$lobby_id]);
    $game = $lobby->fetch(PDO::FETCH_ASSOC);

    $players = $pdo->prepare("SELECT * FROM players WHERE lobby_id = ?");
    $players->execute([$lobby_id]);
    $all_players = $players->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Perks n' Perils | Live</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="game-container">
        <?php if (!$lobby_id): ?>
            <div class="card-editor">
                <h2>Join Perks n' Perils</h2>
                <form method="POST">
                    <input type="text" name="username" placeholder="Username" required>
                    <input type="text" name="join_code" placeholder="Join Code (Leave blank to Create)">
                    <button type="submit" name="action" value="create">Enter Lobby</button>
                </form>
            </div>
        <?php else: ?>
            <div class="nav">
                <strong>CODE: <?= $game['join_code'] ?></strong> | 
                Status: <?= ucfirst($game['status']) ?>
            </div>

            <?php if ($game['status'] === 'waiting'): ?>
                <h3>Waiting for players... (<?= count($all_players) ?>/2)</h3>
                <?php if (count($all_players) >= 2): ?>
                    <button onclick="/* Logic to set status to 'picking' */">Start Game</button>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($game['status'] === 'picking'): ?>
                <form method="POST">
                    <h3>Choose your Hero and Perk</h3>
                    <button type="submit">Lock In</button>
                </form>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>