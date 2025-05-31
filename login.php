<?php
session_start();
require_once 'config.php';
require_once 'functions.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, login, password, role FROM users WHERE login = ?");
    $stmt->bind_param("s", $login);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['login'] = $user['login'];
        $_SESSION['role'] = $user['role'];
        redirect('profile.php');
    } else {
        $errors[] = 'Неверный логин или пароль.';
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
    <title>Вход - Toppyc.ru</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="container">
        <?php include 'sidebar_left.php'; ?>

        <div class="main-content">
            <h2>Вход</h2>
             <div class="tab-container">
                <div class="tab active"><i class="fas fa-sign-in-alt"></i> Авторизация</div> </div>
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo escape($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form action="login.php" method="POST">
                <p>
                    <label for="login">Логин:</label>
                    <input type="text" id="login" name="login" class="input" maxlength="15" required>
                </p>
                <p>
                    <label for="password">Пароль:</label>
                    <input type="password" id="password" name="password" class="input" maxlength="30" required>
                </p>
                <button type="submit" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Войти</button>
            </form>
            <p>Нет аккаунта? <a href="register.php">Зарегистрируйтесь</a>.</p>
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