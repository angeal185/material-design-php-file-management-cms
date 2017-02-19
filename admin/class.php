<?php
$fm_version = '0.0.1';
class ImageServer
{
    public static function showImage()
    {
        $thumb = filter_input(INPUT_GET, 'thumb', FILTER_SANITIZE_STRING);
        if ($thumb) {
            $inline = (isset($_GET['in']) ? true : false);
            if (strlen($thumb) > 0
                && (SetUp::getConfig('thumbnails') == true
                || SetUp::getConfig('inline_thumbs') == true)
            ) {
                ImageServer::showThumbnail(base64_decode($thumb), $inline);
            }
            return true;
        }
        return false;
    }
    public static function isEnabledPdf()
    {
        if (class_exists('Imagick')) {
            return true;
        }
        return false;
    }
    public static function openPdf($file)
    {
        if (!ImageServer::isEnabledPdf()) {
            return false;
        }
        $file = urldecode($file);
        $img = new Imagick($file.'[0]');
        $img->setImageFormat('png');
        $str = $img->getImageBlob();
        $im2 = imagecreatefromstring($str);
        $image = $im2 ? $im2 : imagecreatefromjpeg('admin/images/placeholder.jpg');
        return $im2;
    }
    public static function createThumbnail($file, $inline = false)
    {
        if ($inline == true) {
            // $thumbsize = SetUp::getConfig('inline_tw');
            $thumbsize = 420;

            $max_width = $thumbsize;
            $max_height = $thumbsize;
        } else {
            if (is_int(SetUp::getConfig('thumbnails_width'))) {
                $max_width = SetUp::getConfig('thumbnails_width');
            } else {
                $max_width = 760;
            }
            if (is_int(SetUp::getConfig('thumbnails_height'))) {
                $max_height = SetUp::getConfig('thumbnails_height');
            } else {
                $max_height = 800;
            }
        }
        if (File::isPdfFile($file)) {
            $image = ImageServer::openPdf($file);
        } else {
            $image = ImageServer::openImage($file);
        }
        if ($image == false) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $new_width = $max_width;
        $new_height = $max_height;

        // background color for transparent images
        $bgR = 240;
        $bgG = 240;
        $bgB = 240;

        if ($inline == true) {
            // crop to square thumbnail
            if ($width > $height) {
                $y = 0;
                $x = ($width - $height) / 2;
                $smallestSide = $height;
            } else {
                $x = 0;
                $y = ($height - $width) / 2;
                $smallestSide = $width;
            }
            $thumb = imagecreatetruecolor($new_width, $new_height);
            $bgcolor = imagecolorallocate($thumb, $bgR, $bgG, $bgB);
            imagefilledrectangle($thumb, 0, 0, $new_width, $new_height, $bgcolor);
            imagecopyresampled($thumb, $image, 0, 0, $x, $y, $new_width, $new_height, $smallestSide, $smallestSide);
        } else {
            // resize mantaining aspect ratio
            if (($width/$height) > ($new_width/$new_height)) {
                $new_height = $new_width * ($height / $width);
            } else {
                $new_width = $new_height * ($width / $height);
            }
            $new_width = ($new_width >= $width ? $width : $new_width);
            $new_height = ($new_height >= $height ? $height : $new_height);
            $thumb = imagecreatetruecolor($new_width, $new_height);
            $bgcolor = imagecolorallocate($thumb, $bgR, $bgG, $bgB);
            imagefilledrectangle($thumb, 0, 0, $new_width, $new_height, $bgcolor);
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        }
        return $thumb;
    }
    public static function showThumbnail($file, $inline = false)
    {
        $thumbsdir = 'admin/thumbs';
        
        if (!is_dir($thumbsdir)) {
            if (!mkdir($thumbsdir, 0755)) {
                Utils::setError('error creating /admin/thumbs/ directory');
                return false;
            }
        }
        if ($inline === true) {
            $thumbname = md5($file).'.jpg';
        } else {
            $thumbname = md5($file).'-big.jpg';
        }

        $thumbpath = $thumbsdir.'/'.$thumbname;

        if (!file_exists($thumbpath)) {
            $file = EncodeExplorer::extraChars($file);
            $image = ImageServer::createThumbnail($file, $inline);
            imagejpeg($image, $thumbpath, 80);
            imagedestroy($image); 
        }

        if ($inline) {
            return $thumbpath;
        } else {
            header('Location: '.$thumbpath);
            exit;
        }
    }
    public static function openImage($file)
    {
        $file = urldecode($file);
        $imageInfo = getimagesize($file);
        $memoryNeeded = (($imageInfo[0] * $imageInfo[1]) * $imageInfo['bits']);
        $memoryLimit = (strlen(ini_get('memory_limit')) > 0 ? ImageServer::returnBytes(ini_get('memory_limit')) : false);
        $lowmemory = false;
        /*memory_limit */
        if ($memoryLimit && $memoryNeeded > $memoryLimit) {
            $lowmemory = true;
            $formatneeded = (round($memoryNeeded/1024/1024)+10).'M';
            if (ini_set('memory_limit', $formatneeded)) {
                $lowmemory = false;
            }
        } 
        if ($lowmemory === false) {
            switch ($imageInfo['mime']) {
            case 'image/jpeg':
                $img = imagecreatefromjpeg($file);
                break;
            case 'image/gif':
                $img = imagecreatefromgif($file);
                break;
            case 'image/png':
                $img = imagecreatefrompng($file);
                break;
            default:
                $img = imagecreatefromjpeg($file);
                break;
            }
        } else {
            imagecreatefromjpeg('admin/images/placeholder.jpg');
        }
        return $img;
    }
    public static function returnBytes($size_str)
    {
        switch (substr($size_str, -1)) {
        case 'M':
        case 'm':
            return (int)$size_str * 1048576;
        case 'K':
        case 'k':
            return (int)$size_str * 1024;
        case 'G':
        case 'g':
            return (int)$size_str * 1073741824;
        default:
            return $size_str;
        }
    }
}
class Logger
{
    public static function log($message, $relpath = 'admin/')
    {
        if (SetUp::getConfig('log_file') == true) {
            $logjson = $relpath.'log/'.date('Y-m-d').'.json';

            if (Location::isFileWritable($logjson)) {

                $message['time'] = date('H:i:s');

                if (file_exists($logjson)) {
                    $oldlog = json_decode(file_get_contents($logjson), true);
                } else {
                    $oldlog = array();
                }

                $daily = date('Y-m-d');
                $oldlog[$daily][] = $message;

                file_put_contents($logjson, json_encode($oldlog, JSON_FORCE_OBJECT));

            } else {
                Utils::setError('The script does not have permissions to write inside "admin/log" folder. check CHMOD');
                return;
            }
        }
    }
    public static function logAccess()
    {
        $message = '<td>'.GateKeeper::getUserInfo('name').'</td>'
        .'<td><span class="label label-warning">ACCESS</span></td>'
        .'<td>--</td><td class="wordbreak">--</td>';
        Logger::log($message);
    }
    public static function logCreation($path, $isDir)
    {
        $path = addslashes($path);

        $message = array(
            'user' => GateKeeper::getUserInfo('name'),
            'action' => 'ADD',
            'type' => $isDir ? 'folder':'file',
            'item' => $path
        );
        Logger::log($message);
        if (!$isDir && SetUp::getConfig('notify_upload')) {
            Logger::emailNotification($path, 'upload');
        }
        if ($isDir && SetUp::getConfig('notify_newfolder')) {
            Logger::emailNotification($path, 'newdir');
        }
    }
    public static function logDeletion($path, $isDir, $remote = false)
    {
        $path = addslashes($path);
        $message = array(
            'user' => GateKeeper::getUserInfo('name'),
            'action' => 'REMOVE',
            'type' => $isDir ? 'folder':'file',
            'item' => $path
        );
        if ($remote == false) {
            Logger::log($message);
        } else {
            Logger::log($message, '');
        }
    }
    public static function logDownload($path, $folder = false)
    {
        $user = GateKeeper::getUserInfo('name') ? GateKeeper::getUserInfo('name') : '--';
        $mailmessage = '';
        $type = $folder ? 'folder' : 'file';
        if (is_array($path)) {
            foreach ($path as $value) {
                $path = addslashes($value);
                $message = array(
                    'user' => $user,
                    'action' => 'DOWNLOAD',
                    'type' => $type,
                    'item' => $path
                );
                $mailmessage .= $path."\n";
                Logger::log($message, "");
            }
        } else {
            $path = addslashes($path);
            $message = array(
                'user' => $user,
                'action' => 'DOWNLOAD',
                'type' => $type,
                'item' => $path
            );
            $mailmessage = $path;
            Logger::log($message, "");
        }
        if (SetUp::getConfig('notify_download')) {
            Logger::emailNotification($mailmessage, 'download');
        }
    }
    public static function logPlay($path)
    {
        $path = addslashes($path);
        $message = array(
            'user' =>  GateKeeper::getUserInfo('name') ? GateKeeper::getUserInfo('name') : '--',
            'action' => 'PLAY',
            'type' => 'file',
            'item' => $path
        );
        Logger::log($message, '');
    }
    public static function emailNotification($path, $action = false)
    {
        global $encodeExplorer;
        if (strlen(SetUp::getConfig('upload_email')) > 5) {
            $time = SetUp::formatModTime(time());
            $appname = SetUp::getConfig('appname');
            switch ($action) {
            case 'download':
                $title = $encodeExplorer->getString('new_download');
                break;
            case 'upload':
                $title = $encodeExplorer->getString('new_upload');
                break;
            case 'newdir':
                $title = $encodeExplorer->getString('new_directory');
                break;
            case 'login':
                $title = $encodeExplorer->getString('new_access');
                break;
            default:
                $title = $encodeExplorer->getString('new_activity');
                break;
            }
            $message = $time."\n\n";
            $message .= "IP   : ".$_SERVER['REMOTE_ADDR']."\n";
            $message .= $encodeExplorer->getString('user')." : ".GateKeeper::getUserInfo('name')."\n";
            $message .= $encodeExplorer->getString('path')." : ".$path."\n";

    // send to multiple recipients
            // $sendTo = SetUp::getConfig('upload_email').',cc1@example.com,cc2@example.com';            
            $sendTo = SetUp::getConfig('upload_email');
            $from = "=?UTF-8?B?".base64_encode($appname)."?=";
            mail(
                $sendTo,
                "=?UTF-8?B?".base64_encode($title)."?=",
                $message,
                "Content-type: text/plain; charset=UTF-8\r\n".
                "From: ".$from." <noreply@{$_SERVER['SERVER_NAME']}>\r\n".
                "Reply-To: ".$from." <noreply@{$_SERVER['SERVER_NAME']}>"
            );
        }
    }
}
class Updater
{
    public static function init()
    {
        global $updater;

        $posteditname = filter_input(INPUT_POST, 'user_new_name', FILTER_SANITIZE_STRING);
        $postoldname = filter_input(INPUT_POST, 'user_old_name', FILTER_SANITIZE_STRING);
        $posteditpass = filter_input(INPUT_POST, 'user_new_pass', FILTER_SANITIZE_STRING);
        $posteditpasscheck = filter_input(INPUT_POST, 'user_new_pass_confirm', FILTER_SANITIZE_STRING);
        $postoldpass = filter_input(INPUT_POST, 'user_old_pass', FILTER_SANITIZE_STRING);
        $posteditmail = filter_input(INPUT_POST, 'user_new_email', FILTER_VALIDATE_EMAIL);
        $postoldmail = filter_input(INPUT_POST, 'user_old_email', FILTER_VALIDATE_EMAIL);
        if ($postoldpass && $posteditname) {
            $updater->updateUser(
                $posteditname,
                $postoldname,
                $posteditpass,
                $posteditpasscheck,
                $postoldpass,
                $posteditmail,
                $postoldmail
            );
        }
    }
    public function updateUser(
        $posteditname,
        $postoldname,
        $posteditpass,
        $posteditpasscheck,
        $postoldpass,
        $posteditmail,
        $postoldmail
    ) {
        global $encodeExplorer;
        global $updater;
        global $_USERS;
        global $users;
        $users = $_USERS;
        $passa = true;
        if (GateKeeper::isUser($postoldname, $postoldpass)) {

            if ($posteditname != $postoldname) {
                if ($updater->findUser($posteditname)) {
                        Utils::setError(
                            '<strong>'.$posteditname.'</strong> '
                            .$encodeExplorer->getString('file_exists')
                        );
                        $passa = false;
                        return;
                }
                Cookies::removeCookie($postoldname);
                Updater::updateAvatar($postoldname, $posteditname);
                $updater->updateUserData($postoldname, 'name', $posteditname);
            }
            if ($posteditmail != $postoldmail) {
                if ($updater->findEmail($posteditmail)) {
                        Utils::setError(
                            '<strong>'.$posteditmail.'</strong> '
                            .$encodeExplorer->getString('file_exists')
                        );
                        $passa = false;
                        return;
                }
                $updater->updateUserData($postoldname, 'email', $posteditmail);
            }
            if ($posteditpass) {
                if ($posteditpass === $posteditpasscheck) {
                    $updater->updateUserPwd($postoldname, $posteditpass);
                } else {
                    $encodeExplorer->setErrorString('wrong_pass');
                    $passa = false;
                    return;
                }
            }
            if ($passa == true) {
                $updater->updateUserFile('', $posteditname);
            }
        } else {
            $encodeExplorer->setErrorString('wrong_pass');
        }

    }
    public function updateUserPwd($checkname, $changepass)
    {
        global $_USERS;
        global $users;
        $utenti = $_USERS;

        foreach ($utenti as $key => $value) {
            if ($value['name'] === $checkname) {
                $salt = SetUp::getConfig('salt');
                $users[$key]['pass'] = crypt($salt.urlencode($changepass), Utils::randomString());
                break;
            }
        }
    }
    public function updateUserData($checkname, $type, $changeval)
    {
        global $updater;
        global $_USERS;
        global $users;
        $utenti = $_USERS;

        foreach ($utenti as $key => $value) {
            if ($value['name'] === $checkname) {
                if ($changeval) {
                    $users[$key][$type] = $changeval;
                } else {
                    unset($users[$key][$type]);
                }
                break;
            }
        }
    }
    public static function updateAvatar($checkname = false, $newname = false, $dir = 'admin/')
    {
        $avatars = glob($dir.'images/avatars/*.png');
        $filename = md5($checkname);

        foreach ($avatars as $avatar) {

            $fileinfo = Utils::mbPathinfo($avatar);
            $avaname = $fileinfo['filename'];

            if ($avaname === $filename) {
                
                if ($newname) {
                    $newname = md5($newname);
                    rename($dir.'images/avatars/'.$avaname.'.png', $dir.'images/avatars/'.$newname.'.png');
                } else {
                    unlink($dir.'images/avatars/'.$avaname.'.png');
                }
                break;
            }
        }
    }
    public function deleteUser($checkname)
    {
        global $_USERS;
        global $users;
        $utenti = $_USERS;

        foreach ($utenti as $key => $value) {
            if ($value['name'] === $checkname) {
                unset($users[$key]);
                Cookies::removeCookie($checkname, '');
                Updater::updateAvatar($checkname, false, '');
                break;
            }
        }
    }
    public function findEmail($userdata)
    {
        global $_USERS;
        $utenti = $_USERS;
        
        if (is_array($utenti)) {
            foreach ($utenti as $value) {
                if (isset($value['email']) && $value['email'] === $userdata) {
                    return true;
                }
            }
        }
        return false;
    }
    public function findUser($userdata)
    {
        global $_USERS;
        $utenti = $_USERS;

        if (is_array($utenti)) {
            foreach ($utenti as $value) {
                if ($value['name'] === $userdata) {
                    return true;
                }
            }
        }
        return false;
    }
    public function findUserPre($userdata)
    {
        global $newusers;
        $utenti = $newusers;

        if (is_array($utenti)) {
            foreach ($utenti as $value) {
                if ($value['name'] === $userdata) {
                    return true;
                }
            }
        }
        return false;
    }
    public function findUserEmailPre($usermail)
    {
        global $newusers;
        $utenti = $newusers;
        
        if (is_array($utenti)) {
            foreach ($utenti as $value) {
                if (isset($value['email']) && isset($value['name'])) {
                    if ($value['email'] === $usermail) {
                        return $value['name'];
                    }
                }
            }
        }
        return false;
    }
    public function findUserKey($userdata)
    {
        global $newusers;
        $utenti = array();
        $utenti = $newusers;
        $defaultfolders = SetUp::getConfig('registration_user_folders');

        foreach ($utenti as $utente) {
            if ($utente['key'] === $userdata) {
                $thisuser = array();
                foreach ($utente as $attrkey => $userattr) {
                    $thisuser[$attrkey] = $userattr;
                }
                $thisuser['role'] = SetUp::getConfig('registration_role');

                if ($defaultfolders) {
                    $arrayfolders = json_decode($defaultfolders, false);

                    if (in_array('fm_reg_new_folder', $arrayfolders)) {
                        
                        $userfolderpath = $value['name'];

                        $newpath = SetUp::getConfig('starting_dir').$userfolderpath;

                        if (!is_dir($newpath)) {
                            mkdir($newpath);
                        }

                        $arrayfolders = array_diff($arrayfolders, array('fm_reg_new_folder'));
                        $arrayfolders[] = $userfolderpath;
                        $userdir = json_encode(array_values($arrayfolders));
                    } else {
                        $userdir = $defaultfolders;
                    }

                    $thisuser['dir'] = $userdir;
                    if (strlen(SetUp::getConfig('registration_user_quota')) > 0) {
                        $thisuser['quota'] = SetUp::getConfig('registration_user_quota');
                    }
                }
                unset($thisuser['key']);
                return $thisuser;
            }
        }
        return false;
    }
    public function updateUserFile($option = '', $postname = false)
    {
        global $encodeExplorer;
        global $users;
        $usrs = '$_USERS = ';

        if (false == (file_put_contents(
            'admin/users/users.php',
            "<?php\n\n $usrs".var_export($users, true).";\n"
        ))
        ) {
            Utils::setError('error updating users list');
        } else {
            if ($option == 'password') {
                Utils::setSuccess($encodeExplorer->getString('password_reset'));
            } else {
                if ($postname) {
                    $edited = '<strong>'.$postname.'</strong> ';
                    Utils::setSuccess($edited.$encodeExplorer->getString('updated'));
                }
            }
            $_SESSION['fm_user_name'] = null;
            $_SESSION['fm_logged_in'] = null;
            $_SESSION['fm_user_space'] = null;
            $_SESSION['fm_user_used'] = null;
            // session_destroy();
        }
    }
    public function updateRegistrationFile($newusers, $path = '')
    {
        global $encodeExplorer;
        $usrs = '$newusers = ';

        if (false == (file_put_contents(
            $path.'users-new.php',
            "<?php\n\n $usrs".var_export($newusers, true).";\n"
        ))
        ) {
            return false;
        } else {
            return true;
        }
    }
    public function confirmRegistration($newusers)
    {
        global $encodeExplorer;
        $usrs = '$newusers = ';

        if (false == (file_put_contents(
            '../users-new.php',
            "<?php\n\n $usrs".var_export($newusers, true).";\n"
        ))
        ) {
            return false;
        } else {
            return true;
        }
    }
    public function removeUserFromValue($array, $key, $value)
    {
        foreach ($array as $subKey => $subArray) {
            if ($subArray[$key] == $value) {
                unset($array[$subKey]);
            }
        }
        return $array;
    }
    public function removeOldReg($array, $key, $lifetime)
    {
        foreach ($array as $subKey => $subArray) {
            $data = strtotime($subArray[$key]);

            if ($data <= $lifetime) {
                unset($array[$subKey]);
            }
        }
        return $array;
    }
    public function updateHtaccess($starting_dir, $direct_links = false)
    {
        $htaccess = '.'.$starting_dir.".htaccess";

        $start_marker = "# begin FM rules";
        $end_marker   = "# end FM rules";
        $pre_lines = $post_lines = $existing_lines = array();
        $found_marker = $found_end_marker = false;
        if (file_exists($htaccess)) {
            $hta = file_get_contents($htaccess);
            $lines = explode(PHP_EOL, $hta);
            foreach ( $lines as $line ) {
                if (!$found_marker && false !== strpos($line, $start_marker)) {
                    $found_marker = true;
                    continue;
                } elseif (!$found_end_marker && false !== strpos($line, $end_marker) ) {
                    $found_end_marker = true;
                    continue;
                }
                if (!$found_marker) {
                    $pre_lines[] = $line;
                } elseif ($found_marker && $found_end_marker) {
                    $post_lines[] = $line;
                } else {
                    $existing_lines[] = $line;
                }
            }
        }
        $insertion = array();
        $insertion[] = "php_flag engine off";
        if (!$direct_links && strlen($starting_dir) > 2) {
            $insertion[] = "Order Deny,Allow";
            $insertion[] = "Deny from all";
        }
        if ($existing_lines === $insertion) {
            return true;
        }
        $new_file_data = implode(
            "\n", array_merge(
                $pre_lines,
                array( $start_marker ),
                $insertion,
                array( $end_marker ),
                $post_lines
            )
        );
        $fpp = fopen($htaccess, "w+");
        if ($fpp === false) {
            return false;
        }
        fwrite($fpp, $new_file_data);
        fclose($fpp);
        return true;
    }

}

