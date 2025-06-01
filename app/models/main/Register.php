<?
$page_title = 'Регистрация на проекте';

if (isLoggedIn()) {
    redirect('/');
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
            redirect('/profile');
        } else {
            $errors[] = 'Ошибка регистрации. Попробуйте снова.';
        }
        $stmt->close();
    }
}
?>