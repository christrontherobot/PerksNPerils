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
        // We still pick a situation here, but we won't DISPLAY it yet
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
            <a href="actions.php?do=leave" class="button" style="margin-left:auto; background:var(--poke-red); text-decoration:none;">LEAVE</a>
        </nav>

        <?php if ($game['status'] !== 'waiting'): ?>
            <div class="badge">
                <strong>SITUATION:</strong><br><?= htmlspecialchars($situation_text) ?>
            </div>
        <?php endif; ?>

        <?php if ($game['status'] === 'waiting'): ?>
            <div class="card-editor">
                <h2 style="color:var(--poke-blue); margin-bottom:10px;">LOBBY READY</h2>
                <h3>Players (<?= count($all_players) ?>/6)</h3>
                <div style="margin: 20px 0; text-align: left;">
                    <?php foreach($all_players as $p): ?>
                        <div class="leaderboard-item" style="border-left: 5px solid var(--poke-red);">
                            <?= htmlspecialchars($p['username']) ?> 
                            <?= ($p['id'] == $all_players[0]['id']) ? '⭐' : '' ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($is_host): ?>
                    <?php if(count($all_players) >= 2): ?>
                        <a href="actions.php?do=start" class="button" style="display:block; text-decoration:none;">START GAME</a>
                    <?php else: ?>
                        <p style="font-size:0.8rem; color:#666;">Need at least 2 players to start...</p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="badge" style="animation: none; box-shadow: none;">Waiting for host to start...</p>
                <?php endif; ?>
            </div>

        <?php elseif ($game['status'] === 'picking'): ?>
            <?php if (!$me['has_submitted']): ?>
                <form action="actions.php?do=submit" method="POST">
                    <h3>Choose Hero</h3>
                    <div class="item-list">
                        <?php foreach($my_heroes as $c): ?>
                            <label class="item-card">
                                <input type="radio" name="char" value="<?= $c['id'] ?>" required>
                                <?php if(!empty($c['image_url'])): ?><img src="<?= htmlspecialchars($c['image_url']) ?>"><?php endif; ?>
                                <strong><?= htmlspecialchars($c['description']) ?></strong>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <h3 style="margin-top:30px;">Choose Perk</h3>
                    <div class="item-list">
                        <?php foreach($my_perks as $s): ?>
                            <label class="item-card">
                                <input type="radio" name="str" value="<?= $s['id'] ?>" required>
                                <p style="color:var(--poke-blue);"><?= htmlspecialchars($s['description']) ?> (+<?= $s['points'] ?>)</p>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" style="margin-top:30px;">LOCK IN</button>
                </form>
            <?php else: ?>
                <div class="card-editor"><h3>Locked In</h3><p>Waiting for opponents...</p></div>
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
                        <p style="color:green;">Perk: <?= htmlspecialchars($p['s_d']) ?></p>
                        <p style="color:var(--poke-red);">Peril: <?= htmlspecialchars($p['w_d']) ?></p>
                        <?php if (!$me['voted_for_id'] && $p['id'] != $player_id): ?>
                            <a href="actions.php?do=vote&target=<?= $p['id'] ?>" class="button" style="text-decoration:none; display:block; text-align:center; margin-top:10px;">VOTE</a>
                        <?php elseif ($p['id'] == $player_id): ?>
                            <p style="font-size:0.7rem; color:#888; text-align:center;">(Your Card)</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($game['status'] === 'result'): ?>
            <div class="card-editor">
                <h2>Leaderboard</h2>
                <?php foreach($all_players as $r): ?>
                    <div class="leaderboard-item"><span><?= htmlspecialchars($r['username']) ?></span><span><?= $r['score'] ?></span></div>
                <?php endforeach; ?>
                <?php if ($is_host): ?>
                    <a href="actions.php?do=start" class="button" style="display:block; text-decoration:none; margin-top:20px;">NEXT ROUND</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>