class Cookies
{

    public static function removeCookie($postusername = false, $path = 'admin/')
    {
        global $_REMEMBER;

        if (array_key_exists($postusername, $_REMEMBER)) {
            unset($_REMEMBER[$postusername]);
        
            $rmb = '$_REMEMBER = ';
            if (false == (file_put_contents(
                $path.'users/remember.php',
                "<?php\n\n $rmb".var_export($_REMEMBER, true).";\n"
            ))
            ) {
                Utils::setError('error removing remember key');
                return false;
            }
        }
    }

    public function setCookie($postusername = false)
    {
        global $_REMEMBER;

        $rewrite = false;
        $salt = SetUp::getConfig('salt');
        $rmsha = md5($salt.sha1($postusername.$salt));
        $rmshaved = md5($rmsha);

        setcookie('rm', $rmsha, time()+ (60*60*24*365));
        setcookie('fm_user_name', $postusername, time()+ (60*60*24*365));

        if (array_key_exists($postusername, $_REMEMBER)
            && $_REMEMBER[$postusername] !== $rmshaved
        ) {
            $rewrite = true;
        }

        if (!array_key_exists($postusername, $_REMEMBER)
            || $rewrite == true
        ) {
            $_REMEMBER[$postusername] = $rmshaved;
            $rmb = '$_REMEMBER = ';
            if (false == (file_put_contents(
                'admin/users/remember.php',
                "<?php\n\n $rmb".var_export($_REMEMBER, true).";\n"
            ))
            ) {
                Utils::setError('error setting your remember key');
                return false;
            }
        }
    }

