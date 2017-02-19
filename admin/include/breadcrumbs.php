<?php
if ($setUp->getConfig("show_path") == true
    && $gateKeeper->isAccessAllowed() 
    && $location->editAllowed()
) { ?>
    <ol class="breadcrumb">
        <li>
            <a href="?dir=">
                <i class="fa fa-folder-open"></i> <?php print $encodeExplorer->getString("root"); ?>
            </a>
        </li>
    <?php 
    $totdirs = count($location->path);
    foreach ($location->path as $key => $dir) {
        if ($key > 0) {
            $stolink = $encodeExplorer->makeLink(false, null, $location->getDir(false, true, false, $totdirs -1 - $key)); ?>
            <li><a href="<?php echo $stolink; ?>">
                <i class="fa fa-folder-open-o"></i> 
                <?php echo urldecode($location->getPathLink($key, false)); ?>
            </a></li>
        <?php
        }
    } ?>
    </ol>
<?php
}
