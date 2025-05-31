<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/func/config.php';

// Проверка, авторизован ли пользователь
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Проверка, является ли пользователь администратором
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Перенаправление
function redirect($url) {
    header("Location: $url");
    exit();
}

// Экранирование данных для защиты от XSS
function escape($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Генерация капчи (4 цифры)
function generateCaptcha() {
    $captcha = rand(1000, 9999);
    $_SESSION['captcha'] = $captcha;
    return $captcha;
}

// Проверка капчи
function verifyCaptcha($input) {
    return isset($_SESSION['captcha']) && $_SESSION['captcha'] == $input;
}

// Функция для генерации скриншота (теперь в основном используется для заглушки или других целей)
// При загрузке пользователем в add_site.php эта функция НЕ вызывается.
function generateScreenshot($url, $site_id) {
    // В реальном проекте используй API, например, ScreenshotLayer или аналогичный сервис
    // Или эту функцию можно использовать, если нужно генерировать скриншоты для старых сайтов
    // или из админки.
    $placeholder = 'placeholder.png'; // Убедитесь, что placeholder.png есть в папке screenshots
    if (!is_dir(SCREENSHOT_PATH)) {
        mkdir(SCREENSHOT_PATH, 0755, true);
    }
    if (!file_exists(SCREENSHOT_PATH . $placeholder)) {
        // Загружаем заглушку, если её нет
        $placeholder_url = 'https://via.placeholder.com/150x150?text=No+Screenshot'; // Обновленный URL заглушки
        $placeholder_content = @file_get_contents($placeholder_url);
         if ($placeholder_content !== false) {
             file_put_contents(SCREENSHOT_PATH . $placeholder, $placeholder_content);
         } else {
             // Если даже заглушку не удалось загрузить, создаем пустой файл
             file_put_contents(SCREENSHOT_PATH . $placeholder, '');
         }
    }
    // Копируем заглушку с именем ID сайта
    copy(SCREENSHOT_PATH . $placeholder, SCREENSHOT_PATH . $site_id . '.png');
     error_log("INFO: Placeholder screenshot generated for site ID {$site_id}"); // Логируем использование заглушки
}


// Обновление статистики сайта (хиты и просмотры) с счетчика
// Эта функция вызывается из counter-image.php
function updateCounterStats($conn, $site_id) {
    $today = date('Y-m-d');
    $visitor_ip = $_SERVER['REMOTE_ADDR'];
    // $session_key = "visited_site_counter_$site_id"; // Этот ключ используется в counter-image.php для ограничения уникальных за сессию


    // Проверяем, есть ли запись статистики на сегодня для этого сайта
    $result_stats = $conn->query("SELECT id FROM stats WHERE site_id = $site_id AND date = '$today'");
    $stats_row = $result_stats->fetch_assoc();

    if ($stats_row) {
        $stats_id = $stats_row['id'];

        // Увеличиваем хиты за сегодня
        $conn->query("UPDATE stats SET hits = hits + 1 WHERE id = $stats_id");

        // Проверяем, уникальный ли посетитель за сегодня по IP
        // Используем 'ip_address' как имя столбца
        $result_visitor = $conn->query("SELECT id FROM stats_visitors WHERE stat_id = $stats_id AND ip_address = '$visitor_ip'");
        if ($result_visitor->num_rows === 0) {
            // Если IP уникален за сегодня, добавляем его и увеличиваем unique_visitors
            // Добавляем IP с текущим временем
            $stmt = $conn->prepare("INSERT INTO stats_visitors (stat_id, ip_address, visit_time) VALUES (?, ?, NOW())");
            $stmt->bind_param("is", $stats_id, $visitor_ip);
            $stmt->execute();
            $stmt->close();

            $conn->query("UPDATE stats SET unique_visitors = unique_visitors + 1 WHERE id = $stats_id");
        } else {
             // Если IP не уникален за сегодня, просто записываем хит с новой меткой времени в stats_visitors
             // (это нужно для подсчета хитов и уникальных за час)
             $stmt = $conn->prepare("INSERT INTO stats_visitors (stat_id, ip_address, visit_time) VALUES (?, ?, NOW())");
             $stmt->bind_param("is", $stats_id, $visitor_ip);
             $stmt->execute();
             $stmt->close();
        }
    } else {
        // Если записи на сегодня нет, создаем новую запись в stats
        // При первом хите за день views, hits, unique_visitors равны 1
        $conn->query("INSERT INTO stats (site_id, date, views, hits, unique_visitors) VALUES ($site_id, '$today', 1, 1, 1)");
        $stats_id = $conn->insert_id;
        // Добавляем IP первого посетителя за сегодня с текущим временем в stats_visitors
        $stmt = $conn->prepare("INSERT INTO stats_visitors (stat_id, ip_address, visit_time) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $stats_id, $visitor_ip);
        $stmt->execute();
        $stmt->close();
    }

    // Увеличиваем общее количество просмотров сайта (sites.views)
    // Это должно происходить при каждом показе счетчика, если views в stats и sites - это просмотры счетчика
     $conn->query("UPDATE sites SET views = views + 1 WHERE id = $site_id");

}


// Функция для обновления статистики просмотров страницы site.php
function updateSitePageViews($conn, $site_id) {
    $stmt = $conn->prepare("UPDATE sites SET site_page_views = site_page_views + 1 WHERE id = ?");
    $stmt->bind_param("i", $site_id);
    $stmt->execute();
    $stmt->close();
}


// Функция для подсчета общей репутации сайта
function calculateOverallReputation($conn, $site_id) {
    $stmt = $conn->prepare("SELECT SUM(rating) AS total_reputation FROM reviews WHERE site_id = ? AND parent_id IS NULL"); // Считаем только корневые отзывы
    $stmt->bind_param("i", $site_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$result['total_reputation'];
}

// Функция для обновления репутации сайта в базе
function updateSiteReputation($conn, $site_id) {
    $reputation = calculateOverallReputation($conn, $site_id);
    $stmt = $conn->prepare("UPDATE sites SET reputation = ? WHERE id = ?");
    $stmt->bind_param("ii", $reputation, $site_id);
    $stmt->execute();
    $stmt->close();
}


// Функция для получения статистики счетчика за определенный период (для текстового отображения)
function getCounterStats($conn, $site_id, $period = 'day') {
    $stats = [
        'views' => 0, // Views счетчика
        'unique_visitors' => 0, // Уникальные посетители счетчика
        'hits' => 0 // Хиты счетчика
    ];

    switch ($period) {
        case 'day':
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT views, unique_visitors, hits FROM stats WHERE site_id = ? AND date = ?");
            $stmt->bind_param("is", $site_id, $today);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['views'] = (int)$row['views'];
                $stats['unique_visitors'] = (int)$row['unique_visitors'];
                 $stats['hits'] = (int)$row['hits'];
            }
            $stmt->close();
            break;
        case 'month':
            // Получаем сумму за последние 30 дней из таблицы stats
            $month_start = date('Y-m-d', strtotime('-30 days'));
            $stmt = $conn->prepare("SELECT SUM(views) AS total_views, SUM(unique_visitors) AS total_unique, SUM(hits) AS total_hits
                                    FROM stats
                                    WHERE site_id = ? AND date >= ?");
            $stmt->bind_param("is", $site_id, $month_start);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stats['views'] = (int)$row['total_views'];
                $stats['unique_visitors'] = (int)$row['total_unique'];
                 $stats['hits'] = (int)$row['total_hits'];
            }
            $stmt->close();
            break;
        case 'hour':
             // Получение хитов и уникальных посетителей за последний час из stats_visitors
             $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
             $today = date('Y-m-d');

             // Находим stat_id для сегодняшнего дня, т.к. stats_visitors привязаны к stats по date
             $stmt_stat_id = $conn->prepare("SELECT id FROM stats WHERE site_id = ? AND date = ?");
             $stmt_stat_id->bind_param("is", $site_id, $today);
             $stmt_stat_id->execute();
             $stat_row = $stmt_stat_id->get_result()->fetch_assoc();
             $stmt_stat_id->close();

             if ($stat_row) {
                 $stats_id = $stat_row['id'];

                 // Подсчитываем хиты за последний час из stats_visitors
                 $stmt_hits = $conn->prepare("SELECT COUNT(*) AS hourly_hits FROM stats_visitors WHERE stat_id = ? AND visit_time >= ?");
                 $stmt_hits->bind_param("is", $stats_id, $one_hour_ago);
                 $stmt_hits->execute();
                 $hits_row = $stmt_hits->get_result()->fetch_assoc();
                 $stats['hits'] = (int)$hits_row['hourly_hits'];
                 $stmt_hits->close();

                 // Подсчитываем уникальных посетителей за последний час (по IP в рамках stat_id за сегодня и времени)
                 $stmt_unique = $conn->prepare("SELECT COUNT(DISTINCT ip_address) AS hourly_unique FROM stats_visitors WHERE stat_id = ? AND visit_time >= ?");
                 $stmt_unique->bind_param("is", $stats_id, $one_hour_ago);
                 $stmt_unique->execute();
                 $unique_row = $stmt_unique->get_result()->fetch_assoc();
                 $stats['unique_visitors'] = (int)$unique_row['hourly_unique'];
                 $stmt_unique->close();

                  // Views счетчика за час приравниваем к хитам за час
                 $stats['views'] = $stats['hits'];

             } else {
                 // Если нет записи stats за сегодня, статистики за час тоже нет.
             }

            break;
    }

    return $stats;
}

// Функция для получения ежедневной статистики для графика (за последние N дней)
function getDailyStatsForGraph($conn, $site_id, $days = 30) {
    $data = [];
    $start_date = date('Y-m-d', strtotime("-{$days} days"));
    $stmt = $conn->prepare("SELECT date, views, unique_visitors, hits FROM stats WHERE site_id = ? AND date >= ? ORDER BY date ASC");
    $stmt->bind_param("is", $site_id, $start_date);
    $stmt->execute();
    $result = $stmt->get_result();

    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        new DateTime('tomorrow') // Включаем сегодняшний день
    );

    $stats_by_date = [];
    while($row = $result->fetch_assoc()){
        $stats_by_date[$row['date']] = $row;
    }
    $stmt->close();

    foreach ($period as $date) {
        $date_str = $date->format('Y-m-d');
        $data[] = [
            'date' => $date->format('d.m'), // Формат для оси X
            'views' => isset($stats_by_date[$date_str]) ? (int)$stats_by_date[$date_str]['views'] : 0,
            'unique_visitors' => isset($stats_by_date[$date_str]) ? (int)$stats_by_date[$date_str]['unique_visitors'] : 0,
            'hits' => isset($stats_by_date[$date_str]) ? (int)$stats_by_date[$date_str]['hits'] : 0,
        ];
    }

    return $data;
}

// Функция для получения почасовой статистики для графика (за последние 24 часа)
function getHourlyStatsForGraph($conn, $site_id) {
    $data = [];
    $now = new DateTime();
    $start_time = (clone $now)->modify('-24 hours');

    // Находим stat_id для каждого дня за последние 24 часа
    // Это нужно, потому что stats_visitors связаны со stats по stat_id и date
    // Могут быть записи из двух разных дней, если 24 часа пересекают полночь
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $stmt_stat_ids = $conn->prepare("SELECT id, date FROM stats WHERE site_id = ? AND (date = ? OR date = ?)");
    $stmt_stat_ids->bind_param("iss", $site_id, $today, $yesterday);
    $stmt_stat_ids->execute();
    $stat_ids = [];
    $result_stat_ids = $stmt_stat_ids->get_result();
    while($row = $result_stat_ids->fetch_assoc()) {
        $stat_ids[$row['date']] = $row['id'];
    }
    $stmt_stat_ids->close();

    // Если нет статистики за последние 2 дня, возвращаем пустой массив
    if (empty($stat_ids)) {
        return [];
    }

    // Получаем хиты и уникальных посетителей из stats_visitors за последние 24 часа
    // Группируем по полному часу (YYYY-MM-DD HH:00:00)
    $stmt_hourly_stats = $conn->prepare("SELECT
                                            DATE_FORMAT(visit_time, '%Y-%m-%d %H:00:00') as hour,
                                            COUNT(*) as hourly_hits,
                                            COUNT(DISTINCT ip_address) as hourly_unique
                                        FROM stats_visitors
                                        WHERE stat_id IN (" . implode(',', array_values($stat_ids)) . ")
                                        AND visit_time >= ?
                                        GROUP BY hour
                                        ORDER BY hour ASC");
    $start_time_str = $start_time->format('Y-m-d H:i:s');
    $stmt_hourly_stats->bind_param("s", $start_time_str);
    $stmt_hourly_stats->execute();
    $hourly_results = $stmt_hourly_stats->get_result();

    $stats_by_hour = [];
    while ($row = $hourly_results->fetch_assoc()) {
        $stats_by_hour[$row['hour']] = $row;
    }
    $stmt_hourly_stats->close();

    // Формируем данные для графика, включая часы без статистики с нулевыми значениями
    $interval = new DateInterval('PT1H'); // Интервал в 1 час
    // Создаем период от start_time до текущего часа + 1 час, чтобы убедиться, что последний час включен
    $period = new DatePeriod($start_time, $interval, (clone $now)->modify('+1 hour'));

    foreach ($period as $hour_date) {
        $hour_str = $hour_date->format('Y-m-d H:00:00');
         $display_label = $hour_date->format('H:00'); // Формат для оси X (например, 14:00)

        $data[] = [
            'hour' => $display_label,
            'views' => isset($stats_by_hour[$hour_str]) ? (int)$stats_by_hour[$hour_str]['hourly_hits'] : 0, // Views счетчика = хитам
            'unique_visitors' => isset($stats_by_hour[$hour_str]) ? (int)$stats_by_hour[$hour_str]['hourly_unique'] : 0,
            'hits' => isset($stats_by_hour[$hour_str]) ? (int)$stats_by_hour[$hour_str]['hourly_hits'] : 0, // Хиты
        ];
    }

    return $data;
}

// Функция для проверки, заблокирован ли пользователь
function isUserBlocked($conn, $user_id) {
    $stmt = $conn->prepare("SELECT is_blocked FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $stmt->close();
            return (bool)$user['is_blocked']; // Возвращает true, если заблокирован, иначе false
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare statement for isUserBlocked: " . $conn->error);
    }
    return false; // По умолчанию считаем пользователя не заблокированным при ошибке
}

// Функция для переключения статуса блокировки пользователя
function toggleUserBlockStatus($conn, $user_id, $block_status) {
    // Убедимся, что $block_status является булевым значением
    $block_status = (bool)$block_status;

    $stmt = $conn->prepare("UPDATE users SET is_blocked = ? WHERE id = ?");
    if ($stmt) {
        // Используем 'i' для integer (block_status преобразуется к 0 или 1) и 'i' для user_id
        $stmt->bind_param("ii", $block_status, $user_id);
        if ($stmt->execute()) {
            $stmt->close();
            return true; // Успешно обновлено
        } else {
            error_log("Failed to execute statement for toggleUserBlockStatus: " . $stmt->error);
            $stmt->close();
            return false; // Ошибка выполнения
        }
    } else {
        error_log("Failed to prepare statement for toggleUserBlockStatus: " . $conn->error);
        return false; // Ошибка подготовки
    }
}

?>