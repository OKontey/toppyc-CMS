<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/load.php';

//$domain = SITE_URL;
$domain = $MainCfg['domain'];

if (!isLoggedIn()) {
    redirect('login.php');
}

$errors = [];
$success = '';
$counter_code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $url = trim($_POST['url']);
    $description = trim($_POST['description']);
    $category_id = (int)$_POST['category_id'];
    $user_id = $_SESSION['user_id'];

    // Валидация полей формы (существующая)
    if (strlen($name) > 20) {
        $errors[] = 'Название сайта не должно превышать 20 символов.';
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Некорректный URL.';
    }
    if (strlen($description) > 100) {
        $errors[] = 'Описание не должно превышать 100 символов.';
    }
    $stmt = $conn->prepare("SELECT id FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $errors[] = 'Выбранная категория не существует.';
    }
    $stmt->close();

    // Проверка существования пользователя
    $stmt = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        $errors[] = 'Пользователь не существует.';
    }
    $stmt->close();

    // Проверка на дубликат сайта (по URL и user_id)
    $stmt = $conn->prepare("SELECT id FROM sites WHERE url = ? AND user_id = ?");
    $stmt->bind_param("si", $url, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = 'Вы уже добавили сайт с таким URL.';
    }
    $stmt->close();

    // --- Обработка загрузки файла скриншота ---
    $screenshot_uploaded = false;
    $screenshot_filename = '';

    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['screenshot']['tmp_name'];
        $file_name = $_FILES['screenshot']['name'];
        $file_size = $_FILES['screenshot']['size'];
        $file_type = $_FILES['screenshot']['type'];
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Валидация файла
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 2 * 1024 * 1024; // 2MB

        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = 'Разрешены только файлы изображений (JPG, JPEG, PNG, GIF).';
        }
        if ($file_size > $max_file_size) {
            $errors[] = 'Размер файла скриншота не должен превышать 2MB.';
        }

        // Если нет ошибок загрузки файла
        if (empty($errors)) {
            $screenshot_uploaded = true;
            // Временное имя файла (пока нет ID сайта)
             $screenshot_filename = uniqid() . '.' . $file_extension;
        }
    } else {
        // Если файл не был загружен или произошла ошибка при загрузке
        switch ($_FILES['screenshot']['error']) {
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'Не выбран файл скриншота.';
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                 $errors[] = 'Размер загруженного файла превысил допустимый лимит.';
                 break;
            default:
                 $errors[] = 'Ошибка загрузки файла скриншота. Код ошибки: ' . $_FILES['screenshot']['error'];
                 break;
        }
    }
    // --- Конец обработки загрузки файла ---


    // Если нет ошибок (включая ошибки загрузки файла), добавляем сайт
    if (empty($errors)) {
        $reputation = 0;
        $stmt = $conn->prepare("INSERT INTO sites (user_id, name, url, description, category_id, reputation) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssii", $user_id, $name, $url, $description, $category_id, $reputation);
        if ($stmt->execute()) {
            $site_id = $stmt->insert_id;

            // --- Сохранение загруженного скриншота ---
            if ($screenshot_uploaded) {
                 // Генерируем конечное имя файла на основе ID сайта
                 $final_screenshot_filename = $site_id . '.' . $file_extension;
                 $upload_path = SCREENSHOT_PATH . $final_screenshot_filename;

                 // Убедимся, что папка для скриншотов существует
                 if (!is_dir(SCREENSHOT_PATH)) {
                    mkdir(SCREENSHOT_PATH, 0755, true); // Создаем папку, если её нет
                 }

                 // Перемещаем загруженный файл в конечную папку с новым именем
                 if (move_uploaded_file($file_tmp_path, $upload_path)) {
                     // Файл успешно загружен и переименован
                 } else {
                     // Ошибка перемещения файла (редко, но может быть из-за прав или других проблем)
                     $errors[] = 'Ошибка при сохранении файла скриншота на сервере.';
                     // Возможно, стоит удалить добавленную запись сайта, если скриншот не сохранился
                     // $conn->query("DELETE FROM sites WHERE id = $site_id");
                 }
            } else {
                 // Если файл не был загружен, но других ошибок не было (не должно произойти при required)
                 // Здесь можно использовать заглушку, если загрузка файла не обязательна,
                 // но по вашему запросу файл должен быть загружен.
            }
            // --- Конец сохранения загруженного скриншота ---


            $success = 'Сайт успешно добавлен и отправлен на модерацию.';
            // Генерация кода счётчика (сделано кликабельным)
            $counter_code = "<a href=\"$domain/site.php?id=$site_id\" target=\"_blank\"><img src=\"$domain/counter-image.php?site_id=$site_id\" alt=\"Счётчик\"></a>";
        } else {
            $errors[] = 'Ошибка добавления сайта. Попробуйте снова. Код ошибки: ' . $stmt->error;
        }
        $stmt->close();
    }
}

// Получение списка категорий
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
    <title>Добавить сайт - Toppyc.ru</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> </head>
<body>
    <div class="container">
        <?php include 'sidebar_left.php'; ?>

        <div class="main-content">
            <h2>Добавить сайт</h2>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo escape($success); ?></div>
                <?php if ($counter_code): ?>
                    <h3>Установите счётчик на ваш сайт</h3>
                    <p>Скопируйте и вставьте этот код в HTML вашего сайта, чтобы отслеживать статистику в реальном времени:</p>
                    <textarea class="input" readonly rows="3"><?php echo escape($counter_code); ?></textarea>
                     <button class="btn btn-primary" onclick="copyCounterCodeAddSite()"><i class="fas fa-copy"></i> Копировать</button> <?php endif; ?>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo escape($error); ?></p>
                    <? endforeach; ?>
                </div>
            <?php endif; ?>
            <form action="add_site.php" method="POST" enctype="multipart/form-data"> <p>
                    <label for="name">Название (не более 20 символов):</label>
                    <input type="text" id="name" name="name" class="input" maxlength="20" required value="<?php echo isset($_POST['name']) ? escape($_POST['name']) : ''; ?>"> </p>
                <p>
                    <label for="url">URL:</label>
                    <input type="url" id="url" name="url" class="input" required value="<?php echo isset($_POST['url']) ? escape($_POST['url']) : ''; ?>"> </p>
                <p>
                    <label for="description">Описание (не более 100 символов):</label>
                    <textarea id="description" name="description" class="input" maxlength="100" required><?php echo isset($_POST['description']) ? escape($_POST['description']) : ''; ?></textarea> </p>
                <p>
                    <label for="category_id">Категория:</label>
                    <select id="category_id" name="category_id" class="input" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>><?php echo escape($category['name']); ?></option> <?php endforeach; ?>
                    </select>
                </p>
                 <p>
                    <label for="screenshot">Скриншот сайта (JPG, PNG, GIF, до 2MB):</label>
                    <input type="file" id="screenshot" name="screenshot" class="input" accept="image/jpeg, image/png, image/gif" required> </p>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Добавить</button>
            </form>
        </div>

        <div class="sidebar-right">
            <h3>Категории сайтов</h3>
            <?php foreach ($categories as $category): ?>
                <a href="category.php?id=<?php echo $category['id']; ?>"><i class="fas fa-folder"></i> <?php echo escape($category['name']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>
     <script>
        // Функция для копирования кода счетчика на странице добавления сайта
        function copyCounterCodeAddSite() {
            const textarea = document.querySelector('.main-content textarea[readonly]');
            textarea.select();
            document.execCommand('copy');
            alert('Код счетчика скопирован!'); // Простое уведомление
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>