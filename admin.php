<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/load.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Обработка действий (удаление пользователя, одобрение/удаление сайта, удаление категории)
if (isset($_GET['action']) && isAdmin()) {
    $action = $_GET['action'];
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    switch ($action) {
        case 'delete_user':
            if ($id > 0) {
                // Перед удалением пользователя, можно переназначить его сайты
                // или удалить их. В этом примере просто удаляем пользователя.
                $conn->query("DELETE FROM users WHERE id = $id");
            }
            break;
        case 'approve_site':
            if ($id > 0) {
                $conn->query("UPDATE sites SET approved = 1 WHERE id = $id");
            }
            break;
        case 'delete_site':
            if ($id > 0) {
                // Удаляем связанные данные
                $conn->query("DELETE FROM stats WHERE site_id = $id");
                $conn->query("DELETE FROM reviews WHERE site_id = $id");
                // Удаляем записи stats_visitors, связанные со stats для этого сайта
                $conn->query("DELETE sv FROM stats_visitors sv JOIN stats s ON sv.stat_id = s.id WHERE s.site_id = $id");

                $conn->query("DELETE FROM sites WHERE id = $id");
                // Удаляем скриншот, если он существует
                $screenshot_path = SCREENSHOT_PATH . $id . '.png';
                if (file_exists($screenshot_path)) {
                    unlink($screenshot_path);
                }
            }
            break;
         case 'add_category':
             if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_name'])) {
                 $category_name = trim($_POST['category_name']);
                 if (!empty($category_name)) {
                     $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                     $stmt->bind_param("s", $category_name);
                     $stmt->execute();
                     $stmt->close();
                 }
             }
             break;
        case 'edit_category':
             if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['category_name']) && $id > 0) {
                 $category_name = trim($_POST['category_name']);
                 if (!empty($category_name)) {
                     $stmt = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
                     $stmt->bind_param("si", $category_name, $id);
                     $stmt->execute();
                     $stmt->close();
                 }
             }
             break;
        case 'delete_category':
            if ($id > 0) {
                // Перед удалением категории, нужно решить, что делать с сайтами в этой категории
                // В этом примере, сайты останутся без категории (можно добавить NULL) или должны быть переназначены.
                // Простой вариант: удалить сайты в этой категории (если CASCADE DELETE настроен в БД) или установить category_id в NULL
                 $conn->query("UPDATE sites SET category_id = NULL WHERE category_id = $id"); // Устанавливаем категорию в NULL
                 $conn->query("DELETE FROM categories WHERE id = $id"); // Удаляем категорию
            }
            break;
    }

    // Перенаправление обратно в админку после действия, сохраняя активную вкладку
    $active_tab = isset($_POST['active_tab']) ? $_POST['active_tab'] : (isset($_GET['tab']) ? $_GET['tab'] : 'users'); // Получаем активную вкладку
    redirect("admin.php?tab=" . urlencode($active_tab)); // Перенаправляем с параметром вкладки
}

// Получение данных для вкладок
// Пользователи
$users = [];
$result = $conn->query("SELECT id, login, email, created_at, role FROM users ORDER BY created_at DESC");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

// Сайты на модерации
$pending_sites = [];
$result = $conn->query("SELECT s.id, s.name, s.url, s.description, u.login AS user_login, c.name AS category_name
                       FROM sites s
                       JOIN users u ON s.user_id = u.id
                       JOIN categories c ON s.category_id = c.id
                       WHERE s.approved = 0
                       ORDER BY s.id ASC");
while ($row = $result->fetch_assoc()) {
    $pending_sites[] = $row;
}

// Все сайты (одобренные)
$approved_sites = [];
$result = $conn->query("SELECT s.id, s.name, s.url, s.description, u.login AS user_login, c.name AS category_name
                       FROM sites s
                       JOIN users u ON s.user_id = u.id
                       JOIN categories c ON s.category_id = c.id
                       WHERE s.approved = 1
                       ORDER BY s.id ASC");
while ($row = $result->fetch_assoc()) {
    $approved_sites[] = $row;
}

// Категории
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

// Получение данных для редактирования категории, если запрошено
$edit_category = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit_category' && isset($_GET['id'])) {
    $category_id_to_edit = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id, name FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id_to_edit);
    $stmt->execute();
    $edit_category = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}


