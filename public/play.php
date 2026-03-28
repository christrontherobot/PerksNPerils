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
        if (!$lobby_id) die("Invalid Code. <a href='play.php'>Back</a>");
    }
    $stmt = $pdo->prepare("INSERT INTO players (lobby_id, username) VALUES (?, ?) RETURNING id");
    $stmt->execute([$lobby_id, $user]);
    $_SESSION['player_id'] = $stmt->fetchColumn();
    $_SESSION['lobby_id'] = $lobby_id;
    header("Location: play.php"); exit;
}

// State Fetching
$game = $lobby_id ? $pdo->query("SELECT * FROM lobbies WHERE id = $lobby_id")->fetch(PDO::FETCH_ASSOC) : null;
$me = $player_id ? $pdo->query("SELECT * FROM players WHERE id = $player_id")->fetch(PDO::FETCH_ASSOC) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perks n' Perils</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        async function sync() {
            const r = await fetch('game_status.php');
            const d = await r.json();
            if (d.should_reload) window.location.reload();
        }
        setInterval(sync, 3000);
    </script>
</head>
<body>
<div class="game-container">
    <?php if (!$lobby_id): ?>
        <div class="card-editor">
            <h2>Perks n' Perils</h2>
            <form method="POST">
                <input type="text" name="username" placeholder="Name" required>
                <input type="text" name="join_code" placeholder="Join Code">
                <button type="submit" name="action" value="create">Host Game</button>
                <button type="submit" name="action" value="join">Join Game</button>
            </form>
        </div>
    <?php else: ?>
        <nav>
            <strong>Lobby: <?= $game['join_code'] ?></strong>
            <span>Status: <?= strtoupper($game['status']) ?></span>
        </nav>

        <?php if ($game['status'] === 'waiting'): ?>
            <div class="card-editor">
                <h3>Waiting Room</h3>
                <p>Send code <strong><?= $game['join_code'] ?></strong> to your opponent.</p>
                <a href="actions.php?do=start" class="button" style="display:block; text-align:center; text-decoration:none;">Start Game</a>
            </div>

        <?php elseif ($game['status'] === 'picking' && !$me['has_submitted']): ?>
            <form action="actions.php?do=submit" method="POST">
                <h3>Choose 1 Hero</h3>
                <div class="item-list">
                    <?php foreach($pdo->query("SELECT * FROM characters ORDER BY RANDOM() LIMIT 3") as $c): ?>
                        <label class="item-card"><input type="radio" name="char" value="<?= $c['id'] ?>" required> <?= htmlspecialchars($c['description']) ?></label>
                    <?php endforeach; ?>
                </div>
                <h3>Choose 1 Perk</h3>
                <div class="item-list">
                    <?php foreach($pdo->query("SELECT * FROM strengths ORDER BY RANDOM() LIMIT 3") as $s): ?>
                        <label class="item-card"><input type="radio" name="str" value="<?= $s['id'] ?>" required> <?= htmlspecialchars($s['description']) ?> (+<?= $s['points'] ?>)</label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" style="margin-top:20px;">Lock In</button>
            </form>

        <?php elseif ($game['status'] === 'voting'): ?>
            <?php
            $stmt = $pdo->prepare("SELECT p.*, c.description as c_d, s.description as s_d, s.points, w.description as w_d FROM players p JOIN characters c ON p.char_id = c.id JOIN strengths s ON p.strength_id = s.id JOIN weaknesses w ON p.weakness_id = w.id WHERE p.lobby_id = ?");
            $stmt->execute([$lobby_id]);
            $players = $stmt->fetchAll();
            $sit = $pdo->query("SELECT description FROM situations WHERE id = ".(int)$game['current_situation_id'])->fetchColumn();
            ?>
            <div class="badge" style="display:block; text-align:center; padding:15px; margin-bottom:20px;">SITUATION: <?= htmlspecialchars($sit) ?></div>
            <div class="item-list">
                <?php foreach($players as $p): ?>
                    <div class="item-card">
                        <strong><?= htmlspecialchars($p['username']) ?></strong>
                        <p>Hero: <?= htmlspecialchars($p['c_d']) ?></p>
                        <p style="color:var(--secondary);">Perk: <?= htmlspecialchars($p['s_d']) ?> (+<?= $p['points'] ?>)</p>
                        <p style="color:var(--error);">Peril: <?= htmlspecialchars($p['w_d']) ?></p>
                        <?php if ($p['id'] != $player_id && !$me['voted_for_id']): ?>
                            <a href="actions.php?do=vote&target=<?= $p['id'] ?>" class="button" style="text-decoration:none; display:block; text-align:center;">Vote</a>
                        <?php elseif ($me['voted_for_id']): ?>
                            <p><em>Vote Cast</em></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($game['status'] === 'result' || $me['has_submitted']): ?>
            <div class="card-editor" style="text-align:center;">
                <?php if ($game['status'] === 'result'): ?>
                    <h2>Scoreboard</h2>
                    <?php foreach($pdo->query("SELECT username, score FROM players WHERE lobby_id = $lobby_id ORDER BY score DESC") as $r): ?>
                        <p><?= $r['username'] ?>: <?= $r['score'] ?> pts</p>
                    <?php endforeach; ?>
                    <a href="actions.php?do=start" class="button" style="text-decoration:none; display:block;">Next Round</a>
                <?php else: ?>
                    <p>Waiting for opponent...</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>