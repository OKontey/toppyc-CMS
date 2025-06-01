<h1>Вход</h1>
 <div class="tab-container">
    <div class="tab active"><i class="fas fa-sign-in-alt"></i> Авторизация</div> </div>
<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $error): ?>
            <p><?php echo escape($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<form action="/login" method="POST">
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
<p>Нет аккаунта? <a href="/register">Зарегистрируйтесь</a>.</p>