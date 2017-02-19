<?php
if ($regactive && $setUp->getConfig("registration_enable") == true) :
    if (file_exists('admin/users/users-new.php')) {
        include 'admin/users/users-new.php';
        if (!empty($newusers)) {
            global $users;
            global $newusers;
            $newuser = $updater->findUserKey($regactive);
            if ($newuser !== false) {
                $username = $newuser['name'];
                $usermail = $newuser['email'];

                if ($updater->findUser($username) === false && $updater->findEmail($usermail) === false) {
                    array_push($users, $newuser);
                    $updater->updateUserFile('new');
                } else {
                    $_ERROR = "<strong>".$username."</strong> ".$encodeExplorer->getString("file_exists");
                }
                $newusers = $updater->removeUserFromValue($newusers, 'name', $username);
                $newusers = $updater->removeUserFromValue($newusers, 'email', $usermail);
                $lifetime = strtotime("-1 day");
                $newusers = $updater->removeOldReg($newusers, 'date', $lifetime);
                if ($updater->updateRegistrationFile($newusers, 'admin/users/')) {
                    $_SUCCESS = $encodeExplorer->getString("registration_completed");
                } else {
                    $_WARNING = "failed updating registration file";
                }
            } else {
                $_ERROR = $encodeExplorer->getString("invalid_link");
            } 
        } else {
            $_ERROR = $encodeExplorer->getString("link_expired");
        } 
    }
endif;
