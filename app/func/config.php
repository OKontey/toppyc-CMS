<?php
//---connect
define('DB_HOST', 'localhost'); // host
define('DB_USER', 'irich_topsites0'); // user BD
define('DB_NAME', 'irich_topsites0'); // base name
define('DB_PASS', 'topsites0-3x'); // pass BD

//---Init
/*
$MainCfg = array(
    'domain' => 'https://'.$_SERVER["HTTP_HOST"],
    'root_func' => $_SERVER["DOCUMENT_ROOT"]."/app/func/",
    'root_pages' => $_SERVER["DOCUMENT_ROOT"]."/app/pages/",
    'root_models' => $_SERVER["DOCUMENT_ROOT"]."/app/models/",
    'root_blocks' => $_SERVER["DOCUMENT_ROOT"]."/app/blocks/",
    'root_assets' => $_SERVER["DOCUMENT_ROOT"]."/app/assets/",
);
*/

//---con
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Ошибка подключения к базе данных: " . $conn->connect_error);
}
$conn->set_charset("utf8");
?>