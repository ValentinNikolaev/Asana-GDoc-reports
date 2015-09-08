<?php

require __DIR__. '/vendor/autoload.php';

define('APPLICATION_NAME', 'Asana GDoc CLI');
define('CREDENTIALS_PATH', '~/.credentials/drive-api-asana-gdoc.json');
define('CREDENTIALS_PATH_PHP', __DIR__.'/tmp/drive-api-asana-gdoc.json');
define('CLIENT_SECRET_PATH', 'client_secret.json');
define('SCOPES', implode(' ', array(
        Google_Service_Drive::DRIVE_METADATA_READONLY)
));

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient() {
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(SCOPES);
    $client->setAuthConfigFile(CLIENT_SECRET_PATH);
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);
    $credentialsPathPhp = expandHomeDirectory(CREDENTIALS_PATH_PHP);

    if (file_exists($credentialsPath)) {
        $accessToken = file_get_contents($credentialsPath);

    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->authenticate($authCode);

        // Store the credentials to disk.
        if(!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }

        if(!file_exists(dirname($credentialsPathPhp))) {
            mkdir(dirname($credentialsPathPhp), 0700, true);
        }

        if (file_put_contents($credentialsPath, $accessToken)) {
            printf("Credentials saved to %s: ".colorize("SUCCESS", "SUCCESS")."\n", $credentialsPath);
        } else {
            printf("Credentials saved to %s: ".colorize("FAILED", "FAILURE")."\n", $credentialsPath);
            die;
        }

    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->refreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, $client->getAccessToken());
    }
    return $client;
}

/**
 * Expands the home directory alias '~' to the full path.
 * @param string $path the path to expand.
 * @return string the expanded path.
 */
function expandHomeDirectory($path) {
    $homeDirectory = getenv('HOME');
    if (empty($homeDirectory)) {
        $homeDirectory = getenv("HOMEDRIVE") . getenv("HOMEPATH");
    }
    return str_replace('~', realpath($homeDirectory), $path);
}

/**
 * Colorize Console text
 * @param $text
 * @param $status
 * @return string
 * @throws Exception
 */
function colorize($text, $status) {
    $out = "";
    switch($status) {
        case "SUCCESS":
            $out = "[42m"; //Green background
            break;
        case "FAILURE":
            $out = "[41m"; //Red background
            break;
        case "WARNING":
            $out = "[43m"; //Yellow background
            break;
        case "NOTE":
            $out = "[44m"; //Blue background
            break;
        default:
            throw new Exception("Invalid status: " . $status);
    }
    return chr(27) . "$out" . "$text" . chr(27) . "[0m";
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Drive($client);

// Print the names and IDs for up to 10 files.
$optParams = array(
    'maxResults' => 10,
);
$results = $service->files->listFiles($optParams);

if (count($results->getItems()) == 0) {
    print "No files found.\n";
} else {
    print colorize("Files", "NOTE")."\n";
    foreach ($results->getItems() as $file) {
        printf("%s (%s)\n", $file->getTitle(), $file->getId());
    }
}
