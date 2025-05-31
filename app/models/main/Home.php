<?
$page_title = 'Топ-10 сайтов';

// Получение топ-10 сайтов
$sites = [];
$result = $conn->query("SELECT s.id, s.name, s.url, s.views, s.reputation, c.name AS category_name
                        FROM sites s
                        JOIN categories c ON s.category_id = c.id
                        WHERE s.approved = 1
                        ORDER BY s.views DESC, s.reputation DESC
                        LIMIT 10");
if($result){
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
}
?>