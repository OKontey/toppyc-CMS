<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/load.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Получение данных пользователя
$stmt = $conn->prepare("SELECT login, email, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Получение сайтов пользователя
$user_sites = [];
$stmt = $conn->prepare("SELECT s.id, s.name, s.url, s.approved, c.name AS category_name
                        FROM sites s
                        JOIN categories c ON s.category_id = c.id
                        WHERE s.user_id = ?
                        ORDER BY s.id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $user_sites[] = $row;
}
$stmt->close();


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
    <title>Кабинет - Toppyc.ru</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> </head>
<body>
    <div class="container">
        <?php include 'sidebar_left.php'; ?>

        <div class="main-content">
            <h2>Кабинет</h2>

            <div class="tab-container">
                <div class="tab active" data-tab="profile"><i class="fas fa-user"></i> Профиль</div> <div class="tab" data-tab="my-sites"><i class="fas fa-globe"></i> Мои сайты</div> </div>

            <div id="profile-content" class="tab-content active"> <h3>Данные профиля</h3>
                <p><strong>Логин:</strong> <?php echo escape($user['login']); ?></p>
                <p><strong>Email:</strong> <?php echo escape($user['email']); ?></p>
                <p><strong>Дата регистрации:</strong> <?php echo $user['created_at']; ?></p>
                </div>

            <div id="my-sites-content" class="tab-content"> <h3>Мои сайты</h3>
                 <p><a href="add_site.php" class="btn btn-primary"><i class="fas fa-plus"></i> Добавить сайт</a></p> <?php if (empty($user_sites)): ?>
                    <p>У вас пока нет добавленных сайтов.</p>
                <?php else: ?>
                     <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>URL</th>
                                <th>Категория</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_sites as $site): ?>
                                <tr>
                                    <td><?php echo $site['id']; ?></td>
                                    <td><a href="site.php?id=<?php echo $site['id']; ?>"><?php echo escape($site['name']); ?></a></td>
                                    <td><a href="<?php echo escape($site['url']); ?>" target="_blank"><?php echo escape($site['url']); ?></a></td>
                                    <td><?php echo escape($site['category_name']); ?></td>
                                    <td><?php echo $site['approved'] ? '<span style="color: green;">Одобрен</span>' : '<span style="color: orange;">На модерации</span>'; ?></td>
                                    <td>
                                        <a href="site.php?id=<?php echo $site['id']; ?>" class="btn btn-info btn-small"><i class="fas fa-chart-line"></i> Статистика</a> <?php
                                        // Генерация кода счётчика для этого сайта
                                        $counter_code = "<a href=\"" . SITE_URL . "/site.php?id=" . $site['id'] . "\" target=\"_blank\"><img src=\"" . SITE_URL . "/counter-image.php?site_id=" . $site['id'] . "\" alt=\"Счётчик\"></a>";
                                        ?>
                                        <button class="btn btn-primary btn-small counter-btn" data-counter='<?php echo htmlspecialchars($counter_code, ENT_QUOTES, 'UTF-8'); ?>'><i class="fas fa-code"></i> Счётчик</button> </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="modal" id="counterModal" style="display: none;"> <div class="modal-content">
                <span class="close-modal" id="closeModal">&times;</span> <h3>Код счётчика</h3>
                <p>Скопируйте этот код и вставьте его в HTML вашего сайта:</p>
                <textarea class="input" id="counterCodeArea" readonly rows="5"></textarea> <button class="btn btn-primary" onclick="copyCounterCode()"><i class="fas fa-copy"></i> Копировать</button> </div>
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
            const tabs = document.querySelectorAll('.tab-container .tab'); // Выбираем табы внутри контейнера
            const tabContents = document.querySelectorAll('.main-content .tab-content'); // Выбираем содержимое табов внутри main-content

            // Функция для переключения вкладок
            function activateTab(tabId) {
                tabs.forEach(tab => {
                    tab.classList.remove('active');
                });
                tabContents.forEach(content => {
                    // Скрываем все блоки содержимого
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

                // Сохраняем активную вкладку в localStorage
                localStorage.setItem('activeProfileTab', tabId); // Уникальный ключ для профиля
            }

            // Восстановление активной вкладки при загрузке
            const initialTab = localStorage.getItem('activeProfileTab') || 'profile'; // Вкладка по умолчанию 'profile'
            activateTab(initialTab);


            // Назначаем обработчики кликов на табы
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    activateTab(tabId);
                });
            });

            // --- Логика модального окна счётчика ---
            const modal = document.getElementById('counterModal');
            const closeModalSpan = document.getElementById('closeModal');
            const counterCodeArea = document.getElementById('counterCodeArea');
            const counterButtons = document.querySelectorAll('.counter-btn');

            // Открытие модального окна по клику на кнопку "Счётчик"
            counterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const code = this.getAttribute('data-counter');
                    counterCodeArea.value = code;
                    modal.style.display = 'flex'; // Используем flex для центрирования
                });
            });

            // Закрытие модального окна по клику на крестик
            closeModalSpan.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            // Закрытие модального окна по клику вне окна
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

             // Функция копирования кода из textarea
            window.copyCounterCode = function() {
                counterCodeArea.select();
                document.execCommand('copy');
                alert('Код счётчика скопирован!'); // Простое уведомление
            };

        });
    </script>
</body>
</html>

<?php
$conn->close();
?>