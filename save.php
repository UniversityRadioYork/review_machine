<?php

define('SEEMS_LEGIT', true);
define('TEST_MODE', file_exists('.review_machine_test_mode'));

require_once '_auth.php';
require_once '_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 302 Found');
    header('Location: index.php');
    die();
}

if (!(isset($_SESSION))) {
    session_start();
}

if (!(isset($_POST['csrf_token'])) || !(hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']))) {
    header('HTTP/1.1 302 Found');
    header('Location: index.php?message=retry');
    die();
}

$db = DB::getInstance();

if (empty($_POST['github_username'])) {
    header('HTTP/1.1 302 Found');
    header('Location: index.php?message=missing_username');
    die();
}

/** @noinspection PhpUndefinedVariableInspection */
$db->setGithubUsername($memberid, $_POST['github_username']);

if (isset($_POST['projects'])) {
    $db->updateMemberProjects(
        $memberid,
        array_keys($_POST['projects'], 1)
    );
} else {
    $db->updateMemberProjects(
        $memberid,
        []
    );
}

header('HTTP/1.1 302 Found');
header('Location: index.php?message=success');
