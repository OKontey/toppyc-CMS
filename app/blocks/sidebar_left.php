<?php
//session_start();
//require_once 'functions.php';
?>

<div class="sidebar-left">
    <h3><?=R_HOST?></h3>
    <?php if (isLoggedIn()): ?>
        <a href="/" class="sidebar-btn btn btn-primary"><i class="fas fa-home"></i> Главная</a>
        <a href="/profile" class="sidebar-btn btn btn-primary"><i class="fas fa-user"></i> Кабинет</a>
        <?php if (isAdmin()): ?>
            <a href="/<?=RICH?>" class="sidebar-btn btn btn-admin"><i class="fas fa-cog"></i> Админ-панель</a>
        <?php endif; ?>
        <a href="/logout" class="sidebar-btn btn btn-primary"><i class="fas fa-sign-out-alt"></i> Выйти</a>
    <?php else: ?>
        <form action="/login" class="mb-0" method="POST">
            <p>
                <input type="text" name="login" class="input sidebar-input" placeholder="Логин" maxlength="15" required>
            </p>
            <p>
                <input type="password" name="password" class="input sidebar-input" placeholder="Пароль" maxlength="30" required>
            </p>
            <button type="submit" class="sidebar-btn btn btn-primary mb-0"><i class="fas fa-sign-in-alt"></i> Вход</button>
        </form>
        <a href="/register" class="sidebar-btn btn btn-primary"><i class="fas fa-user-plus"></i> Регистрация</a>
    <?php endif; ?>
    
    <div class="sidebar-section">
        <h4>Последняя новость</h4>
        <a href="/news" class="sidebar-btn btn btn-primary"><i class="fas fa-newspaper"></i> Читать новости</a>
    </div>
    
    <div class="sidebar-section">
        <h4>Навигация по сайту</h4>
        <a href="/contact" class="sidebar-btn btn btn-primary"><i class="fas fa-envelope"></i> Контакты</a>
        <a href="/rules" class="sidebar-btn btn btn-primary"><i class="fas fa-book"></i> Правила</a>
    </div>
</div>