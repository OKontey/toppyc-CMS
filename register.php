<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/app/load.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $email = trim($_POST['email']);
    $captcha_input = $_POST['captcha'];

    // Валидация
    if (strlen($login) > 15) {
        $errors[] = 'Логин не должен превышать 15 символов.';
    }
    if (strlen($password) < 6 || strlen($password) > 30) { // Добавлена проверка минимальной длины пароля
        $errors[] = 'Пароль должен быть от 6 до 30 символов.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Пароли не совпадают.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Некорректный email.';
    }
    if (!verifyCaptcha($captcha_input)) {
        $errors[] = 'Неверный код капчи.';
    }

    // Проверка уникальности логина и email
    $stmt = $conn->prepare("SELECT id FROM users WHERE login = ?");
    $stmt->bind_param("s", $login);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = 'Логин уже занят.';
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = 'Email уже используется.';
    }
    $stmt->close();

    // Если нет ошибок, регистрируем пользователя
    if (empty($errors)) {
        // Проверяем, есть ли уже пользователи в базе (первый станет админом)
        $result = $conn->query("SELECT COUNT(*) AS count FROM users");
        $row = $result->fetch_assoc();
        $role = $row['count'] == 0 ? 'admin' : 'user';

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (login, password, email, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $login, $hashed_password, $email, $role);
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            $_SESSION['user_id'] = $user_id;
            $_SESSION['login'] = $login;
            $_SESSION['role'] = $role;
            redirect('profile.php');
        } else {
            $errors[] = 'Ошибка регистрации. Попробуйте снова.';
        }
        $stmt->close();
    }
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
    <title>Регистрация - Toppyc.ru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar_left.php'; ?>

        <div class="main-content">
            <h2>Регистрация</h2>
             <div class="tab-container">
                <div class="tab active"><i class="fas fa-user-plus"></i> Создать аккаунт</div> </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo escape($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form action="register.php" method="POST">
                <p>
                    <label for="login">Логин (не более 15 символов):</label>
                    <input type="text" id="login" name="login" class="input" maxlength="15" required>
                </p>
                <p>
                    <label for="password">Пароль (от 6 до 30 символов):</label> <input type="password" id="password" name="password" class="input" maxlength="30" required>
                </p>
                <p>
                    <label for="password_confirm">Повторите пароль:</label>
                    <input type="password" id="password_confirm" name="password_confirm" class="input" maxlength="30" required>
                </p>
                <p>
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" class="input" required>
                </p>
                <p>
                    <label for="captcha">Код капчи:</label>
                    <img src="captcha.php" alt="Капча" style="margin: 5px 0;">
                    <input type="text" id="captcha" name="captcha" class="input" maxlength="4" required>
                </p>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Зарегистрироваться</button>
            </form>
        </div>

        <div class="sidebar-right">
            <h3>Категории сайтов</h3>
            <?php
            $result = $conn->query("SELECT id, name FROM categories ORDER BY name");
            while ($row = $result->fetch_assoc()): ?>
                <a href="category.php?id=<?php echo $row['id']; ?>"><i class="fas fa-folder"></i> <?php echo escape($row['name']); ?></a> <?php endwhile; ?>
        </div>
    </div>
</body>
</html>

<?php
$conn->close();
?>