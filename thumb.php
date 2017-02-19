<?php
require_once 'admin/config.php';
session_name($_CONFIG["session_name"]);
session_start();
require_once 'admin/class.php'; 
if (!GateKeeper::isAccessAllowed()) {
    die('access denied');
}
$imageServer = new ImageServer();
$imageServer->showImage();
exit;
?>