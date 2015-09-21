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

function refreshToken($client)
{
    global $credentialsPath;
    $client->refreshToken($client->getRefreshToken());
    file_put_contents($credentialsPath, $client->getAccessToken());
    return $client;
}


function catchGoogleExceptions($e)
{
    global $credentialsPath, $client;
    print "An error occurred: " . $e->getMessage() . " <br>";
    switch ($e->getCode()) {
        case '401':
            refreshToken($client);
            print "Token refreshed. Restart app <br>";
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
                'q' => 'title contains "' . date(DATE_FORMAT_FNAME) . '"
                 and trashed = false and mimeType="' . GDOC_SHEET_MIME_GET . '" and
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

/**
 * Create Draft email.
 *
 * @param  Google_Service_Gmail $service Authorized Gmail API instance.
 * @param  string $userId User's email address. The special value 'me'
 * can be used to indicate the authenticated user.
 * @param  Google_Service_Gmail_Message $message Message of the created Draft.
 * @return Google_Service_Gmail_Draft Created Draft.
 */
function createDraft($service, $user, $message)
{
    $draft = new Google_Service_Gmail_Draft();
    $draft->setMessage($message);
    try {
        $draft = $service->users_drafts->create($user, $draft);
        print 'Draft ID: ' . $draft->getId();
    } catch (Exception $e) {
        print 'An error occurred: ' . $e->getMessage();
    }
    return $draft;
}

$client = getClient();
$service = new Google_Service_Drive($client);
$gMailService = new Google_Service_Gmail($client);


// Print the names and IDs for up to 10 files.
print colorize("Getting Files...", "NOTE") . "<br>";
$gFiles = retrieveReportFiles($service);

if (count($gFiles) == 0) {
    print "No files found.\n";
} else {
    echo '<form method="post">';
    foreach ($gFiles as $file) {
        $exportLinks = $file->getExportLinks();
        if (array_key_exists(GDOC_PDF_MIME, $exportLinks)) {
            $downloadUrl = $exportLinks[GDOC_PDF_MIME];
        } else {
            printf("Skip $file->getName() as not export to " . GDOC_PDF_MIME);
            continue;
        }


        $parents = $file->getParents();
        $folders = [];
        foreach ($parents as $folder) {
            $folderData = $service->files->get($folder->id);
            $folders[] = htmlspecialchars($folderData->getTitle());
        }

        $fileLink = "<a href='".$downloadUrl."' target'_blank'>".$file->getTitle()."</a>";
        echo '<input type="checkbox" name="report[]" value="' . $downloadUrl . '">'.implode("/", $folders).'/'. $fileLink.'<br>';
        echo '<input type="hidden" name="to:' . base64_encode($downloadUrl) . '" value = "'.implode(",",getClientEmailsByClientName(getPropertyByKey($file, 'asanaClientName'))).'">';
        echo '<input type="hidden" name="subject:' . base64_encode($downloadUrl) . '" value = "'.EMAIL_REPORT_SUBJECT.'">';
//        printf("%s (%s) %s\n",
//            $file->getTitle(),
//            $file->getId(),
//            $file->getmimeType());
    }
    echo "<input type='submit' value='Convert to PDF and make drafts!'></form>";
}

if (isset($_POST['report'])) {
    $allowedHeaders = [
        "Content-Type",
        "Content-Disposition"
    ];
    foreach ($_POST['report'] as $reportUrl) {
        $to = EMAIL_REPORT_TO;
        $subject = EMAIL_REPORT_SUBJECT;
        $msg = EMAIL_REPORT_BODY."\n";

        if (isset($_POST['to:'.base64_encode($reportUrl)]))
                $to = $_POST['to:'.base64_encode($reportUrl)];
        if (isset($_POST['subject:'.base64_encode($reportUrl)]))
            $subject = $_POST['subject:'.base64_encode($reportUrl)];

        $mail = "To: $to\nSubject: $subject\n";



        $message = new Google_Service_Gmail_Message();


        $headers = get_headers($reportUrl);
//        echo '<pre>';
//        var_dump($headers);
        if ($headers) {
            $im = file_get_contents($reportUrl);
            if ($im === FALSE) {
                echo 'Skipped. Cannot receive file  ' . $reportUrl . '<br>';
                continue;
            }
            $name = base64_encode($reportUrl);
            $mail .= "Content-Type: multipart/mixed; boundary=\"$name\" \n";
            $mail .= "--$name\n";
            $mail .= "Content-Type: text/plain; charset=UTF-8\n\n";
            $mail .= $msg;
            $mail .= "--$name\n";
            foreach ($allowedHeaders as $allowedHeader) {
                foreach ($headers as $header) {
                    if (strpos($header, $allowedHeader) !== false) {
                        $mail .= $header . "\n";
                    }
                }
            }
            $mail .= "Content-Transfer-Encoding: base64\n\n";
            $mail .= base64_encode($im);
            $mail .= "--$name--\n";

        } else {
            echo 'Skipped. Cannot recive headers <br>';
            continue;
        }
//        echo '<pre>';
//        echo($mail);
        $message->setRaw(base64url_encode($mail));

//        die;

        /*if($im) {
            $mail = 'Content-Type: multipart/mixed; boundary="'+attachment.name+'"\n' + mail + '\n\n';
        }

        // The regular message
        mail += '--'+attachment.name+'\n';
        mail += 'Content-Type: text/plain; charset="UTF-8"\n\n';
        mail += message + '\n';*/
        createDraft($gMailService, 'me', $message);
        echo 'Send message ' . $reportUrl . ' <br>';
    }
}


/**
 * Returns the size of a file without downloading it, or -1 if the file
 * size could not be determined.
 *
 * @param $url - The location of the remote file to download. Cannot
 * be null or empty.
 *
 * @return The size of the file referenced by $url, or -1 if the size
 * could not be determined.
 */
function curl_get_file_headers($url)
{
    // Assume failure.
    $result = -1;

    $curl = curl_init($url);

    // Issue a HEAD request and follow any redirects.
    curl_setopt($curl, CURLOPT_NOBODY, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
//    curl_setopt( $curl, CURLOPT_USERAGENT, get_user_agent_string() );

    $data = curl_exec($curl);
    curl_close($curl);


    return $data;
}


function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}