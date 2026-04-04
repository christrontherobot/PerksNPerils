<?php
// public/play.php
session_start();
require_once('../src/db.php');

$player_id = $_SESSION['player_id'] ?? null;
$lobby_id = $_SESSION['lobby_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user = htmlspecialchars($_POST['username']);
    if ($_POST['action'] === 'create') {
        $code = strtoupper(substr(md5(time()), 0, 6));
        $sit = $pdo->query("SELECT id FROM situations ORDER BY RANDOM() LIMIT 1")->fetchColumn();
        $stmt = $pdo->prepare("INSERT INTO lobbies (join_code, status, current_situation_id) VALUES (?, 'waiting', ?) RETURNING id");
        $stmt->execute([$code, $sit]);
        $lobby_id = $stmt->fetchColumn();
    } else {
        $code = strtoupper($_POST['join_code']);
        $stmt = $pdo->prepare("SELECT id FROM lobbies WHERE join_code = ?");
        $stmt->execute([$code]);
        $lobby_id = $stmt->fetchColumn();
    }
    
    if ($lobby_id) {
        $count = $pdo->query("SELECT COUNT(*) FROM players WHERE lobby_id = $lobby_id")->fetchColumn();
        if ($count >= 6) { die("Lobby is full. <a href='play.php'>Back</a>"); }
        
        $stmt = $pdo->prepare("INSERT INTO players (lobby_id, username) VALUES (?, ?) RETURNING id");
        $stmt->execute([$lobby_id, $user]);
        $_SESSION['player_id'] = $stmt->fetchColumn();
        $_SESSION['lobby_id'] = $lobby_id;
        header("Location: play.php"); exit;
    }
}

$game = $lobby_id ? $pdo->query("SELECT * FROM lobbies WHERE id = $lobby_id")->fetch(PDO::FETCH_ASSOC) : null;
$me = $player_id ? $pdo->query("SELECT * FROM players WHERE id = $player_id")->fetch(PDO::FETCH_ASSOC) : null;
$all_players = $lobby_id ? $pdo->query("SELECT * FROM players WHERE lobby_id = $lobby_id ORDER BY id ASC")->fetchAll() : [];
$is_host = ($all_players && $all_players[0]['id'] == $player_id);
$situation_text = ($game && $game['current_situation_id']) ? $pdo->query("SELECT description FROM situations WHERE id = ".(int)$game['current_situation_id'])->fetchColumn() : "Waiting...";

// --- SELECTION LOGIC: ROLL HAND ONCE PER ROUND ---
if ($game && $game['status'] === 'picking' && !$me['has_submitted']) {
    if (!isset($_SESSION['my_hero_options'])) {
        $_SESSION['my_hero_options'] = $pdo->query("SELECT * FROM characters ORDER BY RANDOM() LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    }
    if (!isset($_SESSION['my_perk_options'])) {
        $_SESSION['my_perk_options'] = $pdo->query("SELECT * FROM strengths ORDER BY RANDOM() LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    }
}
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
            try {
                const r = await fetch('game_status.php');
                const d = await r.json();
                if (d.should_reload) window.location.reload();
            } catch (e) {}
        }
        setInterval(sync, 2000);
    </script>
</head>
<body>
<div class="game-container">
    <?php if (!$lobby_id || !$me): ?>
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
            <strong>Lobby: <?= htmlspecialchars($game['join_code'] ?? '') ?></strong>
            <a href="actions.php?do=leave" style="margin-left:auto; color:var(--poke-yellow); text-decoration:none; font-size:0.8rem;">LEAVE</a>
        </nav>

        <div class="badge">
            <strong>CURRENT SITUATION:</strong><br><?= htmlspecialchars($situation_text) ?>
        </div>

        <?php if ($game['status'] === 'waiting'): ?>
            <div class="card-editor" style="text-align:center;">
                <h3>Lobby (<?= count($all_players) ?>/6)</h3>
                <?php if ($is_host && count($all_players) >= 2): ?>
                    <a href="actions.php?do=start" class="button" style="display:block; text-decoration:none;">START GAME</a>
                <?php elseif (count($all_players) < 2): ?>
                    <p>Waiting for at least one more player...</p>
                <?php else: ?>
                    <p>Waiting for host to start...</p>
                <?php endif; ?>
            </div>

        <?php elseif ($game['status'] === 'picking'): ?>
            <?php if (!$me['has_submitted']): ?>
                <form action="actions.php?do=submit" method="POST">
                    <h3>Choose Hero</h3>
                    <div class="item-list">
                        <?php foreach($_SESSION['my_hero_options'] as $c): ?>
                            <label class="item-card">
                                <input type="radio" name="char" value="<?= $c['id'] ?>" required>
                                <?php if(!empty($c['image_url'])): ?><img src="<?= htmlspecialchars($c['image_url']) ?>"><?php endif; ?>
                                <strong><?= htmlspecialchars($c['description']) ?></strong>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <h3 style="margin-top:30px;">Choose Perk</h3>
                    <div class="item-list">
                        <?php foreach($_SESSION['my_perk_options'] as $s): ?>
                            <label class="item-card">
                                <input type="radio" name="str" value="<?= $s['id'] ?>" required> 
                                <p style="color:var(--poke-blue);"><?= htmlspecialchars($s['description']) ?> (+<?= $s['points'] ?>)</p>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" style="margin-top:30px;">LOCK IN</button>
                </form>
            <?php else: ?>
                <div class="card-editor" style="text-align:center;">
                    <h3>Locked In</h3>
                    <p>Waiting for others to finish their hand...</p>
                </div>
            <?php endif; ?>

        <?php elseif ($game['status'] === 'voting'): ?>
            <div class="item-list">
                <?php 
                $stmt = $pdo->prepare("SELECT p.*, c.description as c_d, c.image_url as c_img, s.description as s_d, s.points, w.description as w_d FROM players p JOIN characters c ON p.char_id = c.id JOIN strengths s ON p.strength_id = s.id JOIN weaknesses w ON p.weakness_id = w.id WHERE p.lobby_id = ?");
                $stmt->execute([$lobby_id]);
                foreach($stmt->fetchAll() as $p): ?>
                    <div class="item-card">
                        <strong><?= htmlspecialchars($p['username']) ?></strong>
                        <?php if(!empty($p['c_img'])): ?><img src="<?= htmlspecialchars($p['c_img']) ?>"><?php endif; ?>
                        <p>Hero: <?= htmlspecialchars($p['c_d']) ?></p>
                        <p style="color:green;">Perk: <?= htmlspecialchars($p['s_d']) ?> (+<?= $p['points'] ?>)</p>
                        <p style="color:var(--poke-red);">Peril: <?= htmlspecialchars($p['w_d']) ?></p>
                        <?php if (!$me['voted_for_id']): ?>
                            <a href="actions.php?do=vote&target=<?= $p['id'] ?>" class="button" style="text-decoration:none; display:block; text-align:center; margin-top:10px;">VOTE</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($game['status'] === 'result'): ?>
            <div class="card-editor" style="text-align:center;">
                <h2>Leaderboard</h2>
                <?php foreach($all_players as $r): ?>
                    <div class="leaderboard-item">
                        <span><?= htmlspecialchars($r['username']) ?></span>
                        <span><?= $r['score'] ?> pts</span>
                    </div>
                <?php endforeach; ?>
                <?php if ($is_host): ?>
                    <a href="actions.php?do=start" class="button" style="display:block; text-decoration:none; margin-top:20px;">NEXT ROUND</a>
                <?php else: ?>
                    <p>Waiting for host to start next round...</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>