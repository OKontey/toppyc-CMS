<?
$page_title = 'Новости проекта';

// Получение списка категорий для сайдбара
$news = [];
$result = $conn->query("SELECT * FROM news ORDER BY id DESC");
if($result){
    while ($row = $result->fetch_assoc()) {
        $news[] = $row;
    }
}
?>