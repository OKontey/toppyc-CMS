<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/load.php';

// Проверка, передан ли ID категории
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('index.php');
}

$category_id = (int)$_GET['id'];

// Получение информации о категории
$stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$category) {
    redirect('index.php');
}

// Получение списка сайтов в категории
$sites = [];
$result = $conn->query("SELECT s.id, s.name, s.url, s.description, s.views, c.name AS category_name
                        FROM sites s
                        JOIN categories c ON s.category_id = c.id
                        WHERE s.category_id = $category_id AND s.approved = 1
                        ORDER BY s.views DESC");
while ($row = $result->fetch_assoc()) {
    $sites[] = $row;
}

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
    <title>Категория: <?php echo escape($category['name']); ?> - Toppyc.ru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar_left.php'; ?>

        <div class="main-content">
            <h2>Категория: <?php echo escape($category['name']); ?></h2>
            <div class="tab-container">
                <div class="tab active"><i class="fas fa-list"></i> Сайты в категории</div> </div>
            <?php if (empty($sites)): ?>
                <p>В этой категории пока нет сайтов.</p>
            <?php else: ?>
                <?php foreach ($sites as $index => $site): ?>
                    <div class="site-card">
                        <?php
                        // Путь к скриншоту (заглушке, если нет реального)
                        $screenshot_path = 'screenshots/' . $site['id'] . '.png';
                        if (!file_exists($screenshot_path)) {
                            $screenshot_path = 'screenshots/placeholder.png'; // Убедитесь, что placeholder.png существует
                        }
                        ?>
                        <img src="<?php echo escape($screenshot_path); ?>" alt="Скриншот сайта <?php echo escape($site['name']); ?>">

                        <div class="site-info">
                             <h3><a href="site.php?id=<?php echo $site['id']; ?>"><?php echo escape($site['name']); ?></a></h3>
                            <p>Описание: <?php echo escape($site['description']); ?></p>
                            <p>Категория: <?php echo escape($site['category_name']); ?></p>
                        </div>
                        <div class="site-stats">
                             <p><i class="fas fa-eye"></i> Просмотры: <?php echo $site['views']; ?></p>
                             <a href="site.php?id=<?php echo $site['id']; ?>" class="btn btn-info btn-small"><i class="fas fa-info-circle"></i> Подробнее</a> </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="sidebar-right">
            <h3>Категории сайтов</h3>
            <?php foreach ($categories as $cat): ?>
                <a href="category.php?id=<?php echo $cat['id']; ?>"><i class="fas fa-folder"></i> <?php echo escape($cat['name']); ?></a> <?php endforeach; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>