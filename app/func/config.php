<?php
// Настройки подключения к базе данных
define('DB_HOST', 'localhost'); // Хост базы данных (на sweb уточни)
define('DB_USER', '_top'); // Пользователь базы данных (замени)
define('DB_PASS', 'pass'); // Пароль базы данных (замени)
define('DB_NAME', '_top'); // Имя базы данных (замени)

//---Init
$MainCfg = array(
    'domain' => "https://".$_SERVER['HTTP_HOST'],
);

// Подключение к базе данных
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Ошибка подключения к базе данных: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// Общие константы
// ИСПРАВЛЕНО: Используем HTTPS, так как вы подтвердили его использование
define('SITE_URL', 'https://toppyc.ru'); // URL сайта (должен соответствовать протоколу, используемому сайтом)
define('SCREENSHOT_PATH', __DIR__ . '/screenshots/'); // Путь для скриншотов
?>