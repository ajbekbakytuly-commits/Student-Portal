<?php

try {
    $pdo = new PDO(
        'mysql:host=127.0.0.1;port=3306;dbname=college_db;charset=utf8mb4',
        'root',
        ''
    );

    echo 'MySQL-ға қосылу сәтті!';

} catch (PDOException $e) {
    echo 'Қате: ' . $e->getMessage();
}