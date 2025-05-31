<?
$page_title = '';

///app/func
require_once root_func.'pages.php';

// Получение списка категорий
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
if($result){
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}
?>