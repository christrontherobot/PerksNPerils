<?php
// public/play.php
session_start();
require_once('../src/db.php');

$player_id = $_SESSION['player_id'] ?? null;
$lobby_id = $_SESSION['lobby_id'] ?? null;

// Handle Join/Create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user = htmlspecialchars($_POST['username']);
    
    if ($_POST['action'] === 'create') {
        $code = strtoupper(substr(md5(time()), 0, 6));
        $stmt = $pdo->prepare("INSERT INTO lobbies (join_code, status) VALUES (?, 'waiting') RETURNING id");
        $stmt->execute([$code]);
        $lobby_id = $stmt->fetchColumn();
    } else {
        $code = strtoupper($_POST['join_code']);
        $stmt = $pdo->prepare("SELECT id FROM lobbies WHERE join_code = ?");
        $stmt->execute([$code]);
        $lobby_id = $stmt->fetchColumn();
        if (!$lobby_id) die("Invalid Join Code. <a href='play.php'>Try again</a>");
    }

    $stmt = $pdo->prepare("INSERT INTO players (lobby_id, username) VALUES (?, ?) RETURNING id");
    $stmt->execute([$lobby_id, $user]);
    $_SESSION['player_id'] = $stmt->fetchColumn();
    $_SESSION['lobby_id'] = $lobby_id;
    header("Location: play.php"); exit;
}

// Fetch Current State
$game = null;
$me = null;
if ($lobby_id && $player_id) {
    $game = $pdo->query("SELECT * FROM lobbies WHERE id = $lobby_id")->fetch(PDO::FETCH_ASSOC);
    $me = $pdo->query("SELECT * FROM players WHERE id = $player_id")->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perks n' Perils | Play</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        async function checkStatus() {
            const res = await fetch('game_status.php');
            const data = await res.json();
            if (data.should_reload) window.location.reload();
            document.getElementById('live-status').innerText = data.status_text;
        }
        setInterval(checkStatus, 3000);
    </script>
</head>
<body>
    <div class="game-container">
        <?php if (!$lobby_id): ?>
            <div class="card-editor">
                <h2>Perks n' Perils</h2>
                <form method="POST">
                    <input type="text" name="username" placeholder="Your Name" required>
                    <input type="text" name="join_code" placeholder="Join Code (6 digits)">
                    <button type="submit" name="action" value="create">Create New Lobby</button>
                    <button type="submit" name="action" value="join">Join Existing</button>
                </form>
            </div>
        <?php else: ?>
            <nav>
                <strong id="live-status">Lobby: <?= $game['join_code'] ?></strong>
                <a href="play.php" style="margin-left:auto; color:var(--secondary);">Refresh</a>
            </nav>

            <?php if ($game['status'] === 'waiting'): ?>
                <div class="card-editor">
                    <h3>Waiting Room</h3>
                    <p>Share code <strong><?= $game['join_code'] ?></strong> with a friend.</p>
                    <a href="actions.php?do=start" class="button" style="display:block; text-align:center; text-decoration:none;">Start Round</a>
                </div>

            <?php elseif ($game['status'] === 'picking' && !$me['has_submitted']): ?>
                <?php
                $chars = $pdo->query("SELECT * FROM characters ORDER BY RANDOM() LIMIT 3")->fetchAll();
                $perks = $pdo->query("SELECT * FROM strengths ORDER BY RANDOM() LIMIT 3")->fetchAll();
                ?>
                <form action="actions.php?do=submit" method="POST" class="card-editor">
                    <h3>Select Your Hero</h3>
                    <?php foreach($chars as $c): ?>
                        <label class="item-card" style="margin-bottom:10px; cursor:pointer;">
                            <input type="radio" name="char" value="<?= $c['id'] ?>" required> <?= $c['description'] ?>
                        </label>
                    <?php endforeach; ?>
                    <h3>Select Your Perk</h3>
                    <?php foreach($perks as $p): ?>
                        <label class="item-card" style="margin-bottom:10px; cursor:pointer;">
                            <input type="radio" name="str" value="<?= $p['id'] ?>" required> <?= $p['description'] ?> (+<?= $p['points'] ?>)
                        </label>
                    <?php endforeach; ?>
                    <button type="submit">Lock In Choices</button>
                </form>
            <?php else: ?>
                <div class="card-editor">
                    <h3>Waiting for other player...</h3>
                    <p>You have submitted your hero. The game will advance once everyone is ready.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>