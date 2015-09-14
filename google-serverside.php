<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path)
{
    $homeDirectory = getenv('HOME');
    if (empty($homeDirectory)) {
        $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
    }
    return str_replace('~', realpath($homeDirectory), $path);
}
/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(SCOPES);
    $client->setAuthConfigFile(CLIENT_SECRET_PATH);
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH_PHP);

    if (file_exists($credentialsPath)) {
        $accessToken = file_get_contents($credentialsPath);
    } else {
        $authUrl = $client->createAuthUrl();
        $authCode = false;
        if (isset($_POST['authCode']) && $_POST['authCode']) {
            $authCode = $_POST['authCode'];
        }

        if (!$authCode) {
            printf("Open the following link in your browser:\n<a href='%s' target='_blank'>link</a>\n", $authUrl);
            echo "<form method='post'><input name='authCode'><input type='submit' name='Check code'></form>";
        }

        if ($authCode) {
            // Exchange authorization code for an access token.
            $accessToken = $client->authenticate($authCode);

            if (file_put_contents($credentialsPath, $accessToken)) {
                printf("Credentials saved to %s: " . colorize("SUCCESS", "SUCCESS") . "\n", $credentialsPath);
            } else {
                printf("Credentials saved to %s: " . colorize("FAILED", "FAILURE") . "\n", $credentialsPath);
                die;
            }
        } else {
            die("No authCode. Try again.");
        }
    }

    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client= refreshToken($client);
    }

    return $client;
}

function refreshToken($client) {
    global $credentialsPath;
    $client->refreshToken($client->getRefreshToken());
    file_put_contents($credentialsPath, $client->getAccessToken());
    return $client;
}

/**
 * Colorize Console text
 * @param $text
 * @param $status
 * @return string
 * @throws Exception
 */
function colorize($text, $status)
{
    return $text;
}

$client = getClient();
$service = new Google_Service_Drive($client);