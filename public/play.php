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
$situation_text = ($game && $game['current_situation_id']) ? $pdo->query("SELECT description FROM situations WHERE id = ".(int)$game['current_situation_id'])->fetchColumn() : "";

$my_heroes = [];
$my_perks = [];
if ($me && $me['draft_heroes'] && $me['draft_perks']) {
    $my_heroes = $pdo->query("SELECT * FROM characters WHERE id IN ({$me['draft_heroes']})")->fetchAll();
    $my_perks = $pdo->query("SELECT * FROM strengths WHERE id IN ({$me['draft_perks']})")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="/img/favicon.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Perks n' Perils</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        async function sync() {
            try {
                const r = await fetch('game_status.php');
                const d = await r.json();
                if (d.should_reload) { window.location.reload(); }
            } catch (e) {}
        }
        setInterval(sync, 2000);
    </script>
</head>
<body>

<img src="img/blue_hand.png" class="chaos-cards p-blue-corner">
<img src="img/red_hand.png" class="chaos-cards p-red-corner">

<div class="game-container">
    <img src="img/logo.png" alt="Perks n' Perils" class="logo-big">

    <?php if (!$lobby_id || !$me): ?>
        <div class="card-editor">
            <form method="POST" class="auth-form">
                <input type="text" name="username" placeholder="Nickname" required>
                <input type="text" name="join_code" placeholder="Join Code">
                <button type="submit" name="action" value="create" style="background:var(--sketch-red); color:white;">Host Game</button>
                <button type="submit" name="action" value="join">Join Game</button>
            </form>
        </div>
    <?php else: ?>
        
        <?php if ($game['status'] === 'waiting'): ?>
            <div style="width:100%; max-width:450px; display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:10px;">
                <strong class="lobby-code-display">CODE: <?= htmlspecialchars($game['join_code']) ?></strong>
                <a href="actions.php?do=leave" class="leave-btn">LEAVE</a>
            </div>
            
            <div class="card-editor">
                <?php if(count($all_players) >= 2): ?>
                    <h2 style="color: green;">LOBBY READY</h2>
                <?php else: ?>
                    <h2>WAITING FOR PLAYERS</h2>
                <?php endif; ?>

                <h3>Players (<?= count($all_players) ?>/6)</h3>
                <div style="margin: 20px 0; width: 100%;">
                    <?php foreach($all_players as $p): ?>
                        <div class="leaderboard-item">
                            <span><?= htmlspecialchars($p['username']) ?></span>
                            <span><?= ($p['id'] == $all_players[0]['id']) ? '⭐' : '' ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($is_host): ?>
                    <?php if(count($all_players) >= 2): ?>
                        <a href="actions.php?do=start" class="button">START GAME</a>
                    <?php else: ?>
                        <p style="opacity: 0.6;">Need at least 2 players...</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="badge" style="font-size: 1.2rem; width: 80%;">Waiting for host...</div>
                <?php endif; ?>
            </div>

        <?php elseif ($game['status'] === 'picking'): ?>
            <nav>
                <strong class="lobby-code-display">CODE: <?= htmlspecialchars($game['join_code']) ?></strong>
                <a href="actions.php?do=leave" class="leave-btn">LEAVE</a>
            </nav>
            <div class="badge"><strong>SITUATION:</strong><br><?= htmlspecialchars($situation_text) ?></div>

            <?php if (!$me['has_submitted']): ?>
                <form action="actions.php?do=submit" method="POST" style="width:100%; display:flex; flex-direction:column; align-items:center;">
                    <h2 class="section-header">PICK YOUR CHARACTER</h2>
                    <div class="item-list">
                        <?php foreach($my_heroes as $c): ?>
                            <label class="item-card">
                                <input type="radio" name="char" value="<?= $c['id'] ?>" required>
                                <?php if(!empty($c['image_url'])): ?><img src="<?= htmlspecialchars($c['image_url']) ?>"><?php endif; ?>
                                <strong><?= htmlspecialchars($c['description']) ?></strong>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <h2 class="section-header">PICK YOUR PERK</h2>
                    <div class="item-list">
                        <?php foreach($my_perks as $s): ?>
                            <label class="item-card perk-card">
                                <input type="radio" name="str" value="<?= $s['id'] ?>" required>
                                <p><?= htmlspecialchars($s['description']) ?> (+<?= $s['points'] ?> pts)</p>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="button">LOCK IN</button>
                </form>
            <?php else: ?>
                <div class="card-editor"><h2>Locked In!</h2><p>Waiting for the rest of the chaos...</p></div>
            <?php endif; ?>

        <?php elseif ($game['status'] === 'voting'): ?>
            <nav>
                <strong class="lobby-code-display">CODE: <?= htmlspecialchars($game['join_code']) ?></strong>
                <a href="actions.php?do=leave" class="leave-btn">LEAVE</a>
            </nav>
            <div class="badge"><strong>SITUATION:</strong><br><?= htmlspecialchars($situation_text) ?></div>

            <div class="item-list">
                <?php 
                $stmt = $pdo->prepare("SELECT p.*, c.description as c_d, c.image_url as c_img, s.description as s_d, s.points, w.description as w_d FROM players p JOIN characters c ON p.char_id = c.id JOIN strengths s ON p.strength_id = s.id JOIN weaknesses w ON p.weakness_id = w.id WHERE p.lobby_id = ?");
                $stmt->execute([$lobby_id]);
                $players = $stmt->fetchAll();
                $pCount = count($players);

                foreach($players as $p): ?>
                    <div class="item-card vote-card">
                        <strong style="font-size: 1.8rem; border-bottom: 3px solid black; width: 100%; margin-bottom: 10px;">
                            <?= htmlspecialchars($p['username']) ?>
                        </strong>
                        
                        <?php if(!empty($p['c_img'])): ?>
                            <img src="<?= htmlspecialchars($p['c_img']) ?>" style="height:180px; width: 100%; object-fit:cover; border:2px solid black; margin-bottom:15px;">
                        <?php endif; ?>

                        <div class="vote-info-block">
                            <span class="vote-label">HERO</span>
                            <span class="vote-value"><?= htmlspecialchars($p['c_d']) ?></span>
                        </div>

                        <div class="vote-info-block" style="color:green;">
                            <span class="vote-label">PERK</span>
                            <span class="vote-value"><?= htmlspecialchars($p['s_d']) ?></span>
                        </div>

                        <div class="vote-info-block" style="color:var(--sketch-red);">
                            <span class="vote-label">PERIL</span>
                            <span class="vote-value"><?= htmlspecialchars($p['w_d']) ?></span>
                        </div>

                        <?php if (!$me['voted_for_id']): ?>
                            <?php if ($p['id'] != $player_id || $pCount == 2): ?>
                                <a href="actions.php?do=vote&target=<?= $p['id'] ?>" class="button vote-btn">VOTE</a>
                            <?php else: ?>
                                <p style="font-size:0.9rem; opacity:0.6;">(Your Card)</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($game['status'] === 'result'): ?>
            <div class="card-editor">
                <h2>LEADERBOARD</h2>
                <div style="width:100%;">
                    <?php foreach($all_players as $r): ?>
                        <div class="leaderboard-item">
                            <span><?= htmlspecialchars($r['username']) ?></span>
                            <span><?= $r['score'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($is_host): ?>
                    <a href="actions.php?do=start" class="button">NEXT ROUND</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>