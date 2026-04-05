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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perks n' Perils</title>
    <link rel="stylesheet" href="css/style.css">
    <script>
        async function sync() {
            try {
                const r = await fetch('game_status.php');
                const d = await r.json();
                if (d.should_reload) {
                    // Save music time before reload
                    const audio = document.getElementById('bgm');
                    if (audio) localStorage.setItem('bgm_time', audio.currentTime);
                    window.location.reload();
                }
            } catch (e) {}
        }
        setInterval(sync, 2000);

        function startMusic() {
            const audio = document.getElementById('bgm');
            if (audio && audio.paused) {
                const savedTime = localStorage.getItem('bgm_time');
                if (savedTime) audio.currentTime = parseFloat(savedTime);
                audio.play().catch(e => console.log("Audio play blocked."));
                localStorage.setItem('bgm_playing', 'true');
            }
        }

        window.onload = () => {
            if (localStorage.getItem('bgm_playing') === 'true') {
                startMusic();
            }
        }
    </script>
</head>
<body onclick="startMusic()">

<audio id="bgm" loop>
    <source src="audio/chaos_theme.mp3" type="audio/mpeg">
</audio>

<img src="img/red_hand.png" class="chaos-cards p-red-corner" alt="">
<img src="img/blue_hand.png" class="chaos-cards p-blue-corner" alt="">

<div class="game-container">
    <?php if (!$lobby_id || !$me): ?>
        <img src="img/logo.png" alt="Perks n' Perils" class="logo-big">
        
        <div class="card-editor">
            <h2>Hands of Chaos</h2>
            <form method="POST" class="auth-form">
                <input type="text" name="username" placeholder="Nickname" required>
                <input type="text" name="join_code" placeholder="Join Code (Optional)">
                <button type="submit" name="action" value="create" style="background:var(--sketch-red); color:white;">Host Game</button>
                <button type="submit" name="action" value="join">Join Game</button>
            </form>
        </div>
    <?php else: ?>
        <nav>
            <strong>Lobby: <?= htmlspecialchars($game['join_code'] ?? '') ?></strong>
            <a href="actions.php?do=leave" class="leave-btn">LEAVE</a>
        </nav>

        <?php if ($game['status'] !== 'waiting'): ?>
            <div class="badge">
                <strong>SITUATION:</strong><br><?= htmlspecialchars($situation_text) ?>
            </div>
        <?php endif; ?>

        <?php if ($game['status'] === 'waiting'): ?>
            <div class="card-editor">
                <h2>LOBBY READY</h2>
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
                        <a href="actions.php?do=start" class="button" style="text-decoration:none;">START GAME</a>
                    <?php else: ?>
                        <p>Need at least 2 players to start...</p>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="badge" style="clip-path: none; font-size: 1rem;">Waiting for host...</div>
                <?php endif; ?>
            </div>

        <?php elseif ($game['status'] === 'picking'): ?>
            <?php if (!$me['has_submitted']): ?>
                <form action="actions.php?do=submit" method="POST">
                    <h3 class="section-title">Choose Hero</h3>
                    <div class="item-list">
                        <?php foreach($my_heroes as $c): ?>
                            <label class="item-card">
                                <input type="radio" name="char" value="<?= $c['id'] ?>" required>
                                <?php if(!empty($c['image_url'])): ?><img src="<?= htmlspecialchars($c['image_url']) ?>"><?php endif; ?>
                                <strong><?= htmlspecialchars($c['description']) ?></strong>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <h3 class="section-title">Choose Perk</h3>
                    <div class="item-list">
                        <?php foreach($my_perks as $s): ?>
                            <label class="item-card perk-card">
                                <input type="radio" name="str" value="<?= $s['id'] ?>" required>
                                <p><?= htmlspecialchars($s['description']) ?> (+<?= $s['points'] ?>)</p>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align:center;">
                        <button type="submit" class="button" style="max-width:300px;">LOCK IN</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="card-editor"><h3>Locked In</h3><p>Waiting for opponents...</p></div>
            <?php endif; ?>

        <?php elseif ($game['status'] === 'voting'): ?>
            <div class="item-list">
                <?php 
                $stmt = $pdo->prepare("SELECT p.*, c.description as c_d, c.image_url as c_img, s.description as s_d, s.points, w.description as w_d FROM players p JOIN characters c ON p.char_id = c.id JOIN strengths s ON p.strength_id = s.id JOIN weaknesses w ON p.weakness_id = w.id WHERE p.lobby_id = ?");
                $stmt->execute([$lobby_id]);
                $players = $stmt->fetchAll();
                $pCount = count($players);

                foreach($players as $p): ?>
                    <div class="item-card">
                        <strong><?= htmlspecialchars($p['username']) ?></strong>
                        <?php if(!empty($p['c_img'])): ?><img src="<?= htmlspecialchars($p['c_img']) ?>"><?php endif; ?>
                        <p>Hero: <?= htmlspecialchars($p['c_d']) ?></p>
                        <p style="color:green;">Perk: <?= htmlspecialchars($p['s_d']) ?></p>
                        <p style="color:var(--sketch-red);">Peril: <?= htmlspecialchars($p['w_d']) ?></p>
                        <?php if (!$me['voted_for_id']): ?>
                            <?php if ($p['id'] != $player_id || $pCount == 2): ?>
                                <a href="actions.php?do=vote&target=<?= $p['id'] ?>" class="button" style="text-decoration:none;">VOTE</a>
                            <?php else: ?>
                                <p style="font-size:0.8rem; opacity:0.6;">(Your Card)</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif ($game['status'] === 'result'): ?>
            <div class="card-editor">
                <h2>Leaderboard</h2>
                <div style="width:100%;">
                    <?php foreach($all_players as $r): ?>
                        <div class="leaderboard-item">
                            <span><?= htmlspecialchars($r['username']) ?></span>
                            <span><?= $r['score'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($is_host): ?>
                    <a href="actions.php?do=start" class="button" style="text-decoration:none; margin-top:20px;">NEXT ROUND</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>