    public function checkKey($name, $key)
    {
        global $_REMEMBER;
        global $gateKeeper;
        
        if (array_key_exists($name, $_REMEMBER)) {
            if ($_REMEMBER[$name] === md5($key)) {
                $_SESSION['fm_user_name'] = $name;
                $_SESSION['fm_logged_in'] = 1;

                $usedspace = $gateKeeper->getUserSpace();

                if ($usedspace !== false) {
                    $userspace = $gateKeeper->getUserInfo('quota')*1024*1024;
                    $_SESSION['fm_user_used'] = $usedspace;
                    $_SESSION['fm_user_space'] = $userspace;
                } else {
                    $_SESSION['fm_user_used'] = null;
                    $_SESSION['fm_user_space'] = null;
                }
            }
        }
        return false;
    }

    public function checkCookie()
    {
        global $cookies;

        if (isset($_COOKIE['rm']) && isset($_COOKIE['fm_user_name'])) {
            $name = $_COOKIE['fm_user_name'];
            $key = $_COOKIE['rm'];
            return $cookies->checkKey($name, $key);
        }
        return false;
    }
}

class GateKeeper
{

    public static function init()
    {
        global $encodeExplorer;
        global $gateKeeper;
        global $cookies;

        if (isset($_GET['logout'])) {
            setcookie('rm', '', time() -(60*60*24*365));
            $_SESSION['fm_user_name'] = null;
            $_SESSION['fm_logged_in'] = null;            
            $_SESSION['fm_user_space'] = null;
            $_SESSION['fm_user_used'] = null;
            // session_destroy();
        } else {
            $cookies->checkCookie();
        }

        $postusername = filter_input(INPUT_POST, 'user_name', FILTER_SANITIZE_STRING);
        $postuserpass = filter_input(INPUT_POST, 'user_pass', FILTER_SANITIZE_STRING);
        $postcaptcha = filter_input(INPUT_POST, 'captcha', FILTER_SANITIZE_STRING);
        $rememberme = filter_input(INPUT_POST, 'fm_remember', FILTER_SANITIZE_STRING);

        if ($postusername && $postuserpass) {

            if (Utils::checkCaptcha($postcaptcha) == true) {

                if (GateKeeper::isUser($postusername, $postuserpass)) {
                    if ($rememberme == 'yes') {
                        $cookies->setCookie($postusername);
                    }
                    $_SESSION['fm_user_name'] = $postusername;
                    $_SESSION['fm_logged_in'] = 1;

                    $usedspace = $gateKeeper->getUserSpace();

                    if ($usedspace !== false) {
                        $userspace = $gateKeeper->getUserInfo('quota')*1024*1024;
                        $_SESSION['fm_user_used'] = $usedspace;
                        $_SESSION['fm_user_space'] = $userspace;
                    } else {
                        $_SESSION['fm_user_used'] = null;
                        $_SESSION['fm_user_space'] = null;
                    }
                    if (SetUp::getConfig('notify_login')) {
                        Logger::emailNotification('--', 'login');
                    }
                    header('location:?dir=');
                    exit;
                } else {
                    $encodeExplorer->setErrorString('wrong_pass');
                }
            } else {
                $encodeExplorer->setErrorString('wrong_captcha');
            }
        }
    }
    public function getUserSpace()
    {
        global $gateKeeper;

        if ($gateKeeper->getUserInfo('dir') !== null
            && $gateKeeper->getUserInfo('quota') !== null
        ) {
            $totalsize = 0;
            $userfolders = json_decode($gateKeeper->getUserInfo('dir'), true);
            $userfolders = $userfolders ? $userfolders : array();

            foreach ($userfolders as $myfolder) {
                $checkfolder = urldecode(SetUp::getConfig('starting_dir').$myfolder);
                if (file_exists($checkfolder)) {
                    $ritorno = FileManager::getDirSize($checkfolder);
                    $totalsize += $ritorno['size'];
                }
            }
            return $totalsize;
        }
        return false;
    }
    public static function isUser($userName, $userPass)
    {
        $salt = SetUp::getConfig('salt');
        foreach (SetUp::getUsers() as $user) {
            if ($user['name'] === $userName) {
                $passo = $salt.urlencode($userPass);
                if (crypt($passo, $user['pass']) == $user['pass']) {
                    return true;
                }
                break;
            }
        }
        return false;
    }
    public static function isLoginRequired()
    {
        if (SetUp::getConfig('require_login') == false) {
            return false;
        }
        return true;
    }
    public static function isUserLoggedIn()
    {
        if (isset($_SESSION['fm_user_name'])
            && isset($_SESSION['fm_logged_in'])
            && $_SESSION['fm_logged_in'] == 1
        ) {
            return true;
        }
        return false;
    }
    public static function isAllowed($action)
    {
        if (GateKeeper::isAccessAllowed()) {
            if ((SetUp::getConfig($action) == true && GateKeeper::getUserInfo('role') == 'admin')
                || GateKeeper::getUserInfo('role') == 'superadmin'
            ) {
                return true;
            }
        }
        return false;
    }
    public static function isAccessAllowed()
    {
        if (!GateKeeper::isLoginRequired() || GateKeeper::isUserLoggedIn()) {
            return true;
        }
        return false;
    }
    public static function getUserInfo($info)
    {
        if (GateKeeper::isUserLoggedIn() == true
            && isset($_SESSION['fm_user_name'])
            && strlen($_SESSION['fm_user_name']) > 0
        ) {
            $username = $_SESSION['fm_user_name'];
            $curruser = Utils::getCurrentUser($username);

            if (isset($curruser[$info]) && strlen($curruser[$info]) > 0) {
                return $curruser[$info];
            }
            return null;
        }
    }
    public static function getAvatar($username, $adminarea = 'admin/')
    {
        $avaimg = md5($username).'.png';
        
        if (!file_exists($adminarea.'images/avatars/'.$avaimg)) {
            $avaimg = 'default.png';
        }
        return SetUp::getConfig('script_url').'admin/images/avatars/'.$avaimg;
    }
    public static function isSuperAdmin()
    {
        if (GateKeeper::getUserInfo('role') == 'superadmin') {
            return true;
        }
        return false;
    }
    public static function showLoginBox()
    {
        if (!GateKeeper::isUserLoggedIn()
            && count(SetUp::getUsers()) > 0
        ) {
            return true;
        }
        return false;
    }
}
class FileManager
{
    public function run($location)
    {
        $postuserdir = filter_input(INPUT_POST, 'userdir', FILTER_SANITIZE_STRING);
        $postnewname = filter_input(INPUT_POST, 'newname', FILTER_SANITIZE_STRING);

        if ($postuserdir) {
            // add new folder
            $dirname = Utils::normalizeStr($postuserdir);
            Actions::newFolder($location, $dirname);

        } elseif (isset($_FILES['userfile']['name'])) {
            // upload files
            $this->uploadMulti($_FILES['userfile']);
            die();
        } elseif ($postnewname) {
            // rename files or folders
            $postoldname = filter_input(INPUT_POST, 'oldname', FILTER_SANITIZE_STRING);

            $postnewname = Utils::normalizeStr($postnewname);
            $this->setRename($postoldname, $postnewname);

        } else {
            // no post action
            $getdel = filter_input(INPUT_GET, 'del', FILTER_SANITIZE_STRING);
            // delete files or folders
            if ($getdel
                && GateKeeper::isUserLoggedIn()
                && GateKeeper::isAllowed('delete_enable')
            ) {
                $getdel = str_replace(' ', '+', $getdel);
                $getdel = urldecode(base64_decode($getdel));
                
                $getdel = EncodeExplorer::extraChars($getdel);

                $this->setDel($getdel);
            }
        }
    }
    public static function getDirSize($path)
    {
        $bytestotal = 0;
        $path = realpath($path);
        if ($path !== false) {
            foreach (
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
                ) as $object
            ) {
                $bytestotal += $object->getSize();
            }
        }
        $total['size'] = $bytestotal;
        return $total;
    }
    public function setDel($getdel)
    {
        global $gateKeeper;

        if (Utils::checkDel($getdel) == false) {
            Utils::setError('<i class="fa fa-ban"></i> Permission denied');
            return;
        }
        if (is_dir($getdel)) {
            if ($gateKeeper->getUserSpace() !== false) {
                $ritorno = FileManager::getDirSize("./".$getdel);
                $totalsize = $ritorno['size'];

                if ($totalsize > 0) {
                    Actions::updateUserSpaceDeep($totalsize);
                }
            }
            Actions::deleteDir($getdel);

            Utils::setWarning('<i class="fa fa-trash-o"></i> '.substr($getdel, strrpos($getdel, '/') + 1));
            // Directory successfully deleted, sending log notification
            Logger::logDeletion('./'.$getdel, true);

        } elseif (is_file($getdel)) {
            Actions::deleteFile($getdel);
        }
    }
    public function setRename($postoldname, $postnewname)
    {
        if (GateKeeper::isAccessAllowed()
            && GateKeeper::isAllowed('rename_enable')
        ) {
            $postthisext = filter_input(INPUT_POST, "thisext", FILTER_SANITIZE_STRING);
            $postthisdir = filter_input(INPUT_POST, "thisdir", FILTER_SANITIZE_STRING);

            if ($postoldname && $postnewname) {
                if ($postthisext) {
                    $oldname = $postthisdir.$postoldname.".".$postthisext;
                    $newname = $postthisdir.Utils::normalizeStr($postnewname).".".$postthisext;
                } else {
                    $oldname = $postthisdir.$postoldname;
                    $newname = $postthisdir.Utils::normalizeStr($postnewname);
                }
                Actions::renameFile($oldname, $newname, $postnewname);
            }
        }
    }
    public function uploadMulti($coda)
    {
        global $location;
        if ($location->editAllowed()
            && GateKeeper::isUserLoggedIn()
            && GateKeeper::isAccessAllowed()
            && GateKeeper::isAllowed('upload_enable')
        ) {
            // Number of files to uploaded
            $num_files = count($coda['tmp_name']);
            $totnames = array();
            for ($i=0; $i < $num_files; $i++) {
                
                $filepathinfo = Utils::mbPathinfo($coda['name'][$i]);

                $filename = $filepathinfo['filename'];
                $filex = $filepathinfo['extension'];
                $thename = $filepathinfo['basename'];
                $tempname = $coda['tmp_name'][$i];
                $tipo = $coda['type'][$i];
                $filerror = $coda['error'][$i];

                if (in_array($thename, $totnames)) {
                    $thename = $filename.$i.".".$filex;
                }

                if (Utils::notList(
                    $thename,
                    array('.htaccess','.htpasswd','.ftpquota')
                ) == true) {

                    array_push($totnames, $thename);

                    if ($thename) {
                        Actions::uploadFile($location, $thename, $tempname, $tipo);
                        // check uplad errors
                        FileManager::upLog($filerror);
                    }
                }
            }
        }
    }
    public static function upLog($filerr)
    {
        $error_types = array(
        0=>'OK',
        1=>'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
        2=>'The uploaded file exceeds the MAX_FILE_SIZE specified in the HTML form.',
        3=>'The uploaded file was only partially uploaded.',
        4=>'No file was uploaded.',
        6=>'Missing a temporary folder.',
        7=>'Failed to write file to disk.',
        8=>'A PHP extension stopped the file upload.',
        'post_max_size' => 'The uploaded file exceeds the post_max_size directive in php.ini',
        'max_file_size' => 'File is too big',
        'min_file_size' => 'File is too small',
        'accept_file_types' => 'Filetype not allowed',
        'max_number_of_files' => 'Maximum number of files exceeded',
        'max_width' => 'Image exceeds maximum width',
        'min_width' => 'Image requires a minimum width',
        'max_height' => 'Image exceeds maximum height',
        'min_height' => 'Image requires a minimum height',
        'abort' => 'File upload aborted',
        'image_resize' => 'Failed to resize image'
        );

        $error_message = $error_types[$filerr];
        if ($filerr > 0) {
            Utils::setError(' :: '.$error_message);
        }
    }
    public static function safeExtension($name, $extension)
    {
        $evil = array(
            'php','php3','php4','php5','htm','html','phtm','phtml',
            'shtm','shtml','asp','pl','py','jsp','sh','cgi','htaccess',
            'htpasswd','386','bat','cmd','pl','ddl','bin'
            );
        if (in_array($extension, $evil)) {
            $name = $name.'.txt';
        }
        return $name;
    }
}
class Actions
{
    public static function renameFile($oldname, $newname, $thenewname, $move = false, $copy = false)
    {

        global $encodeExplorer;

        $oldname = $encodeExplorer->extraChars($oldname);
        $newname = $encodeExplorer->extraChars($newname);

        if (!file_exists($oldname)) {
            Utils::setError('<i class="fa fa-exclamation-circle"></i> <strong>' .$oldname. '</strong> does not exist');
            return false;
        }

        if (Actions::fileExists($newname)) {
            Utils::setWarning(
                '<i class="fa fa-info-circle"></i> <strong>' .$thenewname. '</strong> '
                .$encodeExplorer->getString('file_exists')
            );
            return false;
        }
        if ($copy) {
            if (!copy($oldname, $newname)) {
                Utils::setError('<i class="fa fa-exclamation-circle"></i> <strong>' .$thenewname. '</strong> can\'t be copied');
                return false;
            } else {
                Actions::updateUserSpace($oldname, true);
                Utils::setSuccess(
                    '<strong>'.$thenewname. '</strong> '
                    .$encodeExplorer->getString('copied')
                );
                return true;
            }
        } else {
            if (!rename($oldname, $newname)) {
                Utils::setError('<i class="fa fa-exclamation-circle"></i> <strong>' .$thenewname. '</strong> can\'t be edited');
                return false;
            } else {
                if ($move === true) {
                    Actions::deleteThumb(substr($oldname, 3), true);
                } else {
                    Actions::deleteThumb($oldname);  
                }
                Utils::setSuccess(
                    '<strong>'.$thenewname. '</strong> '
                    .$encodeExplorer->getString('updated')
                );
                return true;
            }
        }
    }
    public static function walkDir($dir, $currentdir)
    {
        $relativedir = $dir;

        if (is_dir($relativedir)) {
            if ($dh = opendir($relativedir)) {
                echo "<ul>";
                while (false !== ($file = readdir($dh))) {
                    if (($file !== '.') && ($file !== '..')) {
                        if (is_dir($relativedir.$file)) {
                            echo '<li>';
                            if ($relativedir.$file."/" === $currentdir) {
                                echo '<i class="fa fa-folder-open-o"></i> '.$file;
                            } else {
                                echo '<a href="#" data-dest="'.urlencode($dir.$file).'" class="movelink">';
                                echo '<i class="fa fa-folder-o"></i> '.$file.'</a>';
                            }
                            Actions::walkDir($dir.$file.'/', $currentdir);
                            echo '</li>';
                        }
                    }
                }
                echo '</ul>';
            }
        }
    }
    public static function fileExists($path, $caseSensitive = false)
    {

        $pathinfo = Utils::mbPathinfo($path);

        $filename = $pathinfo['filename'];
        $dirname = $pathinfo['dirname'];

        if (file_exists($path)) {
            return true;
        }
        if ($caseSensitive) {
            return false;
        }
        // Handle case insensitive requests
        $fileArray = glob($dirname . '/*', GLOB_NOSORT);
        $fileNameLowerCase = strtolower($path);

        foreach ($fileArray as $file) {
            if (strtolower($file) == $fileNameLowerCase) {
                return true;
            }
        }
        return false;
    }
    public static function newFolder($location, $dirname)
    {
        global $encodeExplorer;

        if (GateKeeper::isAllowed('newdir_enable')) {
            if (strlen($dirname) > 0) {

                if (!$location->editAllowed()) {
                    // The system configuration does not allow uploading here
                    $encodeExplorer->setErrorString('upload_not_allowed');
                    return false;
                }
                if (!$location->isWritable()) {
                    // The target directory is not writable
                    $encodeExplorer->setErrorString('upload_dir_not_writable');
                    return false;
                }
                if (Actions::fileExists($location->getDir(true, false, false, 0).$dirname)) {
                    Utils::setWarning(
                        '<i class="fa fa-folder"></i>  <strong>'.$dirname.'</strong> '
                        .$encodeExplorer->getString('file_exists')
                    );
                    return false;
                }
                if (!mkdir($location->getDir(true, false, false, 0).$dirname, 0755)) {
                    // Error creating a new directory
                    $encodeExplorer->setErrorString('new_dir_failed');
                    return false;
                }
                Utils::setSuccess(
                    '<i class="fa fa-folder"></i> <strong>'.$dirname.'</strong> '
                    .$encodeExplorer->getString('created')
                );
                // Directory successfully created, sending e-mail notification
                Logger::logCreation($location->getDir(true, false, false, 0).$dirname, true);
                return true;
            }
            $encodeExplorer->setErrorString('new_dir_failed');
            return false;
        }
    }
    public static function uploadFile($location, $thename, $tempname, $tipo)
    {
        global $encodeExplorer;

        $extension = File::getFileExtension($thename);

        $filepathinfo = Utils::mbPathinfo($thename);
        $name = Utils::normalizeStr($filepathinfo['filename']).'.'.$extension;

        $upload_dir = $location->getFullPath();

        $upload_file = $upload_dir.$name;

        if (Actions::fileExists($upload_file)) {
            Utils::setWarning(
                '<span><i class="fa fa-info-circle"></i> '.$name.' '
                .$encodeExplorer->getString('file_exists').'</span> '
            );
        } else {

            $mime_type = $tipo;
            $clean_file = $upload_dir.FileManager::safeExtension($name, $extension);

            if (!$location->editAllowed() || !$location->isWritable()) {
                Utils::setError(
                    '<span><i class="fa fa-exclamation-triangle"></i> '
                    .$encodeExplorer->getString('upload_not_allowed').'</span> '
                );

            } elseif (Utils::notList($extension, SetUp::getConfig('upload_allow_type')) == true
                || Utils::inList($extension, SetUp::getConfig('upload_reject_extension')) == true
            ) {
                Utils::setError(
                    '<span><i class="fa fa-exclamation-triangle"></i> '
                    .$name.'<strong>.'.$extension.'</strong> '
                    .$encodeExplorer->getString('upload_type_not_allowed').'</span> '
                );

            } elseif (!is_uploaded_file($tempname)) {
                $encodeExplorer->setErrorString('failed_upload');

            } elseif (!move_uploaded_file($tempname, $clean_file)) {
                $encodeExplorer->setErrorString('failed_move');

            } elseif (Actions::checkUserSpace($clean_file) == false) {
                $encodeExplorer->setErrorString('upload_exceeded');
                unlink($clean_file);

            } else {
                chmod($clean_file, 0755);
                Utils::setSuccess('<span><i class="fa fa-check-circle"></i> '.$name.'</span> ');
                // file successfully uploaded, sending log notification
                Logger::logCreation($location->getDir(true, false, false, 0).$name, false);
            }
        }
    }
    public static function deleteDir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (filetype($dir.'/'.$object) == 'dir') {
                        Actions::deleteDir($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }
    public static function deleteFile($file)
    {
        if (is_file($file)) {

            Actions::updateUserSpace($file, false);
            unlink($file);
            Actions::deleteThumb($file);
            Utils::setWarning('<i class="fa fa-trash-o"></i> '.basename($file));
            Logger::logDeletion('./'.$file, false);
        }
    }
    public static function deleteMulti($file)
    {
        if (is_file($file)) {

            Actions::updateUserSpace($file, false);
            unlink($file);
            Actions::deleteThumb(substr($file, 3), true);
            Utils::setWarning('<i class="fa fa-trash-o"></i> '.basename($file).' | ');
            Logger::logDeletion(substr($file, 1), false, true);
        }
    }
    public static function deleteThumb($file, $multi = false)
    {
        if ($multi == false) {
            $thumbdir = 'admin/thumbs/';
        } else {
            $thumbdir = 'thumbs/';
        }
        $thumbname = md5($file);

        $thumb = $thumbdir.$thumbname.'.jpg';
        $thumb_big = $thumbdir.$thumbname.'-big.jpg';

        if (is_file($thumb)) {
            unlink($thumb);
        }
        if (is_file($thumb_big)) {
            unlink($thumb_big);
        }
    }
    public static function checkUserSpace($file, $thissize = false)
    {
        if (isset($_SESSION['fm_user_used'])
            && isset($_SESSION['fm_user_space'])
        ) {
            if (!$thissize) {
                $thissize = File::getFileSize($file);
            }
            $oldused = $_SESSION['fm_user_used'];
            $newused = $oldused + $thissize;
            $freespace = $_SESSION['fm_user_space'];
            
            if ($newused > $freespace) {
                return false;
            } else {
                return true;
            }
        }
        return true;
    }
    public static function updateUserSpace($file, $add = false)
    {
        if (isset($_SESSION['fm_user_used'])) {

            $thissize = File::getFileSize($file);
            $usedspace = $_SESSION['fm_user_used'];

            if ($add == true) {
                $usedspace = $usedspace + $thissize;
            } else {
                $usedspace = $usedspace - $thissize;
            }
            $_SESSION['fm_user_used'] = $usedspace;
        }
    }
    public static function updateUserSpaceDeep($size)
    {
        if (isset($_SESSION['fm_user_used'])) {

            $thissize = $size;
            $usedspace = $_SESSION['fm_user_used'];
            $usedspace = $usedspace - $thissize;

            $_SESSION['fm_user_used'] = $usedspace;
        }
    }
}
class Dir
{
    public $name;
    public $location;
    public function __construct($name, $location)
    {
        $this->name = $name;
        $this->location = $location;
    }
    public function getLocation()
    {
        return $this->location->getDir(true, false, false, 0);
    }
    public function getName()
    {
        return Utils::normalizeName($this->name);
    }
    public function getNameHtml()
    {
        return htmlspecialchars(Utils::normalizeName($this->name));
    }
    public function getNameEncoded()
    {
        return rawurlencode(Utils::normalizeName($this->name));
    }
}
class File
{
    public $name;
    public $location;
    public $size;
    public $type;
    public $modTime;
    public function __construct($name, $location)
    {
        $this->name = $name;
        $this->location = $location;

        $this->type = File::getFileType(
            $this->location->getDir(true, false, false, 0).$this->getName()
        );
        $this->size = File::getFileSize(
            $this->location->getDir(true, false, false, 0).$this->getName()
        );
        $this->modTime = filemtime(
            $this->location->getDir(true, false, false, 0).$this->getName()
        );
    }
    public function getName()
    {
        return  Utils::normalizeName($this->name);
    }
    public function getNameEncoded()
    {
        return rawurlencode(Utils::normalizeName($this->name));
    }
    public function getNameHtml()
    {
        return htmlspecialchars(Utils::normalizeName($this->name));
    }
    public function getSize()
    {
        return $this->size;
    }
    public function getType()
    {
        return $this->type;
    }
    public function getModTime()
    {
        return $this->modTime;
    }
    public static function getFileSize($file)
    {
        $sizeInBytes = filesize($file);
        if (!$sizeInBytes || $sizeInBytes < 0) {
            $fho = fopen($file, 'r');
            $size = '0';
            $char = '';
            fseek($fho, 0, SEEK_SET);
            $count = 0;
            while (true) {
                //jump 1 MB forward in file
                fseek($fho, 1048576, SEEK_CUR);
                //check if we actually left the file
                if (($char = fgetc($fho)) !== false) {
                    $count ++;
                } else {
                    //else jump back where we were before leaving and exit loop
                    fseek($fho, -1048576, SEEK_CUR);
                    break;
                }
            }
            $size = bcmul('1048577', $count);
            $fine = 0;
            while (false !== ($char = fgetc($fho))) {
                $fine ++;
            }
            //and add them
            $sizeInBytes = bcadd($size, $fine);
            fclose($fho);
        }
        return $sizeInBytes;
    }
    public static function getFileType($filepath)
    {
        return File::getFileExtension($filepath);
    }
    public static function getFileExtension($filepath)
    {
        return pathinfo($filepath, PATHINFO_EXTENSION);
    }
    public function isImage()
    {
        $types = array(
            'jpg',
            'jpeg',
            'gif',
            'png',
        );
        $type = strtolower($this->getType());
        
        if (in_array($type, $types)) {
            return true;
        }
        return false;
    }
    public function isAudio()
    {
        $types = array(
            'mp3',
            'wav',
        );
        $type = strtolower($this->getType());

        if (in_array($type, $types)) {
            return true;
        }
        return false;
    }
    public function isVideo()
    {
        $types = array(
            'mp4',
            'webm',
            'flv',
            // 'ogv',
        );
        $type = strtolower($this->getType());

        if (in_array($type, $types)) {
            return true;
        }
        return false;
    }
    public function isPdf()
    {
        if (strtolower($this->getType()) == 'pdf') {
            return true;
        }
        return false;
    }
    public static function isPdfFile($file)
    {
        if (strtolower(File::getFileType($file)) == 'pdf') {
            return true;
        }
        return false;
    }
    public function isValidForThumb()
    {
        if (SetUp::getConfig('thumbnails') !== true && SetUp::getConfig('inline_thumbs') !== true) {
            return false;
        }
        if ($this->isImage() || ($this->isPdf() && ImageServer::isEnabledPdf())
        ) {
            return true;
        }
        return false;
    }
    public function isValidForAudio()
    {
        if (SetUp::getConfig('playmusic') == true
            && $this->isAudio()
        ) {
            return true;
        }
        return false;
    }
    public function isValidForVideo()
    {
        if (SetUp::getConfig('playvideo') == true
            && $this->isVideo()
        ) {
            return true;
        }
        return false;
    }

}
class Location
{
    public $path;
    public function init()
    {
        $getdir = filter_input(INPUT_GET, 'dir', FILTER_SANITIZE_STRING);

        if (!$getdir || !is_dir($getdir)) {
            $this->path = $this->splitPath(SetUp::getConfig('starting_dir'));
        } else {
            $this->path = $this->splitPath($getdir);
        }
    }
    public static function splitPath($dir)
    {
        $dir = stripslashes($dir);
        $path1 = preg_split("/[\\\\\/]+/", $dir);
        $path2 = array();

        if (is_dir($dir)) {
            for ($i = 0; $i < count($path1); $i++) {
                if ($path1[$i] == '..' || $path1[$i] == '.' || $path1[$i] == '') {
                    continue;
                }            
                $path2[] = $path1[$i];
            }
        }

        if (count($path2) < 1 && strlen(SetUp::getConfig('starting_dir')) > 2) {
            $path2[] = SetUp::getConfig('starting_dir');
        }
        return $path2;
    }
    public function getDir($prefix, $encoded, $html, $upper)
    {
        $dir = '';
        if ($prefix == true) {
            $dir .= './';
        }
        for ($i = 0; $i < ((count($this->path) >= $upper
            && $upper > 0) ? count($this->path)-$upper : count($this->path)); $i++) {

            $temp = $this->path[$i];

            if ($encoded) {
                $temp = rawurlencode($temp);
            }
            if ($html) {
                $temp = htmlspecialchars($temp);
            }
            $dir .= $temp.'/';
        }
        $dir = EncodeExplorer::extraChars($dir);
        return $dir;
    }
    public function getPathLink($level, $html)
    {
        if ($html) {
            return htmlspecialchars($this->path[$level]);
        } else {
            return $this->path[$level];
        }
    }
    public function getFullPath()
    {
        $fullpath = (strlen(
            SetUp::getConfig('basedir')
        ) > 0 ? SetUp::getConfig('basedir'):
        str_replace('\\', '/', dirname($_SERVER['SCRIPT_FILENAME'])))
        ."/".$this->getDir(false, false, false, 0);

        $fullpath = EncodeExplorer::extraChars($fullpath);
        return $fullpath;
    }
    public function isSubDir($checkPath)
    {
        for ($i = 0; $i < count($this->path); $i++) {
            if (strcmp($this->getDir(true, false, false, $i), $checkPath) == 0) {
                return true;
            }
        }
        return false;
    }
    public function editAllowed()
    {
        global $encodeExplorer;
        global $location;

        $totdirs = count($location->path);

        $father = $location->getDir(false, true, false, $totdirs -1);

        if (in_array(basename($father), SetUp::getConfig('hidden_dirs'))) {
            return false;
        }

        if (GateKeeper::getUserInfo('dir') == null
            || $encodeExplorer->checkUserDir($location) == true
        ) {
            return true;
        }
        return false;
    }
    public function isWritable()
    {
        return is_writable($this->getDir(true, false, false, 0));
    }
    public static function isDirWritable($dir)
    {
        return is_writable($dir);
    }
    public static function isFileWritable($file)
    {
        if (file_exists($file)) {
            if (is_writable($file)) {
                return true;
            } else {
                return false;
            }
        } elseif (Location::isDirWritable(dirname($file))) {
            return true;
        } else {
            return false;
        }
    }
}

class EncodeExplorer
{
    public $location;
    public $dirs;
    public $files;
    public $spaceUsed;
    public $lang;
    public function init()
    {
        if (strlen(SetUp::getConfig('session_name')) > 0) {
            session_name(SetUp::getConfig('session_name'));
        }
        if (count(SetUp::getUsers()) > 0) {
            session_start();
        } else {
            return;
        }

        if (isset($_GET['lang'])
            && file_exists('admin/translations/'.$_GET['lang'].'.php')
        ) {
            $this->lang = $_GET['lang'];
            $_SESSION['lang'] = $_GET['lang'];
        }
        if (isset($_SESSION['lang'])) {
            $this->lang = $_SESSION['lang'];
        } else {
            $this->lang = SetUp::getConfig('lang');
        }
    }
    public function printLangMenu($dir = '')
    {
        global $translations_index;

        $directory = 'translations';
        $menu = '<ul class="dropdown-menu">';
        $files = glob($dir.$directory.'/*.php');

        foreach ($files as $item) {
            //$langvar = substr($item, 0, -4);
            $fileinfo = Utils::mbPathinfo($item);
            $langvar = $fileinfo['filename'];
            $menu .= '<li><a href="?lang='.$langvar.'">';
            $out = isset($translations_index[$langvar]) ? $translations_index[$langvar] : $langvar;
            $menu .= '<span>'.$out.'</span></a></li>';
        }
        $menu .= '</ul>';
        return $menu;
    }

