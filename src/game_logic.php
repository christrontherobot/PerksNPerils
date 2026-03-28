<?php
require_once('db.php');

function generateJoinCode() {
    return strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
}

function getPlayerHand($pdo) {
    return [
        'chars' => $pdo->query("SELECT * FROM characters ORDER BY RANDOM() LIMIT 3")->fetchAll(PDO::FETCH_ASSOC),
        'perks' => $pdo->query("SELECT * FROM strengths ORDER BY RANDOM() LIMIT 3")->fetchAll(PDO::FETCH_ASSOC)
    ];
}