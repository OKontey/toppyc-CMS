<h1>Регистрация</h1>
<div class="tab-container">
    <div class="tab active"><i class="fas fa-user-plus"></i> Создать аккаунт</div> </div>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo escape($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<form action="/register" method="POST">
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
        <img src="/app/captcha.php" alt="Капча" style="margin-bottom: -3px;">
        <input type="text" id="captcha" name="captcha" class="input" maxlength="4" required>
    </p>
    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Зарегистрироваться</button>
</form>