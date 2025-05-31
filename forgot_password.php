<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Получение списка категорий для сайдбара
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Восстановление пароля - Toppyc.ru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar_left.php'; ?>

        <div class="main-content">
            <h2>Восстановление пароля</h2>
             <div class="tab-container">
                <div class="tab active"><i class="fas fa-lock-open"></i> Восстановление</div> </div>
            <p>Функционал восстановления пароля находится в разработке.</p>
            <p>Пожалуйста, свяжитесь с администрацией через <a href="contact.php">контакты</a>.</p>
        </div>

        <div class="sidebar-right">
            <h3>Категории сайтов</h3>
            <?php foreach ($categories as $category): ?>
                <a href="category.php?id=<?php echo $category['id']; ?>"><i class="fas fa-folder"></i> <?php echo escape($category['name']); ?></a> <?php endforeach; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>