    public function getLanguages($dir = '')
    {
        global $translations_index;
        $directory = 'translations';
        $files = glob($dir.$directory.'/*.php');
        $languages = array();

        foreach ($files as $item) {
            // $langvar = substr($item, 0, -4);
            $fileinfo = Utils::mbPathinfo($item);

            $langvar = $fileinfo['filename'];
            $langname = isset($translations_index[$langvar]) ? $translations_index[$langvar] : $langvar;
            $languages[$langvar] = $langname;
            //array_push($languages, $langvar);
        }
        return $languages;
    }
    public function readDir()
    {
        global $encodeExplorer;
        global $downloader;

        $fullpath = $this->location->getFullPath();
        $totdirs = count($this->location->path);
        $father = $this->location->getDir(false, true, false, $totdirs -1);

        if (in_array(basename($father), SetUp::getConfig('hidden_dirs'))) {
            $encodeExplorer->setErrorString('unable_to_read_dir');
            return false;
        }
        $startingdir = SetUp::getConfig('starting_dir');
        $hidefiles = false;

        if (strlen($startingdir) < 3 && $startingdir === $this->location->getDir(true, true, false, 0)) {
            $hidefiles = true;
        }
        if (is_dir($fullpath)) {
            $files = glob($fullpath.'/*');
            $this->dirs = array();
            $this->files = array();
            if (is_array($files)) {
                foreach ($files as $item) {
                    $mbitem = Utils::mbPathinfo($item);
                    $item_basename = $mbitem['basename'];

                    if (is_dir($item)) {
                        if (!$hidefiles || ($hidefiles && !in_array($item_basename, SetUp::getConfig('hidden_dirs')))) {
                            $this->dirs[] = new Dir($item_basename, $this->location);
                        }
                    } else {
                        if (!$hidefiles || ($hidefiles && !in_array($item_basename, SetUp::getConfig('hidden_files')))) {
                            $this->files[] = new File($item_basename, $this->location);
                        }
                    }
                }
            }
        }
    }
    public function readFolders()
    {
        global $encodeExplorer;
        $fullpath = $this->location->getFullPath();

        if (is_dir($fullpath)) {

            if ($open_dir = opendir($fullpath)) {
                $this->dirs = array();
                $this->files = array();
                while ($object = readdir($open_dir)) {
                    if ($object != '.' && $object != '..') {
                        if (is_dir($this->location->getDir(true, false, false, 0).'/'.$object)
                            && !in_array($object, SetUp::getConfig('hidden_dirs'))
                            && in_array($object, json_decode(GateKeeper::getUserInfo('dir'), true))
                        ) {
                            $this->dirs[] = new Dir($object, $this->location);
                        }
                    }
                }
                closedir($open_dir);
            } else {
                $encodeExplorer->setErrorString('unable_to_read_dir');
            }
        }
    }
    public function makeLink($logout, $delete, $dir)
    {
        $link = '?';

        if ($logout == true) {
            $link .= 'logout';
            return $link;
        }
        $link .= 'dir='.$dir;
        if ($delete != null) {
            $link .= '&amp;del='.base64_encode($delete);
        }
        return $link;
    }
    public function getString($stringName)
    {
        return SetUp::getLangString($stringName, $this->lang);
    }
    public function setSuccessString($stringName)
    {
        Utils::setSuccess($this->getString($stringName));
    }
    public function setErrorString($stringName)
    {
        Utils::setError($this->getString($stringName));
    }
    public function checkUserDir($location)
    {
        $this->location = $location;
        $startdir = SetUp::getConfig('starting_dir');
        $thispath = $this->location->getDir(true, false, false, 0);

        if (GateKeeper::getUserInfo('dir') == null) {
            return true;
        }
        if (!is_dir(realpath($thispath))) {
            return false;
        }
        $userpatharray = json_decode(GateKeeper::getUserInfo('dir'), true);
        $userpatharray = $userpatharray ? $userpatharray : array();
        foreach ($userpatharray as $value) {
            $userpath = substr($startdir.$value, 2);
            $pos = strpos($thispath, $userpath);
            if ($pos !== false) {
                return true;
            }
        }
        return false;
    }
    public static function extraChars($str)
    {
        $apici = array('&#34;', '&#39;');
        $realapici = array('"', '\'');
        $str = str_replace($apici, $realapici, $str);
        return $str;
    }
    public function run($location)
    {
        global $encodeExplorer;

        $this->location = $location;

        if ($encodeExplorer->checkUserDir($location) == true) {
            $this->readDir();
        } else {
            $this->readFolders();
        }
    }
}
class Utils
{
    public static function randomString($length = 9)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return '$1$'.$randomString;
    }
    public static function checkCaptcha($postcaptcha, $feat = 'show_captcha')
    {
        if (SetUp::getConfig($feat) !== true) {
            return true;
        }
        if ($postcaptcha) {
            $postcaptcha = strtolower($postcaptcha);

            if (isset($_SESSION['captcha'])
                && $postcaptcha === $_SESSION['captcha']
            ) {
                return true;
            }
        }
        return false;
    }
    public static function mbPathinfo($filepath)
    {
        preg_match(
            '%^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^\.\\\\/]+?)|))[\\\\/\.]*$%im',
            $filepath,
            $node
        );

        if (isset($node[1])) {
            $ret['dirname'] = $node[1];
        } else {
            $ret['dirname'] = '';
        }

        if (isset($node[2])) {
            $ret['basename'] = $node[2];
        } else {
            $ret['basename'] = '';
        }

        if (isset($node[3])) {
            $ret['filename'] = $node[3];
        } else {
            $ret['filename'] = '';
        }

        if (isset($node[5])) {
            $ret['extension'] = $node[5];
        } else {
            $ret['extension'] = '';
        }
        return $ret;
    }
    public static function checkDel($path)
    {
        $startdir = SetUp::getConfig('starting_dir');

        $cash = filter_input(INPUT_GET, 'h', FILTER_SANITIZE_STRING);
        $del = filter_input(INPUT_GET, 'del', FILTER_SANITIZE_STRING);

        $del = str_replace(' ', '+', $del);

        if (md5($del.SetUp::getConfig('salt').SetUp::getConfig('session_name')) === $cash) {

            if (GateKeeper::getUserInfo('dir') != null) {
                $userdirs = json_decode(GateKeeper::getUserInfo('dir'), true);

                foreach ($userdirs as $value) {
                    $userpath = $startdir.$value;
                    $pos = strpos('./'.$path, $userpath);

                    if ($pos !== false) {
                        return true;
                    }
                }
                return false;
            }

            $pos = strpos('./'.$path, $startdir);

            $filepathinfo = Utils::mbPathinfo($path);
            $basepath = $filepathinfo['basename'];
            $evil = array('', '/', '\\', '.');
            $avoid = SetUp::getConfig('hidden_files');

            if ($pos === false
                || in_array($path, $evil)
                || in_array($basepath, $avoid)
                || realpath($path) === realpath($startdir)
                || realpath($path) === realpath(dirname(__FILE__))
            ) {
                return false;
            }
            return true;
        }
    
        return false;
    }
    public function checkVideo($video)
    {
        $realsetup = realpath('../.'.SetUp::getConfig('starting_dir'));
        $realfile = realpath($video);
        if (strpos($realfile, $realsetup) !== false && file_exists($video)) {
            return true;
        }
        return false;
    }
    public static function getCurrentUser($search)
    {
        $currentuser = array();
        foreach (SetUp::getUsers() as $user) {
            if ($user['name'] == $search) {
                $currentuser = $user;
                return $currentuser;
            }
        }
        return false;
    }
    public static function normalizeStr($str)
    {
        $str = strip_tags($str);
        $str = trim($str);
        $str = trim($str, '.');
        $str = stripslashes($str);
        // $str = htmlentities($str);
        $invalid = array(
            '&#34;' => '', '&#39;' => '' ,
            ' ' => '-',
            '{' => '-', '}' => '-',
            '<' => '', '>' => '',
            '`' => '', '´' => '',
            '„' => '', '”' => '', 
            '’' => '', '"' => '',
            '!' => '', '¡' => '',
            '?' => '', '¿' => '',
            '|' => '', '=' => '-', 
            '*' => 'x', ':' => '-',
            ',' => '.', ';' => '',
            'ª' => '', 'º' => '', 
            '~' => '', '&' => '-',  
            '\\' => '', '\'' => '-', '/' => '-',
            '§' => 's', '°' => '', '^' => '', '·' => '',
            '$' => 'usd', '¢' => 'cent', '£' => 'lb', '€' => 'eur',
            '®' => '', '™' => '', '@' => '-at-',
            // '(' => '-', ')' => '-', '.' => '_', ,
        );
        $cleanstring = strtr($str, $invalid);

       $cleanstring = Utils::normalizeName($cleanstring);

        // cut name if has more than 31 chars;
        // if (strlen($cleanstring) > 31) {
        //     $cleanstring = substr($cleanstring, 0, 31);
        // }
        return $cleanstring;
        return $str;
    }
    public static function normalizeName($str)
    {
        $normalized = $str;
        if (function_exists('normalizer_is_normalized')) {
            if (!normalizer_is_normalized($normalized)) {
               $normalized = normalizer_normalize($normalized);
            }
        }
        return $normalized;
    }
    public static function setError($message)
    {
        global $_ERROR;
        $_ERROR .= ' '.$message;
        $_SESSION['error'] = $_ERROR;
    }
    public static function setSuccess($message)
    {
        global $_SUCCESS;
        $_SUCCESS .= ' '.$message;
        $_SESSION['success'] = $_SUCCESS;
    }
    public static function setWarning($message)
    {
        global $_WARNING;
        $_WARNING .= ' '.$message;
        $_SESSION['warning'] = $_WARNING;
    }
    public static function checkMagicQuotes($name)
    {
        if (get_magic_quotes_gpc()) {
            $name = stripslashes($name);
        } else {
            $name = $name;
        }
        return $name;
    }
    public static function checkFinfo()
    {
        if (function_exists('finfo_open')
            && function_exists('finfo_file')
        ) {
            return true;
        }
        return false;
    }
    public static function inList($item, $list)
    {
        if (is_array($list)
            && count($list) > 0
            && in_array($item, $list)
        ) {
            return true;
        }
        return false;
    }

