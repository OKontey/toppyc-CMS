<h1>Новости</h1>

<div class="tab-container">
    <div class="tab active"><i class="fas fa-newspaper"></i> Последние новости</div>
</div>

<div>
    <?php if(empty($news)):?>

        <p>Новостей пока нет</p>

    <?php else: ?>
    <?php foreach ($news as $row): ?>

        <h3><?=$row['title']?></h3>
        <p><?=$row['text']?></p>
        <p><strong>Дата публикации:</strong> <?=date('Y-m-d H:i',$row['date_add']);?></p>

        <hr>

    <?php endforeach; ?>
    <?php endif; ?>
</div>