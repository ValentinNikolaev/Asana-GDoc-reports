<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(-1);
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config.php';

global $client, $credentialsPath;

    // Load previously authorized credentials from a file.
       $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH_PHP);

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
    global $credentialsPath;
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(SCOPES);
    $client->setAuthConfigFile(CLIENT_SECRET_PATH);
    $client->setAccessType('offline');



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


function catchGoogleExceptions($e) {
    global $credentialsPath, $client;
    print "An error occurred: " . $e->getMessage()." \n";
    switch ($e->getCode()) {
        case '401':
            refreshToken($client);
            print "Token refreshed. Restart app \n";
            break;
    }
    die;
}


/**
 * Retrieve a list of File resources.
 *
 * @param Google_Service_Drive $service Drive API service instance.
 * @return Array List of Google_Service_Drive_DriveFile resources.
 */
function retrieveReportFiles($service)
{
    $result = array();
    $pageToken = NULL;

    do {
        try {
            $parameters = array(
                'q' => 'title contains "'.date(DATE_FORMAT_FNAME).'"
                 and trashed = false and mimeType="'.GDOC_SHEET_MIME_GET.'" and
                 properties has { key="isAsanaGDocReport" and value="true" and visibility="PUBLIC"}'
            );
            if ($pageToken) {
                $parameters['pageToken'] = $pageToken;
            }
            $files = $service->files->listFiles($parameters);

            $result = array_merge($result, $files->getItems());
            $pageToken = $files->getNextPageToken();
        } catch (Exception $e) {
            catchGoogleExceptions($e);
            $pageToken = NULL;
        }
    } while ($pageToken);
    return $result;
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


// Print the names and IDs for up to 10 files.
print colorize("Getting Files...", "NOTE") . "\n";
$gFiles = retrieveReportFiles($service);