// Получение списка категорий для правого сайдбара (если нужно)
$sidebar_categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $sidebar_categories[] = $row;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - Toppyc.ru</title>
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> </head>
<body>
    <div class="container">
        <?php include 'sidebar_left.php'; ?>

        <div class="main-content">
            <h2>Админ-панель</h2>

            <div class="tab-container">
                <div class="tab" data-tab="users"><i class="fas fa-users"></i> Пользователи</div> <div class="tab" data-tab="pending-sites"><i class="fas fa-hourglass-half"></i> Модерация сайтов</div> <div class="tab" data-tab="approved-sites"><i class="fas fa-check-circle"></i> Одобренные сайты</div> <div class="tab" data-tab="categories"><i class="fas fa-tags"></i> Категории</div> </div>

            <div id="users-content" class="tab-content"> <h3>Список пользователей</h3>
                 <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Логин</th>
                            <th>Email</th>
                            <th>Дата регистрации</th>
                            <th>Роль</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user_item): ?>
                            <tr>
                                <td><?php echo $user_item['id']; ?></td>
                                <td><?php echo escape($user_item['login']); ?></td>
                                <td><?php echo escape($user_item['email']); ?></td>
                                <td><?php echo $user_item['created_at']; ?></td>
                                <td><?php echo escape($user_item['role']); ?></td>
                                <td>
                                    <?php if ($user_item['id'] !== $_SESSION['user_id']): // Нельзя удалить самого себя ?>
                                        <a href="admin.php?action=delete_user&id=<?php echo $user_item['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Вы уверены, что хотите удалить пользователя <?php echo escape($user_item['login']); ?>?');"><i class="fas fa-trash-alt"></i> Удалить</a>
                                    <?php else: ?>
                                         <span style="color: #999;">(Вы)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div id="pending-sites-content" class="tab-content"> <h3>Сайты на модерации</h3>
                 <?php if (empty($pending_sites)): ?>
                     <p>Нет сайтов на модерации.</p>
                 <?php else: ?>
                    <table>
                       <thead>
                           <tr>
                               <th>ID</th>
                               <th>Название</th>
                               <th>URL</th>
                               <th>Пользователь</th>
                               <th>Категория</th>
                               <th>Действия</th>
                           </tr>
                       </thead>
                       <tbody>
                           <?php foreach ($pending_sites as $site): ?>
                               <tr>
                                   <td><?php echo $site['id']; ?></td>
                                   <td><a href="site.php?id=<?php echo $site['id']; ?>" target="_blank"><?php echo escape($site['name']); ?></a></td>
                                   <td><a href="<?php echo escape($site['url']); ?>" target="_blank"><?php echo escape($site['url']); ?></a></td>
                                   <td><?php echo escape($site['user_login']); ?></td>
                                   <td><?php echo escape($site['category_name']); ?></td>
                                   <td>
                                       <a href="admin.php?action=approve_site&id=<?php echo $site['id']; ?>" class="btn btn-success btn-small"><i class="fas fa-check"></i> Одобрить</a>
                                       <a href="admin.php?action=delete_site&id=<?php echo $site['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Вы уверены, что хотите удалить сайт <?php echo escape($site['name']); ?>?');"><i class="fas fa-trash-alt"></i> Удалить</a>
                                   </td>
                               </tr>
                           <?php endforeach; ?>
                       </tbody>
                   </table>
                 <?php endif; ?>
            </div>

             <div id="approved-sites-content" class="tab-content"> <h3>Одобренные сайты</h3>
                 <?php if (empty($approved_sites)): ?>
                     <p>Нет одобренных сайтов.</p>
                 <?php else: ?>
                    <table>
                       <thead>
                           <tr>
                               <th>ID</th>
                               <th>Название</th>
                               <th>URL</th>
                               <th>Пользователь</th>
                               <th>Категория</th>
                               <th>Действия</th>
                           </tr>
                       </thead>
                       <tbody>
                           <?php foreach ($approved_sites as $site): ?>
                               <tr>
                                   <td><?php echo $site['id']; ?></td>
                                   <td><a href="site.php?id=<?php echo $site['id']; ?>" target="_blank"><?php echo escape($site['name']); ?></a></td>
                                   <td><a href="<?php echo escape($site['url']); ?>" target="_blank"><?php echo escape($site['url']); ?></a></td>
                                   <td><?php echo escape($site['user_login']); ?></td>
                                   <td><?php echo escape($site['category_name']); ?></td>
                                   <td>
                                        <a href="site.php?id=<?php echo $site['id']; ?>" class="btn btn-info btn-small"><i class="fas fa-chart-line"></i> Статистика</a>
                                       <a href="admin.php?action=delete_site&id=<?php echo $site['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Вы уверены, что хотите удалить сайт <?php echo escape($site['name']); ?>?');"><i class="fas fa-trash-alt"></i> Удалить</a>
                                   </td>
                               </tr>
                           <?php endforeach; ?>
                       </tbody>
                   </table>
                 <?php endif; ?>
            </div>

             <div id="categories-content" class="tab-content"> <h3>Управление категориями</h3>
                 <?php if ($edit_category): ?>
                    <h4>Редактировать категорию: <?php echo escape($edit_category['name']); ?></h4>
                     <form action="admin.php?action=edit_category&id=<?php echo $edit_category['id']; ?>" method="POST">
                         <p>
                             <label for="category_name">Название категории:</label>
                             <input type="text" id="category_name" name="category_name" class="input" value="<?php echo escape($edit_category['name']); ?>" required>
                         </p>
                         <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button>
                          <a href="admin.php?tab=categories" class="btn btn-secondary"><i class="fas fa-times"></i> Отмена</a> <input type="hidden" name="active_tab" value="categories"> </form>
                 <?php else: ?>
                     <h4>Добавить новую категорию</h4>
                     <form action="admin.php?action=add_category" method="POST">
                         <p>
                             <label for="category_name">Название категории:</label>
                             <input type="text" id="category_name" name="category_name" class="input" required>
                         </p>
                         <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Добавить</button>
                         <input type="hidden" name="active_tab" value="categories"> </form>
                 <?php endif; ?>

                <h4 style="margin-top: 30px; padding-top: 20px;">Существующие категории</h4>
                <?php if (empty($categories)): ?>
                    <p>Категорий пока нет.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Название</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo $category['id']; ?></td>
                                    <td><?php echo escape($category['name']); ?></td>
                                    <td>
                                        <a href="admin.php?action=edit_category&id=<?php echo $category['id']; ?>&tab=categories" class="btn btn-info btn-small"><i class="fas fa-edit"></i> Изменить</a> <?php if ($conn->query("SELECT COUNT(*) FROM sites WHERE category_id = {$category['id']}")->fetch_row()[0] == 0): // Проверка, есть ли сайты в категории ?>
                                             <a href="admin.php?action=delete_category&id=<?php echo $category['id']; ?>" class="btn btn-danger btn-small" onclick="return confirm('Вы уверены, что хотите удалить категорию «<?php echo escape($category['name']); ?>»?');"><i class="fas fa-trash-alt"></i> Удалить</a>
                                        <?php else: ?>
                                             <span style="color: #999; font-size: 12px;">(Есть сайты)</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>


        </div>

        <div class="sidebar-right">
            <h3>Категории сайтов</h3>
            <?php foreach ($sidebar_categories as $category): ?>
                <a href="category.php?id=<?php echo $category['id']; ?>"><i class="fas fa-folder"></i> <?php echo escape($category['name']); ?></a>
            <?php endforeach; ?>
        </div>
    </div>

     <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab-container .tab');
            const tabContents = document.querySelectorAll('.main-content .tab-content');

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
                localStorage.setItem('activeAdminTab', tabId); // Уникальный ключ для админки
            }

            // Восстановление активной вкладки при загрузке
            // Проверяем, есть ли параметр 'tab' в URL (после действия)
            const urlParams = new URLSearchParams(window.location.search);
            const tabFromUrl = urlParams.get('tab');

            const initialTab = tabFromUrl || localStorage.getItem('activeAdminTab') || 'users'; // Приоритет: URL > localStorage > default
            activateTab(initialTab);


            // Назначаем обработчики кликов на табы
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    activateTab(tabId);
                });
            });

             // Если мы на странице редактирования категории, переключаемся на вкладку категорий
             <?php if ($edit_category): ?>
             activateTab('categories');
             <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>