<?php
$parent = basename($_SERVER["SCRIPT_FILENAME"]);
$islogin = ($parent === "login.php" ? true : false);
$logoAlignment = $setUp->getConfig("align_logo");
$fulldesc = $setUp->getDescription();

if ($setUp->getConfig("show_head") !== true && $fulldesc == false) {
    return;
} ?>

            <header class="header">
                <div class="container">
            <?php
            if ($setUp->getConfig("show_head") == true ) { 

                if ($islogin == true) { 
                    $logopath = "images/";
                } else {
                    $logopath = "admin/images/";
                }
                ?>
                <div class="head-banner text-<?php echo $logoAlignment; ?>">
                    <a href="<?php echo $setUp->getConfig("script_url"); ?>">
                        <img alt="<?php print $setUp->getConfig('appname'); ?>" src="<?php print $logopath.$setUp->getConfig('logo'); ?>">
                    </a>
                </div>
            <?php
            }          
            if ($gateKeeper->isAccessAllowed() 
                && !$getcloud 
                && $islogin == false
                && $fulldesc
            ) { ?>
                <div class="description lead"><?php echo $fulldesc; ?></div> 
            <?php
            } ?>
                </div>
            </header>