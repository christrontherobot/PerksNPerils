<?php
require_once('../src/db.php');

// Helper function to get one random row from a table
function getRandomCard($pdo, $table) {
    $stmt = $pdo->query("SELECT * FROM $table ORDER BY RANDOM() LIMIT 1");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Deal the hand
$character = getRandomCard($pdo, 'characters');
$strength  = getRandomCard($pdo, 'strengths');
$weakness  = getRandomCard($pdo, 'weaknesses');
$situation = getRandomCard($pdo, 'situations');

// Fallback if database is empty
if (!$character) {
    die("The deck is empty! Go to /edit to add some cards first.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perks n' Perils | Play</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .game-container { max-width: 600px; margin: 0 auto; text-align: center; }
        .hero-card { 
            background: #2d2d2d; 
            border: 4px solid var(--primary); 
            border-radius: 20px; 
            padding: 20px; 
            margin-top: 20px;
            box-shadow: 0 0 30px rgba(187, 134, 252, 0.2);
        }
        .hero-img { width: 100%; border-radius: 15px; margin-bottom: 20px; }
        .trait { margin: 15px 0; padding: 15px; border-radius: 10px; background: #1a1a1a; }
        .trait.perk { border-left: 5px solid var(--secondary); }
        .trait.peril { border-left: 5px solid var(--error); }
        .deal-btn { 
            background: var(--secondary); 
            font-size: 1.2rem; 
            padding: 20px; 
            margin-top: 30px; 
            box-shadow: 0 5px 15px rgba(3, 218, 198, 0.3);
        }
    </style>
</head>
<body>

<div class="game-container">
    <h1>Perks n' Perils</h1>
    
    <div class="hero-card">
        <?php if(!empty($character['image_url'])): ?>
            <img src="<?= htmlspecialchars($character['image_url']) ?>" class="hero-img">
        <?php endif; ?>

        <h2><?= htmlspecialchars($character['description']) ?></h2>
        
        <div class="trait perk">
            <strong>THE PERK (+<?= $strength['points'] ?>):</strong>
            <p><?= htmlspecialchars($strength['description']) ?></p>
        </div>

        <div class="trait peril">
            <strong>THE PERIL:</strong>
            <p><?= htmlspecialchars($weakness['description']) ?></p>
        </div>

        <div class="situation">
            <p><em>Locked in a struggle:</em><br> 
            <strong><?= htmlspecialchars($situation['description']) ?></strong></p>
        </div>
    </div>

    <button class="deal-btn" onclick="window.location.reload();">NEW ROUND</button>
</div>

</body>
</html>