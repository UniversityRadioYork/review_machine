<?php

define('SEEMS_LEGIT', true);
define('TEST_MODE', file_exists('.review_machine_test_mode'));

require_once '_auth.php';

require_once '_db.php';
$db = DB::getInstance();

if (!(isset($_SESSION))) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"
          integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
    <title>Review Machine</title>
</head>
<body>
<div class="container">
    <h1>Review Machine</h1>

    <?php
    $msgClass = '';
    $msgText = '';
    if (isset($_GET['message'])) {
        switch ($_GET['message']) {
            case 'success':
                $msgClass = 'success';
                $msgText = 'Review preferences saved successfully!';
                break;
            case 'missing_username':
                $msgClass = 'danger';
                $msgText = 'Missing GitHub username';
                break;
            case 'retry':
                $msgClass = 'warning';
                $msgText = 'Sorry, an error occurred. Please try again.';
                break;
        }
    }
    if (!(empty($msgClass))):
        ?>
        <div class="alert alert-<?= $msgClass ?>">
            <?= $msgText ?>
        </div>
    <?php
    endif;
    ?>

    <p>Thank you for volunteering to review GitHub pull requests! This is a great way to learn about our software
        and start contributing at the same time.</p>

    <form action="save.php" method="post">
        <p>To begin, create a <a href="https://github.com">GitHub</a> account, and enter your username in the box below.
        </p>

        <div class="form-group">
            <label>
                GitHub Username
                <?php
                /** @noinspection PhpUndefinedVariableInspection */
                $username = $db->getGitHubUsername($memberid) ?? '';
                ?>
                <input class="form-control" type="text" name="github_username" value="<?= $username ?>" required/>
            </label>
        </div>

        <p>Now, choose which projects you're interested in reviewing changes for.</p>

        <div class="form-group form-check">
            <ul>
                <?php
                $projects = $db->getProjects();
                $memberProjects = $db->getMemberProjects($memberid);
                foreach ($projects as $project):
                    ?>
                    <li>
                        <label>
                            <input
                                    type="checkbox"
                                    name="projects[<?= $project['repo'] ?>]"
                                    value="1"
                                <?= in_array($project['repo'], $memberProjects) ? "checked" : "" ?>
                            />
                            <strong><?= $project['descr'] ?></strong>
                            (languages: <?= $project['languages'] ?>)
                        </label>
                    </li>
                <?php
                endforeach;
                ?>
            </ul>
        </div>

        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>"/>

        <input type="submit" class="btn btn-primary"/>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
        crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"
        integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo"
        crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"
        integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI"
        crossorigin="anonymous"></script>
</body>
</html>