    public static function notList($item, $list)
    {
        if (is_array($list)
            && count($list) > 0
            && !in_array($item, $list)
        ) {
            return true;
        }
        return false;
    }
    public static function countContents($dir)
    {
        $aprila = glob($dir.'/*');
        $quanti = count($aprila);
        if ($aprila) {
            $quantifiles = count(array_filter($aprila, 'is_file'));
            $quantedir = count(array_filter($aprila, 'is_dir'));
        } else {
            $quantifiles = 0;
            $quantedir = 0;
        }
        $result = array(
            'files' => $quantifiles,
            'folders' => $quantedir
        );
        return $result;
    }
}
class SetUp
{

    public static function getAppUrl()
    {

        if (!empty($_SERVER['HTTPS'])
            && $_SERVER['HTTPS'] !== 'off'
            || $_SERVER['SERVER_PORT'] == 443
        ) {
            $http = 'https://';
        } else {
            $http = 'http://';
        }

        $actual_link = $http.$_SERVER['HTTP_HOST'].dirname($_SERVER['REQUEST_URI']);
        $chunks = explode('admin', $actual_link);
        $cleanqs = $chunks[0];
        return $cleanqs;
    }
    public function getFolders($dir = '')
    {
        $directory = '.'.SetUp::getConfig('starting_dir');
        $files = array_diff(
            scandir($dir.$directory),
            array('.', '..')
        );
        $files = preg_grep('/^([^.])/', $files);

        $folders = array();

        foreach ($files as $item) {
            if (is_dir($directory . '/' . $item)) {
                array_push($folders, $item);
            }
        }
        return $folders;
    }
    public static function getLangString($stringName)
    {
        global $_TRANSLATIONS;
        if (isset($_TRANSLATIONS)
            && is_array($_TRANSLATIONS)
            && isset($_TRANSLATIONS[$stringName])
            && strlen($_TRANSLATIONS[$stringName]) > 0
        ) {
            return stripslashes($_TRANSLATIONS[$stringName]);
        } else {
            return '&gt;'.$stringName.'&lt;';
        }
    }
    public static function showLangMenu()
    {
        if (SetUp::getConfig('show_langmenu') == true) {
            return true;
        }
        return false;
    }
    public static function getConfig($name, $default = false)
    {
        global $_CONFIG;
        if (isset($_CONFIG) && isset($_CONFIG[$name])) {
            return $_CONFIG[$name];
        }
        if ($default !== false) {
            return $default;
        }
        return false;
    }
    public static function printAlert()
    {
        global $_ERROR;
        global $_WARNING;
        global $_SUCCESS;

        $alert = false;
        $output = '';
        $sticky_class = '';

        if (SetUp::getConfig('sticky_alerts') === true) {
            $sticky_class = 'sticky-alert '.SetUp::getConfig('sticky_alerts_pos');
        }

        $closebutt = '<button type="button" class="close" aria-label="Close">'
            .'<span aria-hidden="true">&times;</span></button>';

        if (isset($_ERROR) && strlen($_ERROR) > 0) {
            $alert = true;
            $output .= '<div class="response nope alert" role="alert">'
            .$_ERROR.$closebutt.'</div>';
        }
        if (isset($_WARNING) && strlen($_WARNING) > 0) {
            $alert = true;
            $output .= '<div class="response boh alert" role="alert">'
            .$_WARNING.$closebutt.'</div>';
        }
        if (isset($_SUCCESS) && strlen($_SUCCESS) > 0) {
            $alert = true;
            $output .= '<div class="response yep alert" role="alert">'
            .$_SUCCESS.$closebutt.'</div>';
        }
        if ($alert === true) {
            $output = '<div class="alert-wrap '.$sticky_class.'">'.$output.'</div>';
            return $output;
        }
        return false;
    }
    public function getDescription()
    {
        $fulldesc = html_entity_decode(Setup::getConfig('description'), ENT_QUOTES, 'UTF-8');
        $cleandesc = strip_tags($fulldesc, '<img>');

        if (strlen(trim($cleandesc)) > 0) {
            return $fulldesc;
        }
        return false;
    }

