<?php
session_start();

// Генерация случайного кода (4 цифры)
$code = rand(1000, 9999);
$_SESSION['captcha'] = $code;

// Создание изображения
$image = imagecreatetruecolor(80, 15);
$bg_color = imagecolorallocate($image, 255, 255, 255);
$text_color = imagecolorallocate($image, 50, 50, 50); // Тёмно-серый текст
imagefill($image, 0, 0, $bg_color);

// Добавление случайных линий для помех
for ($i = 0; $i < 5; $i++) {
    $line_color = imagecolorallocate($image, rand(150, 200), rand(150, 200), rand(150, 200));
    imageline($image, rand(0, 80), rand(0, 10), rand(0, 80), rand(0, 10), $line_color);
}

// Добавление текста
imagestring($image, 10, 20, 0, $code, $text_color);

// Вывод изображения
header('Content-Type: image/png');
imagepng($image);
imagedestroy($image);
?>