<?php
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest')
) {
    exit;
}
function base64ToJpg($base64_string, $output_file) 
{
    $ifp = fopen($output_file, "wb"); 
    $data = explode(',', $base64_string);

    fwrite($ifp, base64_decode($data[1])); 
    fclose($ifp); 
    //return $output_file; 
}

$relativepath = '../images/avatars';
if (!is_dir($relativepath)) {
    mkdir($relativepath, 0755);         
}
$finalavatar = 'admin/images/avatars/'.$_POST['imgName'].'.png';
$relative = $relativepath.'/'.$_POST['imgName'].'.png';
base64ToJpg($_POST['imgData'], $relative);
echo $finalavatar;
