<?php
if (preg_match('/\.(?:png|jpg|jpeg|gif|css|js)$/', $_SERVER["REQUEST_URI"])) {
    return false;    // serve the requested resource as-is.
}
if(preg_match("/.*upload.*/",$_SERVER["REQUEST_URI"])){
    $_GET['action'] = 'upload';
}
else if(preg_match("/tracks\/(.*)/",$_SERVER["REQUEST_URI"], $matches)){
    $_GET['action'] = 'track';
    $_GET['track'] = $matches[1];
 #   print_r($matches);
}
#print_r($_GET);
include('index.php');

?>
