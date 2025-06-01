<?php
//---model
$page_title = 'Список категорий';
$catId = false;

// Проверка, передан ли ID категории
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $catId = true;
}


if ($catId){
    $category_id = (int)$_GET['id'];

    // Получение информации о категории
    $stmt = $conn->prepare("SELECT name FROM categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $category = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$category) {
        redirect('/category');
    }

    $page_title = 'Сайты категории '.$category["name"];

    // Получение списка сайтов в категории
    $sites = [];
    $result = $conn->query("SELECT s.id, s.name, s.url, s.description, s.views, c.name AS category_name
                            FROM sites s
                            JOIN categories c ON s.category_id = c.id
                            WHERE s.category_id = $category_id AND s.approved = 1
                            ORDER BY s.views DESC");
    while ($row = $result->fetch_assoc()) {
        $sites[] = $row;
    }

}

// Получение списка категорий для сайдбара
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
?>