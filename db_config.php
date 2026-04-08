<?php
/**
 * Конфигурация подключения к базе данных
 * Соблюдение принципа DRY - единая точка подключения
 */

// Параметры подключения
$user = 'u82278';
$pass = '3700374';

try {
    $db = new PDO('mysql:host=localhost;dbname=u82278', $user, $pass,
        [PDO::ATTR_PERSISTENT => true, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}
?>