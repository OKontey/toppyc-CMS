<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/load.php';

// Проверка, передан ли ID сайта
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('index.php');
}

$site_id = (int)$_GET['id'];

// Получение информации о сайте
$stmt = $conn->prepare("SELECT s.id, s.name, s.views, s.hits, c.name AS category_name
                        FROM sites s
                        JOIN categories c ON s.category_id = c.id
                        WHERE s.id = ? AND s.approved = 1");
$stmt->bind_param("i", $site_id);
$stmt->execute();
$site = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$site) {
    redirect('index.php');
}

// Получение статистики за неделю
$week_views = 0;
$week_hits = 0;
$week_unique_visitors = 0; // Получаем уникальных посетителей за неделю
$week_start = date('Y-m-d', strtotime('-7 days'));
$result = $conn->query("SELECT SUM(views) AS total_views, SUM(hits) AS total_hits, SUM(unique_visitors) AS total_unique
                        FROM stats
                        WHERE site_id = $site_id AND date >= '$week_start'");
if ($row = $result->fetch_assoc()) {
    $week_views = (int)$row['total_views'];
    $week_hits = (int)$row['total_hits'];
    $week_unique_visitors = (int)$row['total_unique'];
}

// Получение статистики за месяц
$month_views = 0;
$month_hits = 0;
$month_unique_visitors = 0; // Получаем уникальных посетителей за месяц
$month_start = date('Y-m-d', strtotime('-30 days'));
$result = $conn->query("SELECT SUM(views) AS total_views, SUM(hits) AS total_hits, SUM(unique_visitors) AS total_unique
                        FROM stats
                        WHERE site_id = $site_id AND date >= '$month_start'");
if ($row = $result->fetch_assoc()) {
    $month_views = (int)$row['total_views'];
    $month_hits = (int)$row['total_hits'];
    $month_unique_visitors = (int)$row['total_unique'];
}

// Получение категорий для сайдбара
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
    <title>Статистика: <?php echo escape($site['name']); ?> - Toppyc.ru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar_left.php'; ?>

        <div class="main-content">
            <h2>Статистика: <?php echo escape($site['name']); ?></h2>
            <div class="tab-container">
                <div class="tab active"><i class="fas fa-chart-line"></i> Общая статистика</div> </div>
            <p><strong>Категория:</strong> <?php echo escape($site['category_name']); ?></p>
            <h3>Общая статистика (за всё время)</h3> <p><i class="fas fa-eye"></i> Всего просмотров: <?php echo $site['views']; ?></p>
            <p><i class="fas fa-mouse-pointer"></i> Всего хитов: <?php echo $site['hits']; ?></p>
            <h3>За неделю</h3>
            <p><i class="fas fa-chart-area"></i> Просмотров: <?php echo $week_views; ?></p>
            <p><i class="fas fa-users"></i> Уникальных посетителей: <?php echo $week_unique_visitors; ?></p> <p><i class="fas fa-mouse-pointer"></i> Хитов: <?php echo $week_hits; ?></p>
            <h3>За месяц</h3>
            <p><i class="fas fa-chart-bar"></i> Просмотров: <?php echo $month_views; ?></p>
            <p><i class="fas fa-users-cog"></i> Уникальных посетителей: <?php echo $month_unique_visitors; ?></p> <p><i class="fas fa-mouse-pointer"></i> Хитов: <?php echo $month_hits; ?></p>
            <p><a href="site.php?id=<?php echo $site['id']; ?>" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Назад к сайту</a></p>
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