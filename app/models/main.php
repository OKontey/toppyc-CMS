<?
$page_title = '';

///app/func
require_once root_func.'pages.php';

//---logout
if ($paramUrl['view'] == 'exit' || $paramUrl['view'] == 'logout') {
    //unset($_SESSION);
    session_unset();
    session_destroy();
    header('location: /');
}

// Получение списка категорий
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
if($result){
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>