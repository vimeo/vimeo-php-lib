<?php
require_once('vimeo.php');
session_start();

// Create the object and enable caching
$vimeo = new phpVimeo('CONSUMER_KEY', 'CONSUMER_SECRET');
$vimeo->enableCache(phpVimeo::CACHE_FILE, './cache', 300);

// Clear session
if ($_GET['clear'] == 'all') {
    session_destroy();
    session_start();
}

// Set up variables
$state = $_SESSION['vimeo_state'];
$request_token = $_SESSION['oauth_request_token'];
$access_token = $_SESSION['oauth_access_token'];

// Coming back
if ($_REQUEST['oauth_token'] != NULL && $_SESSION['vimeo_state'] === 'start') {
    $_SESSION['vimeo_state'] = $state = 'returned';
}

// If we have an access token, set it
if ($_SESSION['oauth_access_token'] != null) {
    $vimeo->setToken($_SESSION['oauth_access_token'], $_SESSION['oauth_access_token_secret']);
}

switch ($_SESSION['vimeo_state']) {
    default:

        // Get a new request token
        $token = $vimeo->getRequestToken();

        // Store it in the session
        $_SESSION['oauth_request_token'] = $token['oauth_token'];
        $_SESSION['oauth_request_token_secret'] = $token['oauth_token_secret'];
        $_SESSION['vimeo_state'] = 'start';

        // Build authorize link
        $authorize_link = $vimeo->getAuthorizeUrl($token['oauth_token'], 'write');

        break;

    case 'returned':

        // Store it
        if ($_SESSION['oauth_access_token'] === NULL && $_SESSION['oauth_access_token_secret'] === NULL) {
            // Exchange for an access token
            $vimeo->setToken($_SESSION['oauth_request_token'], $_SESSION['oauth_request_token_secret']);
            $token = $vimeo->getAccessToken($_REQUEST['oauth_verifier']);

            // Store
            $_SESSION['oauth_access_token'] = $token['oauth_token'];
            $_SESSION['oauth_access_token_secret'] = $token['oauth_token_secret'];
            $_SESSION['vimeo_state'] = 'done';

            // Set the token
            $vimeo->setToken($_SESSION['oauth_access_token'], $_SESSION['oauth_access_token_secret']);
        }

        // Do an authenticated call
        try {
            $videos = $vimeo->call('vimeo.videos.getUploaded');
        }
        catch (VimeoAPIException $e) {
            echo "Encountered an API error -- code {$e->getCode()} - {$e->getMessage()}";
        }

        break;
}

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Vimeo Advanced API OAuth Example</title>
</head>
<body>

    <h1>Vimeo Advanced API OAuth Example</h1>
    <p>This is a basic example of Vimeo's new OAuth authentication method. Everything is saved in session vars, so <a href="?clear=all">click here if you want to start over</a>.</p>

    <?php if ($_SESSION['vimeo_state'] == 'start'): ?>
        <p>Click the link to go to Vimeo to authorize your account.</p>
        <p><a href="<?= $authorize_link ?>"><?php echo $authorize_link ?></a></p>
    <?php endif ?>

    <?php if ($ticket): ?>
        <pre><?php print_r($ticket) ?></pre>
    <?php endif ?>

    <?php if ($videos): ?>
        <pre><?php print_r($videos) ?></pre>
    <?php endif ?>

</body>
</html>
