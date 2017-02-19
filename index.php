<?php
require_once 'admin/include/head.php';
?>
<!DOCTYPE HTML>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <title><?php print $setUp->getConfig("appname"); ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    
    <meta http-equiv="Content-Language" content="<?php print $encodeExplorer->lang; ?>" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <link rel="shortcut icon" href="admin/images/favicon.ico">
    <meta name="description" content="file manager">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
	<link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700" rel="stylesheet">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <link rel="stylesheet" href="admin/style.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="admin/skins/<?php print $setUp->getConfig('skin') ?>">
	<link rel="stylesheet" href="admin/css/mdb.min.css">
    <script type="text/javascript" src="admin/js/jquery.min.js"></script>
    <?php
    $bodyclass = 'body';
    if ($setUp->getConfig('inline_thumbs') == true) {
        $bodyclass .= ' inlinethumbs';
    } ?>
</head>
    <body id="uparea" class="<?php echo $bodyclass; ?>">
        <div class="overdrag"></div>
            <?php
            $template->getPart('activate');
            $template->getPart('navbar');
            $template->getPart('header');
            ?>
        <div class="container">
            <?php
            ?>
            <div id="error">
                <noscript>
                    <div class="response boh">
                        <span><i class="fa fa-exclamation-triangle"></i> 
                            Please activate JavaScript</span>
                    </div>
                </noscript>
                <?php echo $setUp->printAlert(); ?>
            </div>
            <div class="main-content">
            <?php 
            if ($getfilelist) :
                $template->getPart('downloader');

            elseif ($getrp) :
                $template->getPart('reset');
            else :
                if (!$getreg || $setUp->getConfig('registration_enable') == false) {
                    $template->getPart('user-redirect');
                    $template->getPart('notify-users');
                    $template->getPart('uploadarea');
                    $template->getPart('breadcrumbs');
                    $template->getPart('list-folders');
                    $template->getPart('list-files');
                    $template->getPart('disk-space');
                }
                if ($getreg && $setUp->getConfig('registration_enable') == true) {
                    $template->getPart('register');
                } else {
                    $template->getPart('login');
                }
            endif; ?>
            </div>
        </div>
        <?php
                $template->getPart('footer');
                $template->getPart('load-js');
                $template->getPart('modals');
            ?>
    </body>
</html>