    public function switchLang($lang)
    {
        $link = '?lang='.$lang;
        return $link;
    }
    public static function formatModTime($time)
    {
        $timeformat = 'd.m.y H:i:s';
        if (SetUp::getConfig('time_format') != null
            && strlen(SetUp::getConfig('time_format')) > 0
        ) {
            $timeformat = SetUp::getConfig('time_format');
        }
        return date($timeformat, $time);
    }
    public function formatSize($size)
    {
        $sizes = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');
        $syz = $sizes[0];
        for ($i = 1; (($i < count($sizes)) && ($size >= 1024)); $i++) {
            $size = $size / 1024;
            $syz  = $sizes[$i];
        }
        return round($size, 2).' '.$syz;
    }
    public function fullSize($size)
    {
        $size = $size / 1024;
        return round($size);
    }
    public static function getUsers()
    {
        global $_USERS;
        if (isset($_USERS)) {
            return $_USERS;
        }
        return null;
    }
}
class Downloader
{
    public function subDir($checkPath)
    {
        global $gateKeeper;

        if ($gateKeeper->getUserInfo('dir') == null) {
            return true;
        } else {
            $userdirs = json_decode($gateKeeper->getUserInfo('dir'), true);
            foreach ($userdirs as $value) {
                $pos = strpos($checkPath, $value);
                if ($pos !== false) {
                    return true;
                }
            }
        }
        return false;
    }
    public function checkFile($checkfile)
    {
        global $setUp;

        $fileclean = base64_decode($checkfile);
        $file = '../'.urldecode($fileclean);

        $filepathinfo = Utils::mbPathinfo($fileclean);

        $filename = $filepathinfo['basename'];
        $safedir = $filepathinfo['dirname'];

        $safedir = str_replace(array('/', '.'), '', $safedir);
        $realfile = realpath($file);
        $realsetup = realpath('.'.$setUp->getConfig('starting_dir'));

        $avoidDir = array('admin', 'etc');
        $avoidFile = array('index.php', 'thumb.php', '.htaccess', '.htpasswd');

        if (strpos($realfile, $realsetup) !== false
            && !in_array($safedir, $avoidDir) 
            && !in_array($filename, $avoidFile)
            && file_exists($file)
        ) {
            return true;
        }
        return false;
    }
    public function checkTime($time)
    {
        global $setUp;

        $lifedays = (int)$setUp->getConfig('lifetime');
        $lifetime = 86400 * $lifedays;
        if (time() <= $time + $lifetime) {
            return true;
        }
        return false;
    }
    public function getHeaders($getfile, $playmp3 = false)
    {
        global $utils;

        $headers = array();

        $audiofiles = array('mp3','wav');
        $trackfile = './'.urldecode(base64_decode($getfile));
        $file = '.'.$trackfile;

        $filepathinfo = $utils->mbPathinfo($file);
        $filename = $filepathinfo['basename'];
        $dirname = $filepathinfo['dirname'].'/';
        $ext = $filepathinfo['extension'];
        $file_size = File::getFileSize($file);
        $disposition = 'inline';

        if ($ext == 'pdf' || $ext == 'PDF') {
            $content_type = 'application/pdf';
        } elseif ($ext == 'zip' || $ext == 'ZIP') {
            $content_type = 'application/zip';
            $disposition = 'attachment';
        } elseif (in_array(strtolower($ext), $audiofiles)
            && $playmp3 == 'play'
        ) {
            $content_type = 'audio/mp3';
        } else {
            $content_type = 'application/force-download';
        }
        $headers['file'] = $file;
        $headers['filename'] = $filename;
        $headers['file_size'] = $file_size;
        $headers['content_type'] = $content_type;
        $headers['disposition'] = $disposition;
        $headers['trackfile'] = $trackfile;
        $headers['dirname'] = $dirname;

        return $headers;
    }
    public function download(
        $file,
        $filename,
        $file_size,
        $content_type,
        $disposition = 'inline',
        $android = false
    ) {
        @set_time_limit(0);
        session_write_close();
        header("Content-Length: ".$file_size);

        if ($android) {
            header("Content-Type: application/octet-stream");
            header("Content-Disposition: attachment; filename=\"".$filename."\"");
        } else {
            header("Content-Type: $content_type");
            header("Content-Disposition: $disposition; filename=\"".$filename."\"");
            header("Content-Transfer-Encoding: binary");
            header("Expires: -1");
        }
        if (ob_get_level()) {
            ob_end_clean();
        }
        readfile($file);
        return true;
    }
    public function createZip(
        $files = false,
        $folder = false,
        $ajax = false
    ) {
        $response = array('error' => false);
        $stepback = $ajax ? '../' : '';

        global $setUp;
        global $encodeExplorer;

        @set_time_limit(0);

        $script_url = $setUp->getConfig('script_url');
        $maxfiles = $setUp->getConfig('max_zip_files');
        $maxfilesize = $setUp->getConfig('max_zip_filesize');
        $maxbytes = $maxfilesize*1024*1024;

        if ($files && is_array($files)) {
            $totalsize = 0;
            $filesarray = array();
            foreach ($files as $pezzo) {
                $myfile = "../".urldecode(base64_decode($pezzo));
                $totalsize = $totalsize + File::getFileSize($myfile);
                array_push($filesarray, $myfile);
            }
            $howmany = count($filesarray);
        }
        if ($folder) {
            $folderpathinfo = Utils::mbPathinfo($folder);
            $folderbasename = Utils::normalizeStr(Utils::checkMagicQuotes($folderpathinfo['filename']));

            $folderpath = $stepback.$folder;
            if (!is_dir($folderpath)) {
                $response['error'] = '<strong>'.$folder.'</strong> does not exist';
                return $response;
            }
            // Create recursive directory iterator
            $filesarray = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folderpath),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            $foldersize = FileManager::getDirSize($folderpath);
            $totalsize = $foldersize['size'];
            $howmany = 0;
            foreach ($filesarray as $piece) {
                if (!is_dir($piece)) {
                    $howmany++;
                }
            }
        }
        $response['totalsize'] = $totalsize;
        $response['numfiles'] = $howmany;
        // skip if size or number exceedes
        if ($totalsize > $maxbytes) {
            $response['error'] = '<strong>'.$setUp->formatsize($totalsize).'</strong>: '
            .$encodeExplorer->getString('size_exceeded').'<br>(&lt; '.$setUp->formatsize($maxbytes).')';
            return $response;
        }
        if ($howmany > $maxfiles) {
            $response['error'] = '<strong>'.number_format($howmany).'</strong>: '
            .$encodeExplorer->getString('too_many_files').' '.number_format($maxfiles);
            return $response;
        }

