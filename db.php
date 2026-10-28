<?php
// Деректер қорына қосылу (PDO). XAMPP: пароль әдетте бос.
const DB_HOST = 'localhost';
const DB_USER = 'root';
const DB_PASS = '';
const DB_NAME = 'student_portal';

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $ex) {
    die('Деректер қорына қосылу қатесі: ' . htmlspecialchars($ex->getMessage()));
}

function q(string $sql, array $p = []): PDOStatement {
    global $pdo;
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st;
}
