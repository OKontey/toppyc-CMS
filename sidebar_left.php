<?php
session_start();
require_once 'functions.php';
?>

<div class="sidebar-left">
    <h3>Toppyc.ru</h3>
    <?php if (isLoggedIn()): ?>
        <a href="index.php" class="sidebar-btn btn btn-primary"><i class="fas fa-home"></i> Главная</a>
        <a href="profile.php" class="sidebar-btn btn btn-primary"><i class="fas fa-user"></i> Кабинет</a>
        <?php if (isAdmin()): ?>
            <a href="admin.php" class="sidebar-btn btn btn-admin"><i class="fas fa-cog"></i> Админ-панель</a>
        <?php endif; ?>
        <a href="logout.php" class="sidebar-btn btn btn-primary"><i class="fas fa-sign-out-alt"></i> Выйти</a>
    <?php else: ?>
        <form action="login.php" method="POST">
            <p>
                <input type="text" name="login" class="input sidebar-input" placeholder="Логин" maxlength="15" required>
            </p>
            <p>
                <input type="password" name="password" class="input sidebar-input" placeholder="Пароль" maxlength="30" required>
            </p>
            <button type="submit" class="sidebar-btn btn btn-primary"><i class="fas fa-sign-in-alt"></i> Вход</button>
        </form>
        <a href="register.php" class="sidebar-btn btn btn-primary"><i class="fas fa-user-plus"></i> Регистрация</a>
    <?php endif; ?>
    
    <div class="sidebar-section">
        <h4>Последняя новость</h4>
        <a href="news.php" class="sidebar-btn btn btn-primary"><i class="fas fa-newspaper"></i> Читать новости</a>
    </div>
    
    <div class="sidebar-section">
        <h4>Навигация по сайту</h4>
        <a href="contact.php" class="sidebar-btn btn btn-primary"><i class="fas fa-envelope"></i> Контакты</a>
        <a href="rules.php" class="sidebar-btn btn btn-primary"><i class="fas fa-book"></i> Правила</a>
    </div>
</div>