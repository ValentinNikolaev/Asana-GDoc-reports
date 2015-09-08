<?php

require __DIR__. '/vendor/autoload.php';
require __DIR__. '/config.php';
require __DIR__ . '/asana.php';

define('APPLICATION_NAME', 'Asana GDoc CLI');
define('CREDENTIALS_PATH', '~/.credentials/drive-api-asana-gdoc.json');
define('TMP_PATH', __DIR__.'/tmp/');
define('REPORTS_PATH', __DIR__.'/reports/');
define('CLIENT_SECRET_PATH', 'client_secret.json');
define('SCOPES', implode(' ', array(
        Google_Service_Drive::DRIVE, Google_Service_Drive::DRIVE_APPDATA,Google_Service_Drive::DRIVE_FILE,Google_Service_Drive::DRIVE_METADATA  )
));

define('DAILY_REPORT_TEMPLATE', '17mpulibwqYKlWLmBZx_sM6_nEHWfprrsgVCTNSQ6Gfk');
define('SHEET_INDEX', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

$templates = [];




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
    if(!file_exists(TMP_PATH)) {
        if (mkdir(TMP_PATH, 0700, true))
            printf("Create tmp dir: ".colorize("SUCCESS", "SUCCESS")."\n", TMP_PATH);
        else
            printf("Create tmp dir: ".colorize("FAILED", "FAILURE")."\n", TMP_PATH);
    }

    if(!file_exists(REPORTS_PATH)) {
        if (mkdir(REPORTS_PATH, 0700, true))
            printf("Create report dir: ".colorize("SUCCESS", "SUCCESS")."\n", REPORTS_PATH);
        else
            printf("Create report dir: ".colorize("FAILED", "FAILURE")."\n", REPORTS_PATH);
    }

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

/**
 * Download a file's content.
 *
 * @param Google_Servie_Drive $service Drive API service instance.
 * @param Google_Servie_Drive_DriveFile $file Drive File instance.
 * @return String The file's content if successful, null otherwise.
 */
function downloadFile($service, $file)
{
    $exportLinks =$file->getExportLinks();
    if (array_key_exists(SHEET_INDEX, $exportLinks)) {
        $downloadUrl = $exportLinks[SHEET_INDEX];
    } else {
        printf("No export link for a sheet: " . colorize("No export link for a file.", "FAILURE") . "\n");
        return null;
    }

    if ($downloadUrl) {
        $request = new Google_Http_Request($downloadUrl, 'GET', null, null);
        $httpRequest = $service->getClient()->getAuth()->authenticatedRequest($request);
        if ($httpRequest->getResponseHttpCode() == 200) {
            return $httpRequest->getResponseBody();
        } else {
            printf("Download File: " . colorize("An error occurred during file request.", "FAILURE") . "\n");
            return null;
        }
    } else {
        printf("Download File: " . colorize("No export link for a file.", "FAILURE") . "\n");
        return null;
    }
}

/**
 * Retrieve a list of File resources.
 *
 * @param Google_Service_Drive $service Drive API service instance.
 * @return Array List of Google_Service_Drive_DriveFile resources.
 */
function retrieveAllFiles($service) {
    $result = array();
    $pageToken = NULL;

    do {
        try {
            $parameters = array();
            if ($pageToken) {
                $parameters['pageToken'] = $pageToken;
            }
            $files = $service->files->listFiles($parameters);

            $result = array_merge($result, $files->getItems());
            $pageToken = $files->getNextPageToken();
        } catch (Exception $e) {
            print "An error occurred: " . $e->getMessage();
            $pageToken = NULL;
        }
    } while ($pageToken);
    return $result;
}

function xls($fileName  ){
    $objReader = PHPExcel_IOFactory::createReader('Excel2007');
    $objPHPExcel = $objReader->load($fileName);// Change the file
    $objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue('A1', 'Hello')
        ->setCellValue('B1', 'World!');

// Write the file
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save($fileName."-changed");
}

function getStartTasksDate() {
    $weekDay = date("w");
    if ($weekDay < 7 && $weekDay > 1) {
        $previous = "-1";
    } elseif ($weekDay == 7) {
        $previous = "-2";
    } else {
        $previous = "-3";
    }


    $datetime = new DateTime(date('Y-m-d '.TIME_CHECK_FROM, strtotime($previous.' day')));
    return $datetime->format('Y-m-d\TH:i:s\Z');

}

function getAsanaTasks($startTasksDate = 'now') {
    /**
     * Create a client using Asana API key
     */
    $asana = new Asana(array(
        'apiKey' => ASANA_API_KEY
    ));

    $returnData = [];
    $tasksCounter = 0;
    $projectsCounter = 0;

// Get all workspaces
    $workspaces = $asana->getWorkspaces();
// As Asana API documentation says, when response is successful, we receive a 200 in response so...
    if ($asana->responseCode != '200' || is_null($workspaces)) {
        printf(colorize("FAILED:", "FAILURE").'Error while trying to connect to Asana, response code: ' . $asana->responseCode);
        return;
    }
    $workspacesJson = json_decode($workspaces);
    foreach ($workspacesJson->data as $workspace) {
//        echo '<h3>*** ' . $workspace->name . ' (id ' . $workspace->id . ')' . ' ***</h3><br />' . PHP_EOL;
        // Get all projects in the current workspace (all non-archived projects)
        $projects = $asana->getProjectsInWorkspace($workspace->id, $archived = false);
        // As Asana API documentation says, when response is successful, we receive a 200 in response so...
        if ($asana->responseCode != '200' || is_null($projects)) {
            printf(colorize("FAILED:", "FAILURE").'Error while trying to connect to Asana, response code: ' . $asana->responseCode);
            continue;
        }
        $projectsJson = json_decode($projects);
        foreach ($projectsJson->data as $project) {
            $returnData[$project->id] = [
                'workspace' => $workspace,
                'project' => $project,
                'tasks' => [],
            ];
            // Get all tasks in the current project
            $tasks = $asana->getTasksByFilter(['project' => $project->id, 'workspace' => $workspace->id], ['modified_since' => $startTasksDate/*, 'opt_fields' => 'tags, name'*/]);
//        var_dump($tasks);die;

            $tasksJson = json_decode($tasks);
            if ($asana->responseCode != '200' || is_null($tasks)) {
                printf(colorize("FAILED:", "WARNING").'Error while trying to connect to Asana, response code: ' . $asana->responseCode);
                continue;
            }
            $tasks = array();
            foreach ($tasksJson->data as $task) {

                $lastChar = substr(trim($task->name), -1);
                if ($lastChar != ':')
                    $tasks[] = '+ <a target="_blank" href="https://app.asana.com/0/'.$project->id.'/'.$task->id.'">' . $task->name . '</a> '
                        /*.(($task->tags) ? " [".implode (", ", $task->tags)."] " : '')*/.'<br>' . PHP_EOL;
            }
            if ($tasks) {
                $returnData[$project->id]['tasks'] = $tasks;
                $tasksCounter = $tasksCounter + count($tasks);
                $projectsCounter++;
            }

//            if ($tasks) {
//                echo '<strong>[ ' . $project->name . ' (id ' . $project->id . ')' . ' ]</strong><br>' . PHP_EOL;
//                echo implode("", $tasks);
//                echo '<hr>';
//            }


        }
    }

        return [
            'data' => $returnData,
            'tasksCounter' => $tasksCounter,
            'projectsCounter' => $projectsCounter,
        ];
}

// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Drive($client);

// Print the names and IDs for up to 10 files.
$result = retrieveAllFiles($service);


if (count($result) == 0) {
    print "No files found.\n";
} else {
    print colorize("Files", "NOTE")."\n";
    foreach ($result as $file) {
        printf("%s (%s)\n", $file->getTitle(), $file->getId());
//
//        var_dump($file);die;
        if ($file->getId()  == DAILY_REPORT_TEMPLATE) {
            $downloadResult = downloadFile($service, $file);

            if ($downloadResult) {
                $fileFs = TMP_PATH. $file->getId().'.xls';
                if (file_put_contents($fileFs, $downloadResult)) {
                    printf("Credentials saved to %s: ".colorize("SUCCESS", "SUCCESS")."\n", $fileFs);
                    $templates[] = ($fileFs);
                } else {
                    printf("Credentials saved to %s: ".colorize("FAILED", "FAILURE")."\n", $fileFs);

                }
            } else {
                printf("Download result for '%s': ".colorize("FAILED", "FAILURE")."\n", $file->getTitle());

            }

        }
    }
}

/**
 * Process reports
 */
printf("Templates download: ".colorize(count($templates), "NOTE")."\n");
if ($templates) {
    $startTasksDate = getStartTasksDate();
    printf("Processing Asana tasks....\n");
    printf("Start from: ".colorize($startTasksDate, "WARNING")."\n");
    $tasks = getAsanaTasks($startTasksDate);
    printf("Tasks found: ".$tasks['tasksCounter'].", projects found: ".$tasks['projectsCounter']." \n");
} else {
    printf("Nothing to do here");
}