        if ($howmany < 1) {
            $response['error'] = '<i class="fa fa-files-o"></i> - <strong>0</strong>';
            return $response;
        }
        // create /tmp/ folder if needed
        if (!is_dir($stepback.'tmp')) {
            if (!mkdir($stepback.'tmp', 0755)) {
                $response['error'] = 'Cannot create a tmp dir for .zip files';
                return $response;
            }
        }
        // delete tmp file if is older than 2 hours 
        $oldtmp = glob($stepback.'tmp/*');
        foreach ($oldtmp as $oldfile) {
            if (filemtime($oldfile) < time() - 60*60*2) {
                unlink($oldfile);
            }
        }
        // create temp zip
        $file = tempnam($stepback.'tmp', 'zip');

        if (!$file) {
            $response['error'] = 'Cannot create: tempnam("tmp","zip") from createZip()';
            return $response;       
        }

        $zip = new ZipArchive();

        if ($zip->open($file, ZipArchive::OVERWRITE) !== true) {
            $response['error'] = 'cannot open: '.$file;
            return $response;
        }
        session_write_close();
        $counter = 0;
        $logarray = array();
        foreach ($filesarray as $piece) {
            $filepathinfo = Utils::mbPathinfo($piece);
            $basename = Utils::normalizeStr(Utils::checkMagicQuotes($filepathinfo['basename']));
            // Skip directories (they would be added automatically)
            if (!is_dir($piece)) {
                $counter++;
                if ($counter > 100) {
                    $zip->close();
                    $zip->open($file, ZipArchive::CHECKCONS);
                    $counter = 0;
                }
                // Add current file to archive
                if ($folder) {
                    $folderpath = substr($stepback.$folder, 0, - strlen($folderbasename));
                    $relativePath = substr($piece, strlen($folderpath));
                    $zip->addFile($piece, $relativePath);
                } else {
                    $zip->addFile($piece, $basename);
                    array_push($logarray, "./".$basename);
                }
            }
        }
        $zip->close();

