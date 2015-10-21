<?php
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helper.php';



// Load previously authorized credentials from a file.
$credentialsPath = expandHomeDirectory(CREDENTIALS_PATH_PHP);

/**
* Returns an authorized API client.
 * @return Google_Client the authorized client object
*/
function getClient()
{
    global $credentialsPath;
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(GAPI_SCOPES);
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
            echo "<form method='post'><input name='authCode'><input type='submit' name='Check auth code'></form>";
        }

        if ($authCode) {
            // Exchange authorization code for an access token.
            $accessToken = $client->authenticate($authCode);

            if (file_put_contents($credentialsPath, $accessToken)) {
                printf("Credentials saved to %s: " . colorize("SUCCESS", "SUCCESS") . "<br>", $credentialsPath);
            } else {
                printf("Credentials saved to %s: " . colorize("FAILED", "FAILURE") . "<br>", $credentialsPath);
                die;
            }
        } else {
            die("No authCode. Try again.");
        }
    }

    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client = refreshToken($client);
    }

    return $client;
}
