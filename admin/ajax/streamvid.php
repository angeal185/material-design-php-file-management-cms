<?php
require_once '../config.php';
session_name($_CONFIG["session_name"]);
session_start();
require_once '../class.php'; 
if (!GateKeeper::isAccessAllowed()) {
    die('access denied');
}
$get = filter_input(INPUT_GET, 'vid', FILTER_SANITIZE_STRING);
$path = '../../'.base64_decode($get);
$utils = new Utils();
if ($get && $utils->checkVideo($path) == true) {
    include_once '../class/videostream.php';

    $stream = new VideoStream($path);
    $stream->start();
}
exit;