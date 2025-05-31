<div class="sidebar-right">
    <h3>Категории сайтов</h3>
    <?php if (empty($categories)): ?>
        <p class="text-center">Нет Категорий</p>
    <?php else: ?>
    <?php foreach ($categories as $category): ?>
        <a href="category.php?id=<?php echo $category['id']; ?>"><i class="fas fa-folder"></i> <?php echo escape($category['name']); ?></a>
    <?php endforeach; ?>
    <?php endif; ?>
</div>