<?php

defined('SEEMS_LEGIT') or die('nope');

if (file_exists('/usr/local/www/myradio/src/Controllers/traditional_auth.php')) {
    require_once '/usr/local/www/myradio/src/Controllers/traditional_auth.php';
    require_once '/usr/local/www/myradio/src/Controllers/root_cli.php';
    /** @var \MyRadio\ServiceAPI\MyRadio_User $user */
    $user = \MyRadio\ServiceAPI\MyRadio_User::getCurrentUser();
    if (empty($user)) {
        die('No user');
    }
    $memberid = $user->getID();
} else {
    if (TEST_MODE) {
        $memberid = 42069;
    } else {
        die('Could not authenticate to MyRadio');
    }
}
