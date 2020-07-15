<?php

function _log($lvl, $msg)
{
    file_put_contents('/var/log/review-machine.webhook.log', date(DATE_ISO8601) . "[$lvl] $msg\n", FILE_APPEND);
}

function oh_no_everythings_dead($why)
{
    _log('FATAL', $why);
    header('HTTP/1.1 500 Internal Server Error');
    die('Oh no, everything\'s dead!');
}

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(function (Throwable $e) {
    oh_no_everythings_dead('Uncaught exception ' . get_class($e) . ' ' . $e->getCode() . ' ' . $e->getMessage() . ' on line ' . $e->getLine() . "\n\n" . $e->getTraceAsString() . "\n\n");
});

define('SEEMS_LEGIT', true);

require_once 'vendor/autoload.php';
require_once '_db.php';

if (!file_exists('.githubsecret')) {
    _log('WARN', 'No GitHub secret set, proceeding without verification! THIS IS BAD!');
} else {
    $hookSecret = trim(file_get_contents('.githubsecret'));
    if (!isset($_SERVER['HTTP_X_HUB_SIGNATURE'])) {
        oh_no_everythings_dead('Missing X-Hub-Signature');;
    } elseif (!extension_loaded('hash')) {
        oh_no_everythings_dead('Missing PHP "hash" extension');
    }

    list($algo, $hash) = explode('=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2) + array('', '');
    if (!in_array($algo, hash_algos(), TRUE)) {
        oh_no_everythings_dead("Unsupported hash algorithm $algo");
    }

    $rawPost = file_get_contents('php://input');
    if ($hash !== hash_hmac($algo, $rawPost, $hookSecret)) {
        oh_no_everythings_dead('Hook signature validation failed');
    }
}

switch ($_SERVER['CONTENT_TYPE']) {
    case 'application/json':
        $json = $rawPost ?: file_get_contents('php://input');
        break;

    case 'application/x-www-form-urlencoded':
        $json = $_POST['payload'];
        break;

    default:
        oh_no_everythings_dead("Unsupported content type: $_SERVER[CONTENT_TYPE]");
}

$payload = json_decode($json, true);
$event_type = strtolower($_SERVER['HTTP_X_GITHUB_EVENT']);
$delivery_id = $_SERVER['HTTP_X_GITHUB_DELIVERY'];
_log('INFO', "Received event $event_type, GUID $delivery_id");

switch ($event_type) {
    case 'ping':
        echo 'pong';
        exit(0);
    case 'pull_request':
        $repo = $payload['pull_request']['base']['repo']['full_name'];
        $sender_username = $payload['pull_request']['user']['login'];
        $pull_number = $payload['pull_request']['number'];
        $action = $payload['action'];

        if (!( $action === 'ready_for_review' || ( $action === 'opened' && !$payload['pull_request']['draft'] ) )) {
            _log('INFO', "Action $action (for PR $repo#$pull_number), ignoring");
            exit(0);
        }
        if (!file_exists('.githubtoken')) {
            oh_no_everythings_dead('Missing GitHub access token');
        }
        $githubtoken = trim(file_get_contents('.githubtoken'));

        $db = DB::getInstance();

        $reviewers = $db->getReviewersForRepo($repo);
        if (empty($reviewers)) {
            _log('WARN', "No reviewers for $repo");
            exit(0);
        }

        // Ensure we don't request a review from ourselves
        $key = array_search($sender_username, $reviewers);
        if ($key !== false) {
            unset($reviewers[$key]);
        }

        if (empty($reviewers)) {
            _log('WARN', "No reviewers for $repo that don't include $sender_username");
            exit(0);
        }

        $reviewer = $reviewers[mt_rand(0, count($reviewers) - 1)];

        $client = new \GuzzleHttp\Client();

        $addReviewerResponse = $client->request('POST', "https://api.github.com/repos/$repo/pulls/$pull_number/requested_reviews", [
            'json' => [
                'reviewers' => [$reviewer]
            ],
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'Authorization' => "token $githubtoken"
            ]
        ]);

        if ($addReviewerResponse->getStatusCode() === 422) {
            // they're not an org member. okay.
            _log('INFO', "Tried to request review for $repo#$pull_number from $reviewer, but they're not a member");
        } else if ($addReviewerResponse->getStatusCode() !== 201) {
            $status = $addReviewerResponse->getStatusCode();
            $body = $addReviewerResponse->getBody()->getContents();
            _log('ERROR', "Failed to request review for $repo#$pull_number from $reviewer [$status]:\n\n$body\n");
        }

        $comment = <<<EOF
Thank you for this PR, @$$sender_username!

@$reviewer, would you mind reviewing this pull request, please? Feel free to ask anyone for help if you're not quite sure what you're doing.
EOF;

        $addCommentResponse = $client->request('POST', "https://api.github.com/repos/$repo/issues/$pull_number/comments", [
            'json' => [
                'body' => $comment
            ],
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'Authorization' => "token $githubtoken"
            ]
        ]);
        if ($addCommentResponse->getStatusCode() !== 201) {
            $status = $addCommentResponse->getStatusCode();
            $body = $addCommentResponse->getBody()->getContents();
            _log('ERROR', "Failed to request review for $repo#$pull_number from $reviewer [$status]:\n\n$body\n");
        }

        header('HTTP/1.1 200 OK');
        echo 'yeet';
        exit(0);
        break;
    default:
        _log('DEBUG', 'Unknown event. Ignoring.');
        exit(0);
}