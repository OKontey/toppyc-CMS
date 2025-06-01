<?php
$init_err = 0; //---dev
if($init_err){
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	ini_set('error_reporting', E_ALL);
}

define('R_HOST', $_SERVER["HTTP_HOST"]);
define('R_ROOT', $_SERVER["DOCUMENT_ROOT"]);

define('root_app', R_ROOT.'/app/');
define('root_func', R_ROOT.'/app/func/');
define('root_pages', R_ROOT.'/app/pages/');
define('root_models', R_ROOT.'/app/models/');
define('root_blocks', R_ROOT.'/app/blocks/');
define('root_assets', '/app/assets/');

define('asset_js', root_assets.'js/');
define('asset_css', root_assets.'css/');
define('asset_img', root_assets.'img/');

define('DEF_img', '/screenshots/placeholder.png');


//echo root_func; //---dev
define('RICH', 'cpanel');

//define('SITE_URL', 'https://toppyc.ru');
define('SCREENSHOT_PATH', R_ROOT.'/screenshots/');

//---glob
require_once $_SERVER['DOCUMENT_ROOT'].'/app/load.php';
require_once root_models.'/main.php';

//---pages


//---page Model
/*if($router_include){
    @include($router_include);
}*/
@include(initorView('model'));
//@include(initorView()['model']);
//initorView('model');
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=R_HOST?> - <?=$page_title?></title>
    <link rel="stylesheet" href="<?=asset_css?>styles.css?v=1.1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <div class="container">

        <!-- sidebar_left -->
        <?php require_once root_blocks.'sidebar_left.php'; ?>
        
        <!-- workpart -->
        <div class="main-content">
        <?
        //initorView('view');
        //@include(initorView()['view']);
        @include(initorView('view'));
        /*if($router_include_view){
            @include($router_include_view);
        }*/
        ?>
        </div>

        <!-- sidebar_right -->
        <?php require_once root_blocks.'sidebar_right.php'; ?>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Для главной страницы просто скрываем/показываем блоки, если есть несколько вкладок
            // Если вкладка всего одна ("Топ-10"), этот скрипт может быть упрощен или убран.
            // Оставим его на случай добавления других вкладок.

            const tabs = document.querySelectorAll('.tab-container .tab');
            const tabContents = document.querySelectorAll('.main-content .tab-content');

             // Функция для переключения вкладок
            function activateTab(tabId) {
                tabs.forEach(tab => {
                    tab.classList.remove('active');
                });
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    content.style.display = 'none'; // Явно скрываем
                });

                const activeTab = document.querySelector(`.tab[data-tab="${tabId}"]`);
                const activeContent = document.getElementById(`${tabId}-content`);

                if (activeTab) {
                    activeTab.classList.add('active');
                }
                if (activeContent) {
                    activeContent.classList.add('active');
                    activeContent.style.display = 'block'; // Явно показываем
                }

                // На главной странице можно не сохранять вкладку в localStorage,
                // если всегда по умолчанию показывается Топ-10.
                // localStorage.setItem('activeHomeTab', tabId);
            }

            // Активируем вкладку по умолчанию (Топ-10) при загрузке
             // Проверяем, есть ли блоки содержимого, прежде чем пытаться активировать
             if (tabContents.length > 0) {
                activateTab('top-10'); // Активируем вкладку Топ-10 по умолчанию
             }


            // Назначаем обработчики кликов на табы (если есть несколько вкладок)
             tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    activateTab(tabId);
                });
            });

        });
     </script>
</body>
</html>

<?php
$conn->close();
?>