<?
function readRoutesRules($loadfile) {
    $routesPath = root_app . $loadfile;
    $loadRoutes = include($routesPath); 
    return $loadRoutes;
}

function getUri() {
    if (!empty($_SERVER['REQUEST_URI'])){
        return trim($_SERVER['REQUEST_URI'], '/');
    }
}

function notFound() {
    return array('main','Notfound','index');
}

function preStrReturn($str){
    $str = json_encode($str);
    $str = $str ? '<pre>'.$str.'</pre>' : '<pre>preStrReturn is empty!</pre>';
    echo $str;
}

function checkExists($path){
    //$str = json_encode($str);\
    if (file_exists($path)) {
        return true;
    }
    return false;
}

//explode(",", $str);
$loadRoutes = [];
$loadRoutes += readRoutesRules('urls/main.php');
$loadRoutes += readRoutesRules('urls/cab.php');
$loadRoutes += readRoutesRules('urls/adm.php');
//preStrReturn($loadRoutes); //---dev

//echo '<br><br>';

$uri = getUri();
preStrReturn($uri); //---dev

//---logout
if ($uri == 'exit' || $uri == 'logout') {
    //unset($_SESSION);
    session_unset();
    session_destroy();
    header('location: /');
}

$result = false;
if($loadRoutes){

    /*
    $loadRoutes += [
        RICH => 'adm/Home',
        RICH.'/' => 'adm/Home',
        RICH.'/[a-z0-9]' => 'adm/Pages'
    ];
    */

    foreach ($loadRoutes as $uriPattern => $path) {
        //echo 'uriPattern='.$uriPattern.'; path='.$path.'<br>'; // test
        //echo 'params='.$params; // test
        if (preg_match("~^$uriPattern$~", $uri)) {

            $internalRoute = preg_replace("~$uriPattern~", $path, $uri);
            //echo '<br><br>'; // test
            //echo 'uriPattern='.$uriPattern; //---dev
            preStrReturn('internalRoute - '.$internalRoute); //---dev
            preStrReturn('uriPattern - '.$uriPattern); //---dev

            $arrContollerParams = explode('/', $internalRoute);
            preStrReturn($arrContollerParams); //---dev

            $result = true;
            break;
        }
    } // end foreach
} // end if loadRoutes

if ($result == false) {
    $arrContollerParams = notFound();
}
//echo 'result='.$result;
$router_include = '';

if($arrContollerParams){
    //preStrReturn($arrContollerParams); // test
    //foreach ($arrContollerParams as $key => $val) {
        //echo 'key='.$key.'; val='.$val.'<br>'; // test
        $model = $arrContollerParams[0];
        $page = $arrContollerParams[1];
        //$view = $arrContollerParams[2];

        $path_Model = root_models.$model.'/'.$page.'.php';
        $path_View = root_pages.$model.'/'.$page.'.php';

        //preStrReturn($path_Model); //---dev
        //preStrReturn($path_View); //---dev

        //echo $model;
        //$globalModel = R_ROOT.'/pages/models/'.$model.'.php';
        //include($globalModel); 

        
        if(checkExists($path_Model)){
            $router_include = $path_Model;
        }
        if(checkExists($path_View)){
            $router_include_view = $path_View;
        }

        //print_r($path_Model); // test
        //echo '<br><br>'; // test
        //print_r($path_View); // test

    //}
}//else echo 'none arrContollerParams';

//preStrReturn($result); // test
?>