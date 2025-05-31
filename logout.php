<?php
session_start();
require_once 'functions.php';

// Очистка сессии
session_unset();
session_destroy();

// Перенаправление на главную страницу
redirect('index.php');
?>