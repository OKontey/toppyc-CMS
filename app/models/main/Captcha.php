<?
//---model
@session_start();

// Генерация случайного кода (4 цифры)
$code = rand(1000, 9999);
$_SESSION['captcha'] = $code;

// Создание изображения
$image = imagecreatetruecolor(100, 40);
$bg_color = imagecolorallocate($image, 220, 220, 220); // Светло-серый фон
$text_color = imagecolorallocate($image, 50, 50, 50); // Тёмно-серый текст
imagefill($image, 0, 0, $bg_color);

// Добавление случайных линий для помех
for ($i = 0; $i < 5; $i++) {
    $line_color = imagecolorallocate($image, rand(150, 200), rand(150, 200), rand(150, 200));
    imageline($image, rand(0, 100), rand(0, 40), rand(0, 100), rand(0, 40), $line_color);
}

// Добавление текста
imagestring($image, 5, 30, 10, $code, $text_color);

// Вывод изображения
header('Content-Type: image/png');
$capth = imagepng($image);
imagedestroy($image);
?>