
            <div class="tab-container">
                <div class="tab active" data-tab="top-10"><i class="fas fa-chart-bar"></i> Топ-10</div>
            </div>

            <div id="top-10-content" class="tab-content active">
                <?php if (empty($sites)): ?>
                    <p>Пока нет сайтов для отображения в Топ-10.</p>
                <?php else: ?>
                    <div class="site-block-grid">
                        <?php foreach ($sites as $site): ?>
                            <div class="site-widget">
                                <div class="site-widget-image-container">
                                     <a href="site.php?id=<?php echo $site['id']; ?>">
                                        <img src="<?php echo escape($site['screenshot']); ?>" alt="Скриншот сайта <?php echo escape($site['name']); ?>">
                                     </a>
                                </div>
                                <div class="site-widget-content">
                                    <h3><a href="site.php?id=<?php echo $site['id']; ?>"><?php echo escape($site['name']); ?></a></h3>
                                    <p><i class="fas fa-user"></i> Уникальные: <?php echo $site['unique_visitors']; ?></p>
                                    <p><i class="fas fa-eye"></i> Просмотры: <?php echo $site['views']; ?></p>
                                     <p><i class="fab fa-yandex"></i> Яндекс ИКС: <?php echo escape($site['yandex_iks']); ?></p>
                                    <div class="site-widget-buttons">
                                        <a href="<?php echo escape($site['url']); ?>" target="_blank" class="btn btn-primary btn-small"><i class="fas fa-globe"></i> На сайт</a>
                                        <a href="site.php?id=<?php echo $site['id']; ?>" class="btn btn-info btn-small"><i class="fas fa-chart-line"></i> Статистика</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
      