        $response['dir'] = $stepback.$folder;

        $response['file'] = $file;

        if ($folder) {
            array_push($logarray, $folder);
        }
        $response['logarray'] = $logarray;
        return $response;
    }
}
class Resetter
{
    public static function init()
    {
        global $updater;
        global $resetter;
        global $_USERS;
        global $users;
        $users = $_USERS;

        $resetpwd = filter_input(INPUT_POST, 'reset_pwd', FILTER_SANITIZE_STRING);
        $resetconf = filter_input(INPUT_POST, 'reset_conf', FILTER_SANITIZE_STRING);
        $userh = filter_input(INPUT_POST, 'userh', FILTER_SANITIZE_STRING);
        $getrp = filter_input(INPUT_POST, 'getrp', FILTER_SANITIZE_STRING);

        if ($resetpwd && $resetconf
            && ($resetpwd == $resetconf)
            && $userh
            && $resetter->checkTok($getrp, $userh) == true
        ) {
            $username = $resetter->getUserFromSha($userh);
            $updater->updateUserPwd($username, $resetpwd);
            $updater->updateUserFile('password');
            $resetter->resetToken($resetter->getMailFromSha($userh));
        }
    }
    public function getUserFromSha($usermailsha)
    {
        global $_USERS;
        $utenti = $_USERS;

        foreach ($utenti as $value) {
            if (isset($value['email']) && sha1($value['email']) === $usermailsha) {
                return $value['name'];
            }
        }
    }
    public function getMailFromSha($usermailsha)
    {
        global $_USERS;
        $utenti = $_USERS;

        foreach ($utenti as $value) {
            if (isset($value['email']) && sha1($value['email']) === $usermailsha) {
                return $value['email'];
            }
        }
    }
    public function getUserFromMail($usermail)
    {
        global $_USERS;
        $utenti = $_USERS;

        foreach ($utenti as $value) {
            if (isset($value['email'])) {
                if ($value['email'] === $usermail) {
                    return $value['name'];
                }
            }
        }
    }
    public function resetToken($usermail)
    {
        global $_TOKENS;
        global $tokens;
        $tokens = $_TOKENS;
        unset($tokens[$usermail]);

        $tkns = '$_TOKENS = ';

        if (false == (file_put_contents(
            'admin/users/token.php',
            "<?php\n\n $tkns".var_export($tokens, true).";\n"
        ))
        ) {
            Utils::setError('error, no token reset');
            return false;
        }
    }
    public function setToken($usermail, $path = '')
    {
        global $resetter;
        global $_TOKENS;
        global $tokens;
        $tokens = $_TOKENS;

        $birth = time();
        $salt = SetUp::getConfig('salt');
        $token = sha1($salt.$usermail.$birth);

        $tokens[$usermail]['token'] = $token;
        $tokens[$usermail]['birth'] = $birth;
        $tkns = '$_TOKENS = ';

        if (false == (file_put_contents(
            $path.'token.php',
            "<?php\n\n $tkns".var_export($tokens, true).";\n"
        ))
        ) {
            return false;
        } else {
            $message = array();
            $message['name'] = $resetter->getUserFromMail($usermail);
            $message['tok'] = '?rp='.$token.'&usr='.sha1($usermail);
            return $message;
        }
        return false;
    }
    public function checkTok($getrp, $getusr)
    {
        global $_TOKENS;
        global $tokens;
        $tokens = $_TOKENS;
        $now = time();

        foreach ($tokens as $key => $value) {
            if (sha1($key) === $getusr) {
                if ($value['token'] === $getrp) {
                    if ($now < $value['birth'] + 3600) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
class Chunk
{
    public function setError($message)
    {
        if (isset($_SESSION['error']) && $_SESSION['error'] !== $message) {
            $_SESSION['error'] .= $message;
        } else {
            $_SESSION['error'] = $message;
        }
    }
    public function setWarning($message)
    {
        if (isset($_SESSION['warning']) && $_SESSION['warning'] !== $message) {
            $_SESSION['warning'] .= $message;
        } else {
            $_SESSION['warning'] = $message;
        }
    }
    public function setSuccess($message)
    {
        if (isset($_SESSION['success']) && $_SESSION['success'] !== $message) {
            $_SESSION['success'] .= $message;
        } else {
            $_SESSION['success'] = $message;
        }
    }
    public function checkUserUp($thissize)
    {
        if (isset($_SESSION['fm_user_used']) 
            && isset($_SESSION['fm_user_space'])
        ) {
            $oldused = $_SESSION['fm_user_used'];
            $newused = $oldused + $thissize;
            $freespace = $_SESSION['fm_user_space'];
            
            if ($newused > $freespace) {
                return false;
            } else {
                return true;
            }
        }
        return true;
    }
    public function setUserUp($thissize)
    {
        if (isset($_SESSION['fm_user_used'])) {
            $oldused = $_SESSION['fm_user_used'];
            $newused = $oldused + $thissize;
            $_SESSION['fm_user_used'] = $newused;
        }
    }
    public function setupFilename($resumableFilename, $rid)
    {
        $extension = File::getFileExtension($resumableFilename);
        $filepathinfo = Utils::mbPathinfo($resumableFilename);
        $basename = Utils::normalizeStr(Utils::checkMagicQuotes($filepathinfo['filename']));
        $resumableFilename = $basename.'.'.$extension;
        array_push($_SESSION['upcoda'], $rid);
        array_push($_SESSION['uplist'], $resumableFilename);
        $upcoda = array_unique($_SESSION['upcoda']);
        $uplist = array_unique($_SESSION['uplist']);
        if (count($upcoda) > count($uplist)) {
            $count = count($upcoda);
            $basename = $basename.$count;
            $resumableFilename = $basename.'.'.$extension;
        }
        $_SESSION['upcoda'] = $upcoda;
        $_SESSION['uplist'] = $uplist;
        $resumabledata = array();
        $resumabledata['extension'] = $extension;
        $resumabledata['basename'] = $basename;
        $resumabledata['filename'] = $resumableFilename;
        return $resumabledata;
    }
    public function createFileFromChunks($location, $temp_dir, $fileName, $chunkSize, $totalSize, $logloc)
    {
        global $chunk;
        $upload_dir = str_replace('\\', '', $location);
        $extension = File::getFileExtension($fileName);
        // count all the parts of this file
        $total_files = 0;
        $finalfile = FileManager::safeExtension($fileName, $extension);

        foreach (scandir($temp_dir) as $file) {
            if (stripos($file, $fileName) !== false) {
                $total_files++;
            }
        }
        // check that all the parts are present
        // the size of the last part is between chunkSize and 2*$chunkSize
        if ($total_files * $chunkSize >= ($totalSize - $chunkSize + 1)) {
            // create the final file
            if (is_dir($upload_dir)
                && ($openfile = fopen($upload_dir.$finalfile, 'w')) !== false
            ) {
                for ($i=1; $i<=$total_files; $i++) {
                    fwrite($openfile, file_get_contents($temp_dir.'/'.$fileName.'.part'.$i));
                }
                fclose($openfile);

                // rename the temporary directory (to avoid access from other
                // concurrent chunks uploads) and than delete it
                if (rename($temp_dir, $temp_dir.'_UNUSED')) {
                    Actions::deleteDir($temp_dir.'_UNUSED');
                } else {
                    Actions::deleteDir($temp_dir);
                }
                $chunk->setSuccess(' <span><i class="fa fa-check-circle"></i> '.$finalfile.' </span> ', 'yep');
                $chunk->setUserUp($totalSize);

                $message = array(
                    'user' => GateKeeper::getUserInfo('name'),
                    'action' => 'ADD',
                    'type' => 'file',
                    'item' => $logloc.$finalfile
                );
                Logger::log($message, '');
                if (SetUp::getConfig('notify_upload')) {
                    Logger::emailNotification($logloc.$finalfile, 'upload');
                }

            } else {
                $chunk->setError(' <span><i class="fa fa-exclamation-triangle"></i> cannot create the destination file', 'nope');
                return false;
            }
        }
    }
}
class Template
{
    public function getPart($file, $relative = 'admin/')
    {
        global
        $_CONFIG,
        $_DLIST,
        $_IMAGES,
        $_USERS,
        $_ERROR,
        $_SUCCESS,
        $_WARNING,
        $actual_link,
        $downloader,
        $encodeExplorer,
        $gateKeeper,
        $getcloud,
        $getrp,
        $getusr,
        $hash,
        $location,
        $logoclass,
        $newusers,
        $regactive,
        $resetter,
        $setUp,
        $time,
        $updater,
        $hasvideo,
        $hasimage,
        $hasaudio,
        $imageServer;
        
        if (file_exists($relative.'template/'.$file.'.php')) {
            $thefile = $relative.'template/'.$file.'.php';
        } else {
            $thefile =  $relative.'include/'.$file.'.php';
        }
        include $thefile;
    }
}
