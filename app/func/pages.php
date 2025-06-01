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
    return array('main','Notfound');
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

function paramUrl() {
    $uri = getUri();
    $uri = explode('/', $uri);
    if(isset($uri)){

        //preStrReturn('uri isset'); //---dev

        $paramUrl['view'] = !empty($uri[0]) ? $uri[0] : '/';
        if(isset($uri[1])){
            $paramUrl['param'] = $uri[1];
            if(isset($uri[2])){
                $paramUrl['page'] = (int)$uri[2];
            }
        }
    }//else $paramUrl['view'] = 'home';
    return $paramUrl;
}

function includeRules(){
    //explode(",", $str);
    $loadRoutes = [];
    $loadRoutes += readRoutesRules('urls/main.php');
    $loadRoutes += readRoutesRules('urls/cab.php');
    $loadRoutes += readRoutesRules('urls/adm.php');
    //preStrReturn($loadRoutes); //---dev
    return $loadRoutes;
}

function checkRules(){
    $result = false;
    $loadRoutes = includeRules();
    $uri = paramUrl()['view'];
    if($loadRoutes && $uri){

        preStrReturn('loadRoutes && uri TRUE'); //---dev
        /*
        $loadRoutes += [
            RICH => 'adm/Home',
            RICH.'/' => 'adm/Home',
            RICH.'/[a-z0-9]' => 'adm/Pages'
        ];
        */
    
        foreach ($loadRoutes as $uriPattern => $path) {
            //preStrReturn('uriPattern - '.$uriPattern); //---dev
            //preStrReturn($path); //---dev
            if (preg_match("~^$uriPattern$~", $uri)) {
    
                $internalRoute = preg_replace("~$uriPattern~", $path, $uri);
                //preStrReturn('internalRoute - '.$internalRoute); //---dev
    
                $arrContollerParams = explode('/', $internalRoute);
                //preStrReturn($arrContollerParams); //---dev
    
                $result = true;
                break;
            }
        } // end foreach
    }// end if loadRoutes
    if ($result == false) {
        $arrContollerParams = notFound();
    }
    return $arrContollerParams;
}

function getParam($arr){
    if($arr){
        $par['model'] = $arr[0];
        $par['page'] = $arr[1];
        return $par;
    }
    return preStrReturn('ERR getParam func');
}

function rootFile($arr=false,$type='model'){
    if($arr){
        $path = getParam($arr);
        if(!$path) return preStrReturn('ERR rootFile func / path');

        $res = ($type =='model') ? root_models : root_pages;
        
        $includer = $res.$path["model"].'/'.$path["page"].'.php';
        preStrReturn($includer); //---dev
        return $includer;
    }
    return preStrReturn('ERR rootFile func');
}

function initorView($type){
    $arrContollerParams = checkRules();
    $finInclude = [];
    return rootFile($arrContollerParams,$type);
}
?>