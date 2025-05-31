<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/load.php';

// Получение списка категорий
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Получение топ-10 сайтов (изменено с 21)
$sites = [];
$result = $conn->query("SELECT s.id, s.name, s.url, s.views, s.reputation, c.name AS category_name
                        FROM sites s
                        JOIN categories c ON s.category_id = c.id
                        WHERE s.approved = 1
                        ORDER BY s.views DESC, s.reputation DESC
                        LIMIT 10"); // Изменено на LIMIT 10
while ($row = $result->fetch_assoc()) {
    // Получение уникальных посетителей за всё время
    $unique_visitors = 0;
    $unique_result = $conn->query("SELECT SUM(unique_visitors) AS total_unique
                               FROM stats
                               WHERE site_id = {$row['id']}");
    if ($unique_row = $unique_result->fetch_assoc()) {
        $unique_visitors = (int)$unique_row['total_unique'];
    }
    $row['unique_visitors'] = $unique_visitors;

    // Путь к скриншоту
    $screenshot_path = $domain . '/screenshots/' . $row['id'] . '.png';
    // Проверяем наличие файла на сервере (лучше проверять наличие файла, а не полагаться только на URL)
    $local_screenshot_path = SCREENSHOT_PATH . $row['id'] . '.png';
    if (!file_exists($local_screenshot_path) || (file_exists($local_screenshot_path) && filesize($local_screenshot_path) === 0)) { // Проверяем наличие и что файл не пустой
        $screenshot_path = $domain . '/screenshots/placeholder.png'; // Убедитесь, что placeholder.png существует
         // Опционально, можно вызвать generateScreenshot($row['url'], $row['id']) здесь
         // для генерации скриншота в фоновом режиме или при первом запросе, если API настроен.
    }

    $row['screenshot'] = $screenshot_path;

    // Место для получения Яндекс ИКС
    // Вам потребуется реализовать логику получения ИКС здесь
    // Например, использовать сторонний API сервис
    // Пока просто добавим placeholder
    $row['yandex_iks'] = 'N/A'; // Заглушка для Яндекс ИКС
    // Пример вызова API (закомментировано, требует реализации)
    /*
    $iks_api_key = 'ВАШ_API_КЛЮЧ_ИКС';
    $iks_api_endpoint = 'https://api.yourservices.com/get_iks';
    $iks_params = ['url' => $row['url'], 'key' => $iks_api_key];
    $iks_request_url = $iks_api_endpoint . '?' . http_build_query($iks_params);
    $iks_data = @file_get_contents($iks_request_url);
    if ($iks_data !== false) {
        // Предполагаем, что API возвращает JSON
        // $iks_result = json_decode($iks_data, true);
        // if (isset($iks_result['iks'])) {
        //     $row['yandex_iks'] = $iks_result['iks'];
        // }
    }
    */


    $sites[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$_SERVER['HTTP_HOST']?> - Топ-10 сайтов</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Стиль для центрирования tab-container на главной */
        .main-content .tab-container {
            justify-content: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include 'sidebar_left.php'; ?>

        <div class="main-content">
            <div class="tab-container">
                <div class="tab active" data-tab="top-10"><i class="fas fa-chart-bar"></i> Топ-10</div>
            </div>

            <div id="top-10-content" class="tab-content active">
                <?php if (empty($sites)): ?>
                    <p>Пока нет сайтов для отображения в Топ-10.</p>
                <?php else: ?>
                    <div class="site-block-grid">
                        <?php foreach ($sites as $site): ?>
                            <div class="site-widget">
                                <div class="site-widget-image-container">
                                     <a href="site.php?id=<?php echo $site['id']; ?>">
                                        <img src="<?php echo escape($site['screenshot']); ?>" alt="Скриншот сайта <?php echo escape($site['name']); ?>">
                                     </a>
                                </div>
                                <div class="site-widget-content">
                                    <h3><a href="site.php?id=<?php echo $site['id']; ?>"><?php echo escape($site['name']); ?></a></h3>
                                    <p><i class="fas fa-user"></i> Уникальные: <?php echo $site['unique_visitors']; ?></p>
                                    <p><i class="fas fa-eye"></i> Просмотры: <?php echo $site['views']; ?></p>
                                     <p><i class="fab fa-yandex"></i> Яндекс ИКС: <?php echo escape($site['yandex_iks']); ?></p>
                                    <div class="site-widget-buttons">
                                        <a href="<?php echo escape($site['url']); ?>" target="_blank" class="btn btn-primary btn-small"><i class="fas fa-globe"></i> На сайт</a>
                                        <a href="site.php?id=<?php echo $site['id']; ?>" class="btn btn-info btn-small"><i class="fas fa-chart-line"></i> Статистика</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            </div>

        <div class="sidebar-right">
            <h3>Категории сайтов</h3>
            <?php foreach ($categories as $category): ?>
                <a href="category.php?id=<?php echo $category['id']; ?>"><i class="fas fa-folder"></i> <?php echo escape($category['name']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Для главной страницы просто скрываем/показываем блоки, если есть несколько вкладок
            // Если вкладка всего одна ("Топ-10"), этот скрипт может быть упрощен или убран.
            // Оставим его на случай добавления других вкладок.

            const tabs = document.querySelectorAll('.tab-container .tab');
            const tabContents = document.querySelectorAll('.main-content .tab-content');

             // Функция для переключения вкладок
            function activateTab(tabId) {
                tabs.forEach(tab => {
                    tab.classList.remove('active');
                });
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    content.style.display = 'none'; // Явно скрываем
                });

                const activeTab = document.querySelector(`.tab[data-tab="${tabId}"]`);
                const activeContent = document.getElementById(`${tabId}-content`);

                if (activeTab) {
                    activeTab.classList.add('active');
                }
                if (activeContent) {
                    activeContent.classList.add('active');
                    activeContent.style.display = 'block'; // Явно показываем
                }

                // На главной странице можно не сохранять вкладку в localStorage,
                // если всегда по умолчанию показывается Топ-10.
                // localStorage.setItem('activeHomeTab', tabId);
            }

            // Активируем вкладку по умолчанию (Топ-10) при загрузке
             // Проверяем, есть ли блоки содержимого, прежде чем пытаться активировать
             if (tabContents.length > 0) {
                activateTab('top-10'); // Активируем вкладку Топ-10 по умолчанию
             }


            // Назначаем обработчики кликов на табы (если есть несколько вкладок)
             tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    activateTab(tabId);
                });
            });

        });
     </script>
</body>
</html>

<?php
$conn->close();
?>