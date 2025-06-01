<!---HTML-->
<h1>Категория: <?php echo escape($category['name']); ?></h1>
            <div class="tab-container">
                <div class="tab active"><i class="fas fa-list"></i> Сайты в категории</div> </div>
            <?php if (empty($sites)): ?>
                <p>В этой категории пока нет сайтов.</p>
            <?php else: ?>
                <?php foreach ($sites as $index => $site): ?>
                    <div class="site-card">
                        <?php
                        // Путь к скриншоту (заглушке, если нет реального)
                        $screenshot_path = 'screenshots/' . $site['id'] . '.png';
                        if (!file_exists($screenshot_path)) {
                            $screenshot_path = DEF_img; // Убедитесь, что placeholder.png существует
                        }
                        ?>
                        <img src="<?php echo escape($screenshot_path); ?>" alt="Скриншот сайта <?php echo escape($site['name']); ?>">

                        <div class="site-info">
                             <h3><a href="/site/<?php echo $site['id']; ?>"><?php echo escape($site['name']); ?></a></h3>
                            <p>Описание: <?php echo escape($site['description']); ?></p>
                            <p>Категория: <?php echo escape($site['category_name']); ?></p>
                        </div>
                        <div class="site-stats">
                             <p><i class="fas fa-eye"></i> Просмотры: <?php echo $site['views']; ?></p>
                             <a href="/site/<?php echo $site['id']; ?>" class="btn btn-info btn-small"><i class="fas fa-info-circle"></i> Подробнее</a> </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
