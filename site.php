<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

// Проверка, передан ли ID сайта
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirect('index.php');
}

$site_id = (int)$_GET['id'];

// Обновление статистики просмотров страницы site.php
// Эту функцию вызываем здесь, чтобы считать просмотры именно этой страницы
updateSitePageViews($conn, $site_id);


// Получение информации о сайте, включая site_page_views
$stmt = $conn->prepare("SELECT s.id, s.name, s.url, s.description, s.views, s.hits, s.reputation, s.site_page_views, c.name AS category_name
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

// === PHP-код для графиков ===
// Получение статистики счетчика за разные периоды (для текстового отображения)
$stats_day = getCounterStats($conn, $site_id, 'day');
$stats_month = getCounterStats($conn, $site_id, 'month');
$stats_hour = getCounterStats($conn, $site_id, 'hour');

// Получение данных для ежедневного графика за последние 30 дней
$daily_graph_data = getDailyStatsForGraph($conn, $site_id, 30); // Убедитесь, что эта функция возвращает массив
$daily_graph_data_json = json_encode($daily_graph_data);

// Получение данных для почасового графика за последние 24 часа
$hourly_graph_data = getHourlyStatsForGraph($conn, $site_id, 24); // Убедитесь, что эта функция возвращает массив
$hourly_graph_data_json = json_encode($hourly_graph_data);
// ========================================


// Получение общего количества КОРНЕВЫХ отзывов для заголовка вкладки
$stmt = $conn->prepare("SELECT COUNT(*) AS total_reviews FROM reviews WHERE site_id = ? AND parent_id IS NULL");
$stmt->bind_param("i", $site_id);
$stmt->execute();
$total_root_review_count = $stmt->get_result()->fetch_assoc()['total_reviews'];
$stmt->close();

// Получение общего количества ВСЕХ отзывов для заголовка списка внутри вкладки
$stmt = $conn->prepare("SELECT COUNT(*) AS total_reviews FROM reviews WHERE site_id = ?");
$stmt->bind_param("i", $site_id);
$stmt->execute();
$total_review_count = $stmt->get_result()->fetch_assoc()['total_reviews'];
$stmt->close();


// Обработка POST запросов (добавление или редактирование отзыва/ответа/удаления/блокировки)
$review_error = '';
$review_success = '';
$upload_errors_display = ''; // Переменная для отображения ошибок загрузки пользователю
$block_user_message = ''; // Сообщение о результате блокировки/разблокировки

// Проверяем, были ли сообщения переданы через сессию после редиректа
if (isset($_SESSION['upload_errors_display'])) {
    $upload_errors_display = $_SESSION['upload_errors_display'];
    unset($_SESSION['upload_errors_display']); // Очищаем переменную сессии после использования
}
if (isset($_SESSION['review_success'])) {
     $review_success = $_SESSION['review_success'];
     unset($_SESSION['review_success']); // Очищаем переменную сессии
}
if (isset($_SESSION['review_error'])) {
    $review_error = $_SESSION['review_error'];
    unset($_SESSION['review_error']);
}
if (isset($_SESSION['block_user_message'])) {
    $block_user_message = $_SESSION['block_user_message'];
    unset($_SESSION['block_user_message']);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $action = isset($_POST['action']) ? $_POST['action'] : ''; // Получаем тип действия

    if ($action === 'add_root_review') {
        // Логика добавления нового корневого отзыва
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
        $comment = trim($_POST['comment']);
        $parent_id = null; // Корневой отзыв не имеет родителя

        // Проверка, выбраны ли файлы для загрузки
        $files_selected_for_upload = isset($_FILES['review_images']) && is_array($_FILES['review_images']['name']) && array_filter($_FILES['review_images']['name']);

        // Валидация оценки репутации (-5 до +5)
        if ($rating === null || $rating < -5 || $rating > 5) {
             $review_error = 'Некорректная оценка репутации (должна быть от -5 до +5).';
        } elseif (empty($comment) && !$files_selected_for_upload) {
             // Комментарий обязателен, только если нет прикрепленных изображений для ЗАГРУЗКИ
             $review_error = 'Комментарий отзыва не может быть пустым, если не прикреплено ни одно изображение.';
        } else {
            // Проверяем, оставлял ли пользователь уже корневой отзыв для этого сайта
            $stmt = $conn->prepare("SELECT id FROM reviews WHERE site_id = ? AND user_id = ? AND parent_id IS NULL");
            $stmt->bind_param("ii", $site_id, $user_id);
            $stmt->execute();
            $has_reviewed = $stmt->get_result()->num_rows > 0;
            $stmt->close();

            if ($has_reviewed) {
                $review_error = 'Вы уже оставили основной отзыв на этот сайт.';
            } else {
                // Добавляем новый корневой отзыв
                // Если комментарий пустой, вставляем NULL или пустую строку, если поле в БД не может быть NULL
                $comment_to_db = empty($comment) ? null : $comment; // Вставляем NULL, если комментарий пустой (предполагая, что в БД поле TEXT/VARCHAR может быть NULL)
                $stmt = $conn->prepare("INSERT INTO reviews (site_id, user_id, parent_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiiss", $site_id, $user_id, $parent_id, $rating, $comment_to_db); // Используем $comment_to_db
                if ($stmt->execute()) {
                    $review_id = $conn->insert_id; // Получаем ID нового отзыва
                    $current_review_success = 'Отзыв успешно добавлен.'; // Используем временную переменную

                    // --- Обработка загрузки изображений ---
                    $upload_dir = 'uploads/reviews/'; // Директория для хранения изображений отзывов (относительный путь)
                    // Полный путь на сервере. Предполагаем, что uploads находится в той же директории, что и site.php
                    $upload_base_path = __DIR__ . '/' . $upload_dir;

                    $upload_errors = []; // Ошибки только для загрузки файлов

                    // Убедитесь, что директория существует и доступна для записи, только если есть файлы для загрузки
                    if ($files_selected_for_upload) {
                        if (!is_dir($upload_base_path)) {
                             if (!mkdir($upload_base_path, 0775, true)) {
                                  $upload_errors[] = "Не удалось создать директорию для загрузки: " . htmlspecialchars($upload_dir);
                             }
                        } elseif (!is_writable($upload_base_path)) {
                             $upload_errors[] = "Директория для загрузки изображений недоступна для записи: " . htmlspecialchars($upload_dir);
                        }
                    }


                    $allowed_mime_types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif']; // Допустимые MIME-типы и их расширения
                    $max_file_size = 5 * 1024 * 1024; // УВЕЛИЧЕН РАЗМЕР ДО 5MB
                    $max_files = 5;
                    $uploaded_count = 0;
                    $uploaded_paths_db = []; // Пути для сохранения в БД (относительные)


                    // Проверяем, были ли файлы выбраны и директория доступна
                    if ($files_selected_for_upload && empty($upload_errors)) {
                         // Перебираем каждый загруженный файл
                         foreach ($_FILES['review_images']['name'] as $key => $name) {
                             // Проверяем, был ли файл успешно загружен без ошибок PHP на сервер во временную папку
                             if ($_FILES['review_images']['error'][$key] === UPLOAD_ERR_OK) {
                                 $tmp_name = $_FILES['review_images']['tmp_name'][$key];
                                 $file_size = $_FILES['review_images']['size'][$key];
                                 $original_name = escape($name); // Экранируем имя файла для сообщений

                                 // Получаем MIME-тип файла более надежным способом
                                 $finfo = finfo_open(FILEINFO_MIME_TYPE);
                                 $file_type = finfo_file($finfo, $tmp_name);
                                 finfo_close($finfo);

                                 // Проверка на количество файлов
                                 if ($uploaded_count >= $max_files) {
                                     $upload_errors[] = "Файл '{$original_name}' пропущен: Превышено максимальное количество ({$max_files}).";
                                     continue; // Пропускаем этот файл
                                 }

                                 // Валидация MIME-типа
                                 if (!array_key_exists($file_type, $allowed_mime_types)) {
                                     $upload_errors[] = "Файл '{$original_name}' пропущен: Недопустимый тип файла ({$file_type}). Разрешены: JPG, PNG, GIF.";
                                     continue; // Пропускаем этот файл
                                 }

                                 // Валидация размера файла
                                 if ($file_size > $max_file_size) {
                                     $upload_errors[] = "Файл '{$original_name}' пропущен: Превышает допустимый размер ({".($max_file_size/1024/1024)."MB).";
                                     continue; // Пропускаем этот файл
                                 }

                                 // Получаем расширение из разрешенных MIME-типов
                                 $file_ext = $allowed_mime_types[$file_type];

                                 // Генерируем уникальное имя файла и полный путь сохранения
                                 $new_file_name = uniqid('review_img_') . '.' . $file_ext;
                                 $destination_path = $upload_base_path . $new_file_name; // Полный путь на сервере

                                 // Перемещаем загруженный файл из временной директории в целевую
                                 if (move_uploaded_file($tmp_name, $destination_path)) {
                                     $uploaded_paths_db[] = $upload_dir . $new_file_name; // Сохраняем ОТНОСИТЕЛЬНЫЙ путь для записи в БД
                                     $uploaded_count++;
                                 } else {
                                     $upload_errors[] = "Файл '{$original_name}' пропущен: Ошибка при перемещении на сервер.";
                                 }
                             } elseif ($_FILES['review_images']['error'][$key] !== UPLOAD_ERR_NO_FILE) {
                                 // Обрабатываем другие возможные ошибки загрузки PHP (кроме случая, когда файл не был выбран)
                                  $error_code = $_FILES['review_images']['error'][$key];
                                  $error_message = "Файл '{$original_name}' пропущен: Ошибка загрузки PHP (код {$error_code}).";
                                  // Можно добавить более детальные сообщения для кодов ошибок, если нужно
                                 $upload_errors[] = $error_message;
                             }
                             // UPLOAD_ERR_NO_FILE (код 4) игнорируем, так как это нормально, если не все поля input[type=file] были использованы.
                         } // Конец цикла по файлам

                         // Вставляем ОТНОСИТЕЛЬНЫЕ пути к загруженным изображениям в базу данных
                         if (!empty($uploaded_paths_db)) {
                             $sql = "INSERT INTO review_images (review_id, image_path) VALUES (?, ?)";
                             $stmt_img = $conn->prepare($sql);
                             if ($stmt_img) {
                                 foreach ($uploaded_paths_db as $path_db) {
                                     $stmt_img->bind_param("is", $review_id, $path_db); // Используем подготовленное выражени
                                     $stmt_img->execute();
                                 }
                                 $stmt_img->close();
                             } else {
                                  error_log("Failed to prepare statement for inserting review_images: " . $conn->error);
                                  $upload_errors[] = "Внутренняя ошибка сервера при сохранении изображений.";
                             }
                         }

                    } // Конец if($files_selected_for_upload && empty($upload_errors))


                    // Формируем сообщение об ошибках загрузки для вывода пользователю
                    if (!empty($upload_errors)) {
                         $upload_errors_display = '<div class="alert alert-error">Ошибки при загрузке изображений:<br>' . implode('<br>', $upload_errors) . '</div>';
                    }

                    // --- Конец обработки загрузки изображений ---

                    // Обновляем репутацию сайта после добавления корневого отзыва
                    updateSiteReputation($conn, $site_id);

                     // Передаем сообщения через сессию и перенаправляем
                     $_SESSION['upload_errors_display'] = $upload_errors_display;
                     $_SESSION['review_success'] = $current_review_success; // Передаем успех отзыва

                    redirect("site.php?id=$site_id&tab=reviews-list#review-" . $review_id); // Перенаправляем на добавленный отзыв

                } else {
                    // Если не удалось добавить сам отзыв
                    $_SESSION['review_error'] = 'Ошибка добавления отзыва в базу данных.';
                    redirect("site.php?id=$site_id&tab=reviews-list"); // Перенаправляем обратно с ошибкой
                }
            }
        }

    } elseif ($action === 'add_reply' && isset($_POST['parent_id']) && is_numeric($_POST['parent_id'])) {
         // Логика добавления ответа на отзыв
         $parent_id = (int)$_POST['parent_id'];
         $comment = trim($_POST['comment']);
         // Ответы не имеют оценки репутации, вставляем 0 (т.к. в БД NOT NULL)
         $reply_rating = 0;

         // ИСПРАВЛЕНО: Проверка статуса блокировки пользователя перед добавлением ответа
         if (isUserBlocked($conn, $user_id)) {
              $_SESSION['review_error'] = 'Вы заблокированы и не можете оставлять ответы.';
         } elseif (empty($comment)) { // Комментарий для ответа обязателен
              $_SESSION['review_error'] = 'Комментарий ответа не может быть пустым.';
         } else {
             $stmt = $conn->prepare("INSERT INTO reviews (site_id, user_id, parent_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
             $stmt->bind_param("iiiss", $site_id, $user_id, $parent_id, $reply_rating, $comment);
             if ($stmt->execute()) {
                 $_SESSION['review_success'] = 'Ответ успешно добавлен.';
                 $stmt->close();
                  // Ответы не меняют репутацию сайта напрямую
                 redirect("site.php?id=$site_id&tab=reviews-list#review-" . $parent_id); // Перенаправляем на родительский отзыв
             } else {
                 $_SESSION['review_error'] = 'Ошибка добавления ответа.';
                 $stmt->close();
             }
         }
         redirect("site.php?id=$site_id&tab=reviews-list"); // Перенаправляем в любом случае

    } elseif ($action === 'edit_review' && isset($_POST['review_id']) && is_numeric($_POST['review_id'])) {
        // Логика редактирования существующего корневого отзыва
        $review_id = (int)$_POST['review_id'];
        $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : null;
        $comment = trim($_POST['comment']);

        // Получаем отзыв для проверки владения и того, что это корневой отзыв
        $stmt = $conn->prepare("SELECT id, user_id, site_id, parent_id FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $existing_review = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existing_review || (int)$existing_review['user_id'] !== (int)$user_id || $existing_review['parent_id'] !== null) {
            // Отзыв не найден, или пользователь не автор, или это не корневой отзыв
            $_SESSION['review_error'] = 'Невозможно отредактировать этот отзыв.';
        } elseif ($rating === null || $rating < -5 || $rating > 5) {
             $_SESSION['review_error'] = 'Некорректная оценка репутации (должна быть от -5 до +5).';
        } elseif (empty($comment)) { // Комментарий не может стать пустым при редактировании, если нет изображений? Редактирование текста не связано с изображениями, комментарий обязателен для корневого отзыва.
             $_SESSION['review_error'] = 'Комментарий не может быть пустым.';
        } else {

            // --- Обработка удаления изображений при редактировании ---
            if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                $upload_dir = 'uploads/reviews/'; // Относительный путь
                // Полный путь на сервере для удаления. Предполагаем, что uploads в той же директории.
                $upload_base_path = __DIR__ . '/' . $upload_dir;

                foreach ($_POST['delete_images'] as $image_id_to_delete) {
                    $image_id_to_delete = (int)$image_id_to_delete;
                    if ($image_id_to_delete > 0) {
                        // Получаем информацию об изображении, чтобы убедиться, что оно принадлежит редактируемому отзыву
                        $stmt_check_img = $conn->prepare("SELECT id, review_id, image_path FROM review_images WHERE id = ?");
                        if ($stmt_check_img) {
                             $stmt_check_img->bind_param("i", $image_id_to_delete);
                             $stmt_check_img->execute();
                             $img_to_delete = $stmt_check_img->get_result()->fetch_assoc();
                             $stmt_check_img->close();

                             // Проверяем, что изображение найдено и относится к текущему отзыву
                             if ($img_to_delete && (int)$img_to_delete['review_id'] === $review_id) {
                                  // Удаляем запись из БД
                                  $stmt_del_img = $conn->prepare("DELETE FROM review_images WHERE id = ?");
                                  if ($stmt_del_img) {
                                      $stmt_del_img->bind_param("i", $image_id_to_delete);
                                      if ($stmt_del_img->execute()) {
                                          // Удаляем физический файл с сервера
                                          // Используем полный путь на сервере, построенный корректно
                                          $physical_path = $upload_base_path . basename($img_to_delete['image_path']); // Используем basename для безопасности
                                          if (file_exists($physical_path)) {
                                              unlink($physical_path);
                                          } else {
                                              error_log("Failed to delete physical file (not found): " . $physical_path);
                                          }
                                      } else {
                                           error_log("Failed to delete review_image from DB (ID: {$image_id_to_delete}): " . $conn->error);
                                           // Можно добавить пользователю сообщение об ошибке удаления изображения, если нужно
                                      }
                                       $stmt_del_img->close();
                                  }
                             }
                        }
                    }
                }
            }
            // --- Конец обработки удаления изображений ---


            // Обновляем сам отзыв (текст и рейтинг)
            $stmt = $conn->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE id = ?");
            $stmt->bind_param("isi", $rating, $comment, $review_id);
            if ($stmt->execute()) {
                $_SESSION['review_success'] = 'Отзыв успешно обновлен.';
                $stmt->close();
                // Обновляем репутацию сайта после редактирования корневого отзыва
                updateSiteReputation($conn, (int)$existing_review['site_id']); // Пересчитываем и обновляем, используем site_id из fetched review
                redirect("site.php?id=" . (int)$existing_review['site_id'] . "&tab=reviews-list#review-" . $review_id); // Перенаправляем на отредактированный отзыв
            } else {
                $_SESSION['review_error'] = 'Ошибка обновления отзыва.';
                $stmt->close();
                redirect("site.php?id=" . (int)$existing_review['site_id'] . "&tab=reviews-list#review-" . $review_id); // Перенаправляем с ошибкой
            }
        }
         redirect("site.php?id=" . (int)$existing_review['site_id'] . "&tab=reviews-list#review-" . $review_id); // Перенаправляем в любом случае при ошибке валидации
    } elseif ($action === 'delete_review' && isset($_POST['review_id']) && is_numeric($_POST['review_id']) && isLoggedIn()) {
        // Логика удаления отзыва или ответа
        $review_id = (int)$_POST['review_id'];
        $user_id = $_SESSION['user_id']; // Logged-in user

        // Получаем отзыв для проверки владения или админ статуса, а также site_id для перенаправления и репутации
        $stmt = $conn->prepare("SELECT id, user_id, site_id, parent_id FROM reviews WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $review_to_delete = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($review_to_delete) {
            // Проверяем, является ли авторизованный пользователь автором ИЛИ администратором
            if ((int)$review_to_delete['user_id'] === (int)$user_id || isAdmin()) {
                // Пользователь имеет права на удаление

                // --- Удаление физических файлов изображений перед удалением отзыва из БД ---
                // Получаем пути к изображениям, связанным с этим отзывом
                $image_paths_to_delete = [];
                $stmt_img_paths = $conn->prepare("SELECT image_path FROM review_images WHERE review_id = ?");
                if ($stmt_img_paths) {
                     $stmt_img_paths->bind_param("i", $review_id);
                     $stmt_img_paths->execute();
                     $result_img_paths = $stmt_img_paths->get_result();
                     while ($row_img_paths = $result_img_paths->fetch_assoc()) {
                         $image_paths_to_delete[] = $row_img_paths['image_path'];
                     }
                     $stmt_img_paths->close();
                }

                // Удаляем сам отзыв (это вызовет каскадное удаление записей изображений и ответов в БД)
                $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
                $stmt->bind_param("i", $review_id);
                if ($stmt->execute()) {
                    $_SESSION['review_success'] = 'Отзыв/ответ успешно удален.';
                    $stmt->close();

                    // Удаляем физические файлы изображений после успешного удаления из БД
                    $upload_dir = 'uploads/reviews/'; // Относительный путь
                    // Полный путь на сервере для удаления. Предполагаем, что uploads в той же директории.
                    $upload_base_path = __DIR__ . '/' . $upload_dir;

                    foreach ($image_paths_to_delete as $path) {
                        // Используем полный путь на сервере, построенный корректно
                        $physical_path = $upload_base_path . basename($path); // Используем basename для безопасности
                        if (file_exists($physical_path)) { // Проверяем существование файла перед удалением
                            unlink($physical_path); // Удаляем файл
                        } else {
                            error_log("Failed to delete physical file (not found): " . $physical_path);
                        }
                    }

                    // Если удален корневой отзыв, пересчитываем репутацию сайта
                    // parent_id берется из review_to_delete, который мы получили ДО удаления
                    if ($review_to_delete['parent_id'] === null) {
                         updateSiteReputation($conn, (int)$review_to_delete['site_id']); // Пересчитываем и обновляем репутацию
                    }

                    // Перенаправляем обратно на вкладку отзывов
                    redirect("site.php?id=" . (int)$review_to_delete['site_id'] . "&tab=reviews-list");
                } else {
                    $_SESSION['review_error'] = 'Ошибка удаления отзыва/ответа из базы данных.';
                    redirect("site.php?id=" . (int)$review_to_delete['site_id'] . "&tab=reviews-list");
                }
            } else {
                // Пользователь не имеет прав
                $_SESSION['review_error'] = 'У вас нет прав для удаления этого отзыва/ответа.';
                redirect("site.php?id=" . (int)$review_to_delete['site_id'] . "&tab=reviews-list");
            }
        } else {
            // Отзыв/ответ не найден
            $_SESSION['review_error'] = 'Отзыв/ответ не найден.';
            // Если site_id не известен (отзыв не найден), редиректим на главную или список сайтов
            redirect("index.php"); // Перенаправляем на главную
        }
    } elseif ($action === 'toggle_block_user' && isset($_POST['user_to_block_id']) && is_numeric($_POST['user_to_block_id']) && isAdmin()) {
         // Новая логика для блокировки/разблокировки пользователя
         $user_to_block_id = (int)$_POST['user_to_block_id'];
         $current_block_status = isUserBlocked($conn, $user_to_block_id); // Получаем текущий статус

         // Переключаем статус
         $new_block_status = !$current_block_status;

         if (toggleUserBlockStatus($conn, $user_to_block_id, $new_block_status)) {
              $_SESSION['block_user_message'] = 'Статус блокировки пользователя успешно обновлен.';
         } else {
              $_SESSION['block_user_message'] = 'Ошибка при обновлении статуса блокировки пользователя.';
         }

         // Перенаправляем обратно на страницу сайта, откуда пришел запрос
         redirect("site.php?id=$site_id&tab=reviews-list");
    }
    // else: Обработка других возможных POST действий
     // Если ни одно из действий не совпало, или возникла ошибка до редиректа
     // Убедимся, что сессионные переменные установлены, если были ошибки
     if (!empty($review_error) && !isset($_SESSION['review_error'])) {
          $_SESSION['review_error'] = $review_error;
     }
     if (!empty($review_success) && !isset($_SESSION['review_success'])) {
          $_SESSION['review_success'] = $review_success;
     }
     if (!empty($upload_errors_display) && !isset($_SESSION['upload_errors_display'])) {
         $_SESSION['upload_errors_display'] = $upload_errors_display;
     }
      if (!empty($block_user_message) && !isset($_SESSION['block_user_message'])) {
         $_SESSION['block_user_message'] = $block_user_message;
      }
     // Уже есть редиректы внутри каждого успешного/неуспешного действия.
     // Этот блок ниже, возможно, избыточен, но оставим на всякий случай, если что-то пойдет не по плану.
     // redirect("site.php?id=$site_id&tab=reviews-list"); // Избегать двойных редиректов
}


// --- Получение и очистка сессионных сообщений для отображения после загрузки страницы ---
// Этот блок уже находится выше, перед обработкой POST.
// Переместим его ниже, чтобы он был после определения всех возможных сообщений.
// Но лучше оставить его там, где он сейчас, чтобы сообщения были доступны сразу после загрузки страницы.
// OK, оставляем блок получения сессионных сообщений в начале PHP части.


// Получение отзывов (древовидная структура)
$reviews = [];
$all_reviews = [];
// Сортировка по убыванию даты для новых отзывов первыми
$result = $conn->query("SELECT r.id, r.rating, r.comment, r.created_at, r.parent_id, u.login, r.user_id
                        FROM reviews r
                        JOIN users u ON r.user_id = u.id
                        WHERE r.site_id = $site_id
                        ORDER BY r.created_at DESC");

// Убедитесь, что запрос выполнен успешно перед fetch_assoc
if ($result === FALSE) {
    error_log("Error fetching reviews: " . $conn->error);
    // Можно установить $reviews в пустой массив или вывести ошибку пользователю
    $all_reviews = []; // Убеждаемся, что массив пуст, если запрос провалился
} else {
    while ($row = $result->fetch_assoc()) {
        $all_reviews[$row['id']] = $row;
    }
    $result->free(); // Освобождаем результат запроса
}
// ======================================================================

// Передаем $conn в функцию buildReviewTree для получения изображений
function buildReviewTree(&$elements, $parentId = null, $conn) {
    $branch = array();
    // Сортируем элементы по parent_id для правильной сборки дерева
    $sorted_elements = [];
    foreach ($elements as $element) {
        $sorted_elements[$element['parent_id']][] = $element;
    }

    // Если у текущего parentId есть дети
    if (isset($sorted_elements[$parentId])) {
        // Дополнительная сортировка по created_at в рамках одного parent_id
        usort($sorted_elements[$parentId], function($a, $b) {
             // Корневые отзывы уже отсортированы в главном запросе DESC.
             // Ответы внутри ветки сортируем ASC.
             // Проверяем, является ли родительский ID корневым (null). Если да, используем исходный порядок.
             // Если parentId не null (это ответ), сортируем дочерние элементы (ответы) по возрастанию даты.
            if ($parentId === null) {
                 // Если сортируем корневые элементы (parentId === null), используем исходный порядок (DESC из SQL)
                 // Просто возвращаем 0, чтобы не менять порядок, установленный SQL.
                 // Фактически этот usort для корневых может быть не нужен, т.к. они уже отсортированы.
                 return 0; // Сохраняем порядок, установленный SQL
            } else {
                 // Если сортируем ответы (parentId !== null), сортируем по возрастанию даты
                 return strtotime($a['created_at']) - strtotime($b['created_at']);
            }

        });


        foreach ($sorted_elements[$parentId] as $element) {
             // === Получение изображений для текущего отзыва ===
             $images = [];
             if ($conn) { // Ensure connection is valid
                  $stmt_img = $conn->prepare("SELECT id, image_path FROM review_images WHERE review_id = ?");
                  if ($stmt_img) {
                      $stmt_img->bind_param("i", $element['id']);
                      $stmt_img->execute();
                      $result_img = $stmt_img->get_result();
                      while ($row_img = $result_img->fetch_assoc()) {
                          $images[] = $row_img;
                      }
                      $stmt_img->close();
                      // Добавляем изображения к элементу отзыва
                      $element['images'] = $images;
                  } else {
                       error_log("Failed to prepare statement for review_images in buildReviewTree: " . $conn->error);
                  }
             }
             // ================================================

             $children = buildReviewTree($elements, $element['id'], $conn); // Recursive call
             if ($children) {
                 // Вложенные ответы уже отсортированы рекурсивным вызовом с условием parentId !== null
                 $element['replies'] = $children;
             } else {
                 $element['replies'] = [];
             }
             $branch[$element['id']] = $element;
         }
    }

    return $branch;
}

// Передаем $conn в функцию buildReviewTree
$reviews = buildReviewTree($all_reviews, null, $conn);


// Получение категорий для сайдбара - оставляем без изменений
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Путь к скриншоту - теперь используем SITE_URL, который должен быть HTTPS
$screenshot_path = SITE_URL . '/screenshots/' . $site['id'] . '.png';
// Полный путь на сервере для проверки файла. Предполагаем, что screenshots в той же директории, что и site.php
$local_screenshot_path = __DIR__ . '/screenshots/' . $site['id'] . '.png';
if (!file_exists($local_screenshot_path) || filesize($local_screenshot_path) === 0) {
    $screenshot_path = SITE_URL . '/screenshots/placeholder.png'; // Используем placeholder через SITE_URL (HTTPS)
}

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape($site['name']); ?> - Toppyc.ru</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <?php include 'sidebar_left.php'; ?>

        <div class="main-content">
            <h2><?php echo escape($site['name']); ?></h2>
            <div class="site-card">
                 <div class="site-card-image-container">
                    <img src="<?php echo escape($screenshot_path); ?>" alt="Скриншот сайта <?php echo escape($site['name']); ?>">
                 </div>

                <div class="site-info">
                    <h3><?php echo escape($site['name']); ?></h3>
                    <p><strong>URL:</strong> <a href="<?php echo escape($site['url']); ?>" target="_blank"><?php echo escape($site['url']); ?></a></p>
                    <p><strong>Описание:</strong> <?php echo escape($site['description']); ?></p>
                    <p><strong>Категория:</strong> <?php echo escape($site['category_name']); ?></p>
                    <p><strong>Репутация:</strong> <span class="reputation <?php echo $site['reputation'] < 0 ? 'negative' : ($site['reputation'] > 0 ? 'positive' : ''); ?>"><?php echo $site['reputation']; ?></span></p>
                </div>
            </div>

            <div class="tab-container">
                <div class="tab active" data-tab="stats"><i class="fas fa-chart-line"></i> Статистика</div>
                <div class="tab" data-tab="graph"><i class="fas fa-chart-area"></i> График</div>
                <div class="tab" data-tab="reviews-list"><i class="fas fa-list-alt"></i> Отзывы (<span id="review-count"><?php echo $total_root_review_count; ?></span>)</div>
            </div>

            <div id="stats-content" class="tab-content active">
                 <h3>Статистика счетчика</h3>
                <p>Статистика, которая собирается с установленного счетчика на вашем сайте.</p>

                <div class="stats-block-container">
                    <div class="stats-block-period">
                        <h4>За последний час</h4>
                         <p class="stats-item"><i class="fas fa-users"></i> Уникальных посетителей: <?php echo $stats_hour['unique_visitors']; ?></p>
                         <p class="stats-item"><i class="fas fa-mouse-pointer"></i> Просмотров: <?php echo $stats_hour['hits']; ?></p>
                    </div>

                    <div class="stats-block-period">
                        <h4>За сутки</h4>
                         <p class="stats-item"><i class="fas fa-users"></i> Уникальных посетителей: <?php echo $stats_day['unique_visitors']; ?></p>
                         <p class="stats-item"><i class="fas fa-eye"></i> Просмотров: <?php echo $stats_day['views']; ?></p>
                    </div>

                    <div class="stats-block-period">
                        <h4>За месяц</h4>
                         <p class="stats-item"><i class="fas fa-users-cog"></i> Уникальных посетителей: <?php echo $stats_month['unique_visitors']; ?></p>
                         <p class="stats-item"><i class="fas fa-chart-bar"></i> Просмотров: <?php echo $stats_month['views']; ?></p>
                    </div>
                </div>

             </div>

             <div id="graph-content" class="tab-content">
                 <h3>Статистика за 30 дней (ежедневно)</h3>
                 <div class="chart-container" style="position: relative; height:300px; width:100%; margin-bottom: 40px;">
                     <canvas id="dailyStatsChart"></canvas>
                 </div>

                 <h3>Статистика за последние 24 часа (по часам)</h3>
                  <?php if (empty($hourly_graph_data)): ?>
                      <p>Недостаточно данных для построения почасового графика за последние 24 часа.</p>
                  <?php else: ?>
                     <div class="chart-container" style="position: relative; height:300px; width:100%;">
                         <canvas id="hourlyStatsChart"></canvas>
                     </div>
                 <?php endif; ?>
             </div>


            <div id="reviews-list-content" class="tab-content">
                 <?php if (isLoggedIn()): ?>
                    <?php
                    // Проверяем, оставлял ли текущий пользователь уже корневой отзыв для этого сайта
                    $user_id = $_SESSION['user_id'];
                    $stmt = $conn->prepare("SELECT id FROM reviews WHERE site_id = ? AND user_id = ? AND parent_id IS NULL");
                    $stmt->bind_param("ii", $site_id, $user_id);
                    $stmt->execute();
                    $has_reviewed = $stmt->get_result()->num_rows > 0;
                    $stmt->close();

                    if (!$has_reviewed): // Если пользователь еще не оставил основной отзыв
                    ?>
                        <div class="review-form-container">
                             <h3>Оставить отзыв</h3>
                             <?php if (!empty($review_success)): ?>
                                <div class="alert alert-success"><?php echo escape($review_success); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($review_error)): ?>
                                <div class="alert alert-error"><?php echo escape($review_error); ?></div>
                            <?php endif; ?>
                             <?php echo $upload_errors_display; ?>

                            <form action="site.php?id=<?php echo $site['id']; ?>" method="POST" id="add-review-form" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="add_root_review"> <p>
                                    <label for="rating">Репутация:</label>
                                    <select id="rating" name="rating" class="input" required>
                                        <?php for ($i = 5; $i >= -5; $i--): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i > 0 ? '+' : ''; echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </p>
                                <p>
                                    <label for="comment">Ваш отзыв (до 1000 символов):</label>
                                    <textarea id="comment" name="comment" class="input" maxlength="1000"></textarea> </p>
                                <p>
                                    <label for="review_images">Прикрепить изображения (до 5 файлов, JPG, PNG, GIF, до 5MB каждый):</label>
                                    <input type="file" id="review_images" name="review_images[]" class="input" accept="image/jpeg, image/png, image/gif" multiple>
                                    <small>Максимум 5 файлов, каждый не более 5MB.</small>
                                </p>
                                <div id="selected-files-container" style="margin-top: 5px; font-size: 0.9em;"></div>

                                <button type="submit" class="btn btn-primary" id="submit-review-button" disabled><i class="fas fa-paper-plane"></i> Оставить отзыв</button>
                            </form>
                        </div>
                    <?php else: // Если пользователь уже оставил основной отзыв ?><p>Вы уже оставили основной отзыв на этот сайт.</p><?php endif; ?>
                 <?php else: // Если пользователь не авторизован ?>
                    <p>Пожалуйста, <a href="login.php">войдите</a>, чтобы оставить отзыв.</p>
                <?php endif; ?>

                <?php
                // Удален заголовок h3 "Отзывы пользователей" и лишние символы
                ?>
                 <?php if (!empty($review_success) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                     <div class="alert alert-success"><?php echo escape($review_success); ?></div>
                 <?php endif; ?>
                 <?php if (!empty($review_error) && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
                     <div class="alert alert-error"><?php echo escape($review_error); ?></div>
                 <?php endif; ?>
                 <?php if (!empty($block_user_message)): ?>
                     <div class="alert <?php echo strpos($block_user_message, 'успешно') !== false ? 'alert-success' : 'alert-error'; ?>"><?php echo escape($block_user_message); ?></div>
                 <?php endif; ?>


                <?php if (empty($reviews)): ?>
                    <p>Отзывов пока нет.</p>
                <?php else: ?>
                    <?php
                    // Функция для рекурсивного вывода отзывов и ответов
                    function displayReviews($reviews, $site_id, $loggedInUserId, $conn) {
                        foreach ($reviews as $review) {
                            // Проверяем статус блокировки автора ответа
                            $is_author_blocked = isUserBlocked($conn, $review['user_id']);
                            ?>
                            <div class="review" id="review-<?php echo $review['id']; ?>">
                                <div class="review-display">
                                    <p>
                                        <strong><?php echo escape($review['login']); ?></strong>
                                        <?php if ($review['parent_id'] === null): // Показываем оценку только для корневых отзывов ?>
                                            <span class="reputation <?php echo $review['rating'] < 0 ? 'negative' : ($review['rating'] > 0 ? 'positive' : ''); // ИСПРАВЛЕНО: Добавлен класс positive ?>">
                                                <?php echo $review['rating'] > 0 ? '+' : ''; ?>
                                                <?php echo escape($review['rating']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="review-date">(<?php echo $review['created_at']; ?>)</span>

                                        <?php if (isAdmin() && $review['parent_id'] !== null): // Кнопка "Заблокировать" / "Разблокировать" только для АДМИНА и только для ОТВЕТОВ ?>
                                             <?php
                                             $is_author_of_reply_blocked = isUserBlocked($conn, $review['user_id']);
                                             $button_text = $is_author_of_reply_blocked ? 'Разблокировать' : 'Заблокировать';
                                             $button_class = $is_author_of_reply_blocked ? 'btn-success' : 'btn-danger';
                                             $new_status_value = $is_author_of_reply_blocked ? 0 : 1;
                                             $icon_class = $is_author_of_reply_blocked ? 'fas fa-unlock' : 'fas fa-user-slash';
                                             ?>
                                             <form action="site.php?id=<?php echo $site_id; ?>&tab=reviews-list" method="POST" style="display: inline-block; margin-left: 10px;">
                                                  <input type="hidden" name="action" value="toggle_block_user">
                                                  <input type="hidden" name="user_to_block_id" value="<?php echo $review['user_id']; ?>">
                                                  <input type="hidden" name="block_status" value="<?php echo $new_status_value; ?>">
                                                  <button type="submit" class="btn btn-small <?php echo $button_class; ?>"><i class="<?php echo $icon_class; ?>"></i> <?php echo $button_text; ?></button>
                                             </form>
                                        <?php endif; ?>
                                    </p>
                                    <div class="review-comment"><?php echo nl2br(escape($review['comment'])); ?></div>

                                    <?php
                                    $images = isset($review['images']) ? $review['images'] : [];
                                    ?>

                                    <?php if (!empty($images)): ?>
                                        <div class="review-images-container">
                                            <?php foreach ($images as $image): ?>
                                                <?php
                                                $image_path_clean = ltrim($image['image_path'], '/');
                                                $image_url = SITE_URL . '/' . escape($image_path_clean);
                                                ?>
                                                <img src="<?php echo $image_url; ?>" alt="Изображение к отзыву" class="review-thumbnail" data-full-img-url="<?php echo $image_url; ?>">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($loggedInUserId !== null): ?>
                                        <?php
                                        $can_delete = false;
                                        if ((int)$review['user_id'] === (int)$loggedInUserId || isAdmin()) {
                                            $can_delete = true;
                                        }
                                        ?>

                                        <?php if ($can_delete): ?>
                                            <form action="site.php?id=<?php echo $site_id; ?>&tab=reviews-list" method="POST" class="delete-review-form" style="display: inline-block; margin-right: 5px;">
                                                 <input type="hidden" name="action" value="delete_review">
                                                 <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                 <button type="submit" class="btn btn-danger btn-small delete-button" onclick="return confirm('Вы уверены, что хотите удалить этот отзыв/ответ?');"><i class="fas fa-trash"></i> Удалить</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ((int)$review['user_id'] === (int)$loggedInUserId && $review['parent_id'] === null): ?>
                                            <button class="btn btn-secondary btn-small edit-review-button" data-review-id="<?php echo $review['id']; ?>"><i class="fas fa-edit"></i> Редактировать</button>
                                        <?php endif; ?>

                                        <?php if (!isUserBlocked($conn, $loggedInUserId)): ?>
                                             <button class="btn btn-secondary btn-small reply-button" data-review-id="<?php echo $review['id']; ?>"><i class="fas fa-reply"></i> Ответить</button>
                                        <?php endif; ?>

                                        <form action="site.php?id=<?php echo $site_id; ?>" method="POST" class="reply-form" id="reply-form-<?php echo $review['id']; ?>" style="display: none;">
                                            <input type="hidden" name="action" value="add_reply">
                                            <input type="hidden" name="parent_id" value="<?php echo $review['id']; ?>">
                                            <textarea name="comment" class="input" placeholder="Ваш ответ..." required maxlength="1000"></textarea>
                                            <button type="submit" class="btn btn-primary btn-small"><i class="fas fa-paper-plane"></i> Отправить ответ</button>
                                            <button type="button" class="btn btn-danger btn-small cancel-reply"><i class="fas fa-times"></i> Отмена</button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <div class="review-edit-form" id="edit-form-<?php echo $review['id']; ?>" style="display: none;">
                                     <h3>Редактировать отзыв</h3>
                                     <?php if (!empty($review_success) && strpos($review_success, 'обновлен') !== false): ?><div class="alert alert-success"><?php echo escape($review_success); ?></div><?php endif; ?>
                                     <?php if (!empty($review_error) && strpos($review_error, 'обновления') !== false): ?><div class="alert alert-error"><?php echo escape($review_error); ?></div><?php endif; ?>
                                     <form action="site.php?id=<?php echo $site_id; ?>" method="POST">
                                         <input type="hidden" name="action" value="edit_review">
                                         <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                         <p>
                                             <label for="edit_rating_<?php echo $review['id']; ?>">Репутация:</label>
                                             <select id="edit_rating_<?php echo $review['id']; ?>" name="rating" class="input" required>
                                                 <?php for ($i = 5; $i >= -5; $i--): ?>
                                                     <option value="<?php echo $i; ?>" <?php echo ((int)$review['rating'] === $i) ? 'selected' : ''; ?>><?php echo $i > 0 ? '+' : ''; echo $i; ?></option>
                                                 <?php endfor; ?>
                                             </select>
                                         </p>
                                         <p>
                                             <label for="edit_comment_<?php echo $review['id']; ?>">Комментарий (до 1000 символов):</label>
                                             <textarea id="edit_comment_<?php echo $review['id']; ?>" name="comment" class="input" maxlength="1000" required><?php echo escape($review['comment']); ?></textarea>
                                         </p>

                                         <?php if (!empty($images)): ?>
                                            <div class="edit-images-section" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #eee;">
                                                <h4>Прикрепленные изображения:</h4>
                                                <div class="edit-images-list" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                                                    <?php foreach ($images as $image): ?>
                                                         <?php
                                                         $image_path_clean = ltrim($image['image_path'], '/');
                                                         $image_url = SITE_URL . '/' . escape($image_path_clean);
                                                         ?>
                                                        <div class="edit-image-item" style="border: 1px solid #ccc; padding: 5px; border-radius: 5px; text-align: center; background-color: #fff;">
                                                            <img src="<?php echo $image_url; ?>" alt="Изображение" style="width: 60px; height: 60px; object-fit: cover; display: block; margin: 0 auto 5px auto;">
                                                            <label style="font-size: 0.9em; color: #555;">
                                                                <input type="checkbox" name="delete_images[]" value="<?php echo $image['id']; ?>"> Удалить
                                                            </label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                         <?php endif; ?>


                                         <button type="submit" class="btn btn-primary btn-small"><i class="fas fa-save"></i> Сохранить</button>
                                         <button type="button" class="btn btn-secondary btn-small cancel-edit"><i class="fas fa-times"></i> Отмена</button>
                                     </form>
                                </div>


                                <?php if (!empty($review['replies'])): ?>
                                    <div class="replies">
                                        <?php displayReviews($review['replies'], $site_id, $loggedInUserId, $conn); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                        }
                    }

                    $loggedInUserId = isLoggedIn() ? $_SESSION['user_id'] : null;
                    displayReviews($reviews, $site_id, $loggedInUserId, $conn);
                    ?>
                <?php endif; ?>
            </div>

        </div>

        <div class="sidebar-right">
            <h3>Категории сайтов</h3>
            <?php foreach ($categories as $category): ?>
                <a href="category.php?id=<?php echo $category['id']; ?>"><i class="fas fa-folder"></i> <?php echo escape($category['name']); ?></a> <?php endforeach; ?>
        </div>
    </div>

    <div id="imageLightbox" class="lightbox-modal hidden">
      <span class="close-lightbox">&times;</span>
      <img class="lightbox-content" id="lightboxImage">
      <div id="lightboxCaption"></div>
    </div>


     <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.main-content .tab-container .tab');
            const tabContents = document.querySelectorAll('.main-content .tab-content');

            let dailyStatsChart = null;
            let hourlyStatsChart = null;

            function renderDailyStatsChart(graphData) {
                const ctx = document.getElementById('dailyStatsChart');
                if (!ctx) return;
                if (dailyStatsChart) { dailyStatsChart.destroy(); }
                if (!Array.isArray(graphData)) { console.error("Data for daily chart is not an array:", graphData); return; }
                const labels = graphData.map(item => item.date);
                const viewsData = graphData.map(item => item.views);
                const uniqueData = graphData.map(item => item.unique_visitors);
                dailyStatsChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{ label: 'Просмотры', data: viewsData, borderColor: 'rgb(75, 192, 192)', tension: 0.1, fill: false }, { label: 'Уникальные посетители', data: uniqueData, borderColor: 'rgb(255, 99, 132)', tension: 0.1, fill: false }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 10 } } }, plugins: { legend: { display: true } } }
                });
            }

            function renderHourlyStatsChart(graphData) {
                 const ctx = document.getElementById('hourlyStatsChart');
                 if (!ctx) return;
                 if (hourlyStatsChart) { hourlyStatsChart.destroy(); }
                 if (!Array.isArray(graphData)) { console.error("Data for hourly chart is not an array:", graphData); return; }
                 const labels = graphData.map(item => item.hour + ':00');
                 const viewsData = graphData.map(item => item.views);
                 const uniqueData = graphData.map(item => item.unique_visitors);
                 hourlyStatsChart = new Chart(ctx, {
                     type: 'bar',
                     data: {
                         labels: labels,
                         datasets: [{ label: 'Просмотры', data: viewsData, backgroundColor: 'rgba(75, 192, 192, 0.5)', borderColor: 'rgb(75, 192, 192)', borderWidth: 1 }, { label: 'Уникальные посетители', data: uniqueData, backgroundColor: 'rgba(255, 99, 132, 0.5)', borderColor: 'rgb(255, 99, 132)', borderWidth: 1 }]
                     },
                     options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { maxRotation: 45, minRotation: 0, autoSkip: true, maxTicksLimit: 24 } } }, plugins: { legend: { display: true } } }
                 });
             }

            function activateTab(tabId) {
                tabs.forEach(tab => { tab.classList.remove('active'); });
                tabContents.forEach(content => { content.classList.remove('active'); content.style.display = 'none'; });
                const activeTab = document.querySelector(`.main-content .tab[data-tab="${tabId}"]`);
                const activeContent = document.getElementById(`${tabId}-content`);
                if (activeTab) { activeTab.classList.add('active'); }
                if (activeContent) { activeContent.classList.add('active'); activeContent.style.display = 'block'; }
                localStorage.setItem('activeSiteTab_<?php echo $site_id; ?>', tabId);

                if (tabId === 'graph') {
                     const dailyCanvas = document.getElementById('dailyStatsChart');
                     if (dailyCanvas) renderDailyStatsChart(<?php echo $daily_graph_data_json; ?>);
                     const hourlyCanvas = document.getElementById('hourlyStatsChart');
                     if (hourlyCanvas && <?php echo json_encode(!empty($hourly_graph_data)); ?>) { renderHourlyStatsChart(<?php echo $hourly_graph_data_json; ?>); hourlyCanvas.style.display = 'block'; } else if (hourlyCanvas) { hourlyCanvas.style.display = 'none'; }
                } else {
                     if (dailyStatsChart) { dailyStatsChart.destroy(); dailyStatsChart = null; }
                      if (hourlyStatsChart) { hourlyStatsChart.destroy(); hourlyStatsChart = null; }
                }
            }

            const urlParams = new URLSearchParams(window.location.search);
            const tabFromUrl = urlParams.get('tab');
            const hash = window.location.hash;
            let initialTab = tabFromUrl || localStorage.getItem('activeSiteTab_<?php echo $site_id; ?>') || (hash && hash.startsWith('#review-') ? 'reviews-list' : 'stats');
            activateTab(initialTab);

             tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    activateTab(tabId);
                });
            });

            const replyButtons = document.querySelectorAll('.reply-button');
            const replyForms = document.querySelectorAll('.reply-form');
            const cancelReplyButtons = document.querySelectorAll('.cancel-reply');

            replyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.getAttribute('data-review-id');
                    const form = document.getElementById('reply-form-' + reviewId);
                    replyForms.forEach(f => f.style.display = 'none');
                    document.querySelectorAll('.review-edit-form').forEach(f => f.style.display = 'none');
                    document.querySelectorAll('.review-display').forEach(d => d.style.display = 'block');
                    if (form) {
                        form.style.display = 'block';
                        const textarea = form.querySelector('textarea');
                        if (textarea) { textarea.focus(); }
                    }
                });
            });

            cancelReplyButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const form = this.closest('.reply-form');
                    if (form) { form.style.display = 'none'; const textarea = form.querySelector('textarea'); if (textarea) textarea.value = ''; }
                });
            });

            const commentTextarea = document.getElementById('comment');
            const reviewImageInput = document.getElementById('review_images');
            const submitReviewButton = document.getElementById('submit-review-button');
            const selectedFilesContainer = document.getElementById('selected-files-container');

            if (commentTextarea && submitReviewButton) {
                function checkSubmitButtonState() {
                    const filesSelected = reviewImageInput && reviewImageInput.files && reviewImageInput.files.length > 0;
                    const tooManyFilesSelected = filesSelected && reviewImageInput.files.length > 5;
                    if (commentTextarea.value.trim().length > 0 || (filesSelected && !tooManyFilesSelected)) {
                        submitReviewButton.disabled = false;
                    } else {
                        submitReviewButton.disabled = true;
                    }
                    if (selectedFilesContainer) {
                         const existingError = selectedFilesContainer.querySelector('.file-count-error');
                         if (tooManyFilesSelected) {
                             if (!existingError) {
                                 const errorDiv = document.createElement('div');
                                 errorDiv.classList.add('file-count-error');
                                 errorDiv.style.color = 'red';
                                 errorDiv.style.marginTop = '5px';
                                 errorDiv.textContent = `Ошибка: Выбрано ${reviewImageInput.files.length} файлов, но максимум разрешено 5.`;
                                 selectedFilesContainer.appendChild(errorDiv);
                             }
                         } else {
                             if (existingError) { existingError.remove(); }
                         }
                    }
                }
                commentTextarea.addEventListener('input', checkSubmitButtonState);
                if (reviewImageInput && selectedFilesContainer) {
                     reviewImageInput.addEventListener('change', function() {
                         selectedFilesContainer.innerHTML = '';
                         const files = this.files;
                         if (files.length > 0) {
                             const fileList = document.createElement('ul');
                             fileList.style.listStyle = 'none';
                             fileList.style.padding = '0';
                             fileList.style.margin = '5px 0 0 0';
                             for (let i = 0; i < files.length; i++) {
                                 const li = document.createElement('li');
                                  li.style.marginBottom = '3px';
                                  li.style.wordBreak = 'break-word';
                                 const fileName = files[i].name;
                                 const fileSizeMB = (files[i].size / 1024 / 1024).toFixed(2);
                                 const fileType = files[i].type;
                                 li.textContent = `Выбран: ${fileName} (${fileSizeMB} MB)`;
                                 const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                                 const maxSizeBytes = 5 * 1024 * 1024;
                                 let errorMessages = [];
                                 if (!allowedTypes.includes(fileType)) { errorMessages.push(`недопустимый тип (${fileType})`); }
                                 if (files[i].size > maxSizeBytes) { errorMessages.push(`слишком большой размер (макс ${maxSizeBytes/1024/1024} MB)`); }
                                 if (errorMessages.length > 0) { li.textContent += ' - Ошибка: ' + errorMessages.join(', '); li.style.color = 'red'; } else { li.style.color = '#555'; }
                                 fileList.appendChild(li);
                             }
                             selectedFilesContainer.appendChild(fileList);
                         } else { selectedFilesContainer.innerHTML = ''; }
                         checkSubmitButtonState();
                     });
                    checkSubmitButtonState();
                 }
            }

            const editReviewButtons = document.querySelectorAll('.edit-review-button');
            const cancelEditButtons = document.querySelectorAll('.review-edit-form .cancel-edit');

            editReviewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.getAttribute('data-review-id');
                    const reviewDiv = document.getElementById('review-' + reviewId);
                    const reviewDisplay = reviewDiv.querySelector('.review-display');
                    const editFormDiv = reviewDiv.querySelector('.review-edit-form');
                    if (reviewDisplay && editFormDiv) {
                         replyForms.forEach(f => f.style.display = 'none');
                         document.querySelectorAll('.review-edit-form').forEach(f => f.style.display = 'none');
                         document.querySelectorAll('.review-display').forEach(d => d.style.display = 'block');
                        reviewDisplay.style.display = 'none';
                        editFormDiv.style.display = 'block';
                         const textarea = editFormDiv.querySelector('textarea');
                         if (textarea) { textarea.focus(); }
                    }
                });
            });

            cancelEditButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const editFormDiv = this.closest('.review-edit-form');
                    const reviewDiv = editFormDiv.closest('.review');
                    const reviewDisplay = reviewDiv.querySelector('.review-display');
                    if (reviewDisplay && editFormDiv) {
                        editFormDiv.style.display = 'none';
                        reviewDisplay.style.display = 'block';
                         const alertErrors = editFormDiv.querySelectorAll('.alert-error');
                         alertErrors.forEach(alert => alert.remove());
                          const alertSuccess = editFormDiv.querySelectorAll('.alert-success');
                          alertSuccess.forEach(alert => alert.remove());
                    }
                });
            });

            const lightbox = document.getElementById('imageLightbox');
            const lightboxImg = document.getElementById('lightboxImage');
            const closeLightbox = document.querySelector('.close-lightbox');

            document.addEventListener('click', function(event) {
                const thumbnail = event.target.closest('.review-thumbnail');
                if (thumbnail) {
                    const fullImgUrl = thumbnail.getAttribute('data-full-img-url');
                    lightbox.classList.remove('hidden');
                    lightbox.style.display = 'flex';
                    lightboxImg.src = fullImgUrl;
                    event.preventDefault();
                }
            });

            if (closeLightbox) {
                 closeLightbox.addEventListener('click', function() {
                     lightbox.style.display = '';
                     lightbox.classList.add('hidden');
                     lightboxImg.src = '';
                 });
            }

            if (lightbox) {
                 lightbox.addEventListener('click', function(event) {
                     if (event.target === lightbox) {
                         lightbox.style.display = '';
                         lightbox.classList.add('hidden');
                         lightboxImg.src = '';
                     }
                 });
            }
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>