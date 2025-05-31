<?php
@session_start();
require_once $_SERVER['DOCUMENT_ROOT'].'/app/func/config.php';

// Отключение кэширования изображения
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Проверка, передан ли ID сайта
if (!isset($_GET['site_id']) || !is_numeric($_GET['site_id'])) {
    http_response_code(400);
    exit;
}

$site_id = (int)$_GET['site_id'];

// Проверка, существует ли сайт
$result = $conn->query("SELECT id FROM sites WHERE id = $site_id AND approved = 1");
if ($result->num_rows === 0) {
    http_response_code(404);
    exit;
}

// Получение статистики за сегодня
$today = date('Y-m-d');
$result = $conn->query("SELECT views, unique_visitors FROM stats WHERE site_id = $site_id AND date = '$today'");
if ($result->num_rows > 0) {
    $stats = $result->fetch_assoc();
    $views = (int)$stats['views'];
    $unique_visitors = (int)$stats['unique_visitors'];
} else {
    $views = 0;
    $unique_visitors = 0;
}

// Регистрация просмотра и уникального посетителя
$visitor_ip = $_SERVER['REMOTE_ADDR'];
$session_key = "visited_site_$site_id";

// Проверяем, посещал ли пользователь сайт в этой сессии
$is_unique = !isset($_SESSION[$session_key]);

$result = $conn->query("SELECT id FROM stats WHERE site_id = $site_id AND date = '$today'");
if ($result->num_rows > 0) {
    // Запись существует, обновляем
    $row = $result->fetch_assoc();
    $stat_id = $row['id'];
    
    // Проверяем, был ли этот IP уже учтён
    $ip_already_counted = $conn->query("SELECT 1 FROM stats_visitors WHERE stat_id = $stat_id AND ip_address = '$visitor_ip'")->num_rows > 0;
    
    if ($is_unique && !$ip_already_counted) {
        $conn->query("UPDATE stats SET views = views + 1, unique_visitors = unique_visitors + 1 WHERE id = $stat_id");
        $conn->query("INSERT INTO stats_visitors (stat_id, ip_address) VALUES ($stat_id, '$visitor_ip')");
        $views++;
        $unique_visitors++;
        $_SESSION[$session_key] = true; // Отмечаем, что пользователь уже посещал сайт
    } else {
        $conn->query("UPDATE stats SET views = views + 1 WHERE id = $stat_id");
        $views++;
    }
} else {
    // Создаём новую запись
    $conn->query("INSERT INTO stats (site_id, date, views, unique_visitors) VALUES ($site_id, '$today', 1, 1)");
    $stat_id = $conn->insert_id;
    $conn->query("INSERT INTO stats_visitors (stat_id, ip_address) VALUES ($stat_id, '$visitor_ip')");
    $views = 1;
    $unique_visitors = 1;
    $_SESSION[$session_key] = true;
}

// Обновляем общее количество просмотров в таблице sites
$conn->query("UPDATE sites SET views = views + 1 WHERE id = $site_id");

// Генерация изображения
$width = 88;
$height = 31;
$image = imagecreatetruecolor($width, $height);

// Создание градиентного фона (светло-голубой -> светло-серый)
for ($x = 0; $x < $width; $x++) {
    $r = 230 - ($x / $width) * (230 - 249);  // #e6f0fa (230, 240, 250) -> #f9fafc (249, 250, 252)
    $g = 240 - ($x / $width) * (240 - 250);
    $b = 250 - ($x / $width) * (250 - 252);
    $color = imagecolorallocate($image, $r, $g, $b);
    imageline($image, $x, 0, $x, $height, $color);
}

// Загрузка иконок
$views_icon = imagecreatefrompng('icons/views_icon.png');
$unique_icon = imagecreatefrompng('icons/unique_icon.png');

// Позиции иконок (справа, поменяны местами)
imagecopy($image, $unique_icon, $width - 20, 2, 0, 0, 16, 16);  // Иконка уникальных (была внизу, теперь сверху)
imagecopy($image, $views_icon, $width - 20, 15, 0, 0, 16, 16);  // Иконка просмотров (была сверху, теперь внизу)

// Цвет текста (тёмно-серый для лучшей читаемости)
$text_color = imagecolorallocate($image, 51, 51, 51); // #333

// Шрифт (Arial Bold для жирности, если доступен на сервере)
$font_path = 'fonts/Arial.ttf'; // Укажи правильный путь к Arial Bold на сервере

// Текст (поменяны местами, размер шрифта уменьшен до 9)
$views_text = "$views";
$unique_text = "$unique_visitors";

// Позиции текста (слева от иконок, поменяны местами)
imagettftext($image, 9, 0, 5, 15, $text_color, $font_path, $unique_text);  // Уникальные (были внизу, теперь сверху)
imagettftext($image, 9, 0, 5, 28, $text_color, $font_path, $views_text);   // Просмотры (были сверху, теперь внизу)

// Вывод изображения
header('Content-Type: image/png');
imagepng($image);

// Освобождение памяти
imagedestroy($image);
imagedestroy($views_icon);
imagedestroy($unique_icon);

$conn->close();
?>