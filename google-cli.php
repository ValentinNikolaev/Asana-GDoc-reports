<?php

require __DIR__. '/vendor/autoload.php';
require __DIR__. '/config.php';
//require __DIR__ . '/asana.php';


define('SCOPES', implode(' ', array(
        Google_Service_Drive::DRIVE, Google_Service_Drive::DRIVE_APPDATA,Google_Service_Drive::DRIVE_FILE,Google_Service_Drive::DRIVE_METADATA  )
));


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

function xls($fileName){
    $objReader = PHPExcel_IOFactory::createReader('Excel2007');
    $objPHPExcel = $objReader->load($fileName);// Change the file
    $objPHPExcel->setActiveSheetIndex(0)
        ->setCellValue('A1', 'Hello')
        ->setCellValue('B1', 'World!');

// Write the file
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save($fileName."-changed");
}

function generateXlsReports($data, $fileName) {
    $alphas = range('A', 'Z');
    $highestRowBoard = 50;
    print colorize("Generate report for project ".$data['project']->name, "NOTE")."\n";
    $objReader = PHPExcel_IOFactory::createReader('Excel2007');
    $objPHPExcel = $objReader->load($fileName);// Change the file
    // Set active sheet index to the first sheet, so Excel opens this as the first sheet
    $objPHPExcel->setActiveSheetIndex(0);
    $sheet = $objPHPExcel->getActiveSheet();
    $highestRow = $sheet->getHighestRow();
    $highestColumn = $sheet->getHighestColumn();
    if (!in_array($highestColumn, $alphas)) {
        printf(colorize("FAILED:", "FAILURE").'Too many columns in a template. Highest Column %s \n', $highestColumn);
        return;
    }

    $highestRow = $highestRow > $highestRowBoard ? $highestRowBoard : $highestRow;

    $tableStartCell = '';
    $tableCells = [];
    $dateReport = date('m/d/Y');

    $styleArray = array(
        'borders' => array(
            'allborders' => array(
                'style' => PHPExcel_Style_Border::BORDER_THIN
            )
        ),
        'alignment' => array(
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_TOP,
        ),
    );

    /** try to find meta tags */
    for ($row = 1; $row <= $highestRow; $row++) {
        foreach ($alphas as $column) {
            $cellAddress = $column.$row;
            $cellValue = trim($sheet->getCell($cellAddress)->getFormattedValue());
            switch ($cellValue) {
                case '<date>':
                    $objPHPExcel->getActiveSheet()->setCellValue($cellAddress, $dateReport);
                    break;
                case '<person>':
                    $objPHPExcel->getActiveSheet()->setCellValue($cellAddress, RESPONSE_PERSON);
                    break;
                case '<project_title>':
                    $objPHPExcel->getActiveSheet()->setCellValue($cellAddress , $data['project']->name);
                    break;
                case '<task_type>':
                    if (!$tableStartCell)
                        $tableStartCell = $cellAddress;
                    $tableCells[] = [
                        'key' => 'tags',
                        'style' => $objPHPExcel->getActiveSheet()->getStyle($cellAddress)
                    ];

                    break;
                case '<task_completed>':
                    if (!$tableStartCell)
                        $tableStartCell = $cellAddress;
                    $tableCells[] = [
                        'key' => 'completed',
                        'style' => $objPHPExcel->getActiveSheet()->getStyle($cellAddress)
                    ];
                    break;
                case '<notes>':
                    if (!$tableStartCell)
                        $tableStartCell = $cellAddress;
                    $tableCells[] = [
                        'key' => 'notes',
                        'style' => $objPHPExcel->getActiveSheet()->getStyle($cellAddress)
                    ];
                    break;
                case '<link>':
                    if (!$tableStartCell)
                        $tableStartCell = $cellAddress;
                    $tableCells[] = [
                        'key' => 'link',
                        'style' => $objPHPExcel->getActiveSheet()->getStyle($cellAddress)
                    ];
                    break;
            }
        }
    }

    /**
     * loop tasks
     */
    if ($tableStartCell) {
        $tableStartCellColumn = $tableStartCell[0];
        $tableStartCellRow = substr($tableStartCell, 1);
        $tableStartCellRowLoop = $tableStartCellRow;
        foreach ($data['tasks'] as $key => $task) {
            $tableStartCellColumnLoop = $tableStartCellColumn;

            foreach($tableCells as $cell) {
                $tableCellAddress = $tableStartCellColumnLoop.$tableStartCellRowLoop;
                $value = '';
                $url = false;
                $tplCell = $cell['key'];
                if (isset($task[$tplCell])) {
                    if (is_array($task[$tplCell])) {
                        if (isset($task[$tplCell]['title']))
                            $value = $task[$tplCell]['title'];
                        if (isset($task[$tplCell]['url']))
                            $url = $task[$tplCell]['url'];
                    } else {
                        $value = $task[$tplCell];
                    }

                }
                $objPHPExcel->getActiveSheet()->setCellValue($tableCellAddress , $value);
                if (isset($cell['style']))
                    $objPHPExcel->getActiveSheet()->duplicateStyle($cell['style'], $tableCellAddress);
                $objPHPExcel->getActiveSheet()->getStyle($tableCellAddress)->applyFromArray($styleArray);
                $objPHPExcel->getActiveSheet()->getStyle($tableCellAddress)->getAlignment()->setWrapText(true);
                if ($url)
                    $objPHPExcel->getActiveSheet()->getCell($tableCellAddress)->getHyperlink()->setUrl($url);
                $tableStartCellColumnLoop = $alphas[array_search($tableStartCellColumnLoop, $alphas) + 1];
            }
            $tableStartCellRowLoop = $tableStartCellRow + $key;

        }
    }

    $folder = createProjectReportDir($data['project']->name);
    $fileName = $data['project']->name." ".date('m_d_Y').".xls";
    $fileReport = $folder.$fileName;
    printf("Save report to %s ... \n", $fileReport);
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save($fileReport);
    return $fileReport;
}

function createProjectReportDir($projectName ='') {
    if ($projectName) {
        $pathDir = REPORTS_PATH.$projectName.'/';
        if (!file_exists($pathDir)) {
            printf("Project dir '%s' doesn't exists. Try to create... \n", $pathDir);
            if (mkdir($pathDir, 0777, true)) {
                printf("Project dir %s created: ".colorize("SUCCESS", "SUCCESS")."\n", $pathDir);
                return $pathDir;
            } else {
                printf("Project dir %s was not created: ".colorize("FAILED", "FAILURE")."\n", $pathDir);
            }
        } else
            return $pathDir;
    }

    return REPORTS_PATH;
}

function getStartTasksDate() {
    $weekDay = date("w");
    if ($weekDay < 7 && $weekDay > 1) {
        $previous = "-2";
    } elseif ($weekDay == 7) {
        $previous = "-2";
    } else {
        $previous = "-3";
    }


    $datetime = new DateTime(date('Y-m-d '.TIME_CHECK_FROM, strtotime($previous.' day')), new DateTimeZone(DATETIME_TIMEZONE_CURRENT));
    $datetime->setTimezone( new DateTimeZone(DATETIME_TIMEZONE_ASANA) );
    return $datetime->format('Y-m-d\TH:i:s\Z');

}


/**
 * Insert new file.
 *
 * @param Google_Service_Drive $service Drive API service instance.
 * @param string $title Title of the file to insert, including the extension.
 * @param string $description Description of the file to insert.
 * @param string $parentId Parent folder's ID.
 * @param string $mimeType MIME type of the file to insert.
 * @param string $filename Filename of the file to insert.
 * @return Google_Service_Drive_DriveFile The file that was inserted. NULL is
 *     returned if an API error occurred.
 */
function insertFile($service, $title, $description, $parentId, $mimeType, $filename) {
    $file = new Google_Service_Drive_DriveFile();
    $file->setTitle($title);
    $file->setDescription($description);
    $file->setMimeType($mimeType);

    // Set the parent folder.
    if ($parentId != null) {
        $parent = new Google_Service_Drive_ParentReference();
        $parent->setId($parentId);
        $file->setParents(array($parent));
    }

    try {
        $data = file_get_contents($filename);

        $createdFile = $service->files->insert($file, array(
            'data' => $data,
            'mimeType' => $mimeType,
            'uploadType' => 'media',
            'convert' => true,
        ));

        // Uncomment the following line to print the File ID
        // print 'File ID: %s' % $createdFile->getId();

        return $createdFile;
    } catch (Exception $e) {
        print "An error occurred: " . $e->getMessage();
    }
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
        printf(colorize("FAILED:", "FAILURE").'Error while trying to connect to Asana, response code: ' . $asana->responseCode."\n");
        return;
    }
    $workspacesJson = json_decode($workspaces);
    foreach ($workspacesJson->data as $workspace) {
//        echo '<h3>*** ' . $workspace->name . ' (id ' . $workspace->id . ')' . ' ***</h3><br />' . PHP_EOL;
        // Get all projects in the current workspace (all non-archived projects)
        $projects = $asana->getProjectsInWorkspace($workspace->id, $archived = false);
        // As Asana API documentation says, when response is successful, we receive a 200 in response so...
        if ($asana->responseCode != '200' || is_null($projects)) {
            printf(colorize("FAILED:", "FAILURE").'Error while trying to connect to Asana [get project, workspace '.$workspace->name.'], response code: ' . $asana->responseCode."\n");
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
                printf(colorize("FAILED:", "WARNING").'Error while trying to connect to Asana [get tasks, project "'.$project->name.'"], response code: ' . $asana->responseCode."\n");
                unset($returnData[$project->id]);
                continue;
            }
            $tasks = array();
            foreach ($tasksJson->data as $task) {
                $taskFullInfo = $asana->getTask($task->id);

//        var_dump($tasks);die;

                $taskJson = json_decode($taskFullInfo);
                if ($asana->responseCode != '200' || is_null($tasks)) {
                    printf(colorize("FAILED:", "WARNING").'Error while trying to connect to Asana [get task Info. Project "'.$project->name.'". Task "'.$task->name.'"], response code: ' . $asana->responseCode."\n");
                    unset($returnData[$project->id]);
                    continue;
                }

                $lastChar = substr(trim($taskJson->data->name), -1);
                if ($lastChar != ':') {
                    $taskTags = [];
                    if ($taskJson->data->tags) {
                        foreach ($taskJson->data->tags as $taskTag) {
                            $taskTags[] = $taskTag->name;
                        }
                    }

                    $tasks[] = array(
                        'link' => 'https://app.asana.com/0/' . $project->id . '/' . $taskJson->data->id,
                        'task_type' => '',
                        'completed' => $taskJson->data->completed ? 'Yes' : 'No',
                        'notes' => $taskJson->data->notes,
                        'created_at' => $taskJson->data->created_at,
                        'modified_at' => $taskJson->data->modified_at,
                        'tags' => implode(", ", $taskTags),
                    );
                }
//                    $tasks[] = '+ <a target="_blank" href="https://app.asana.com/0/'.$project->id.'/'.$task->id.'">' . $task->name . '</a> '
//                        /*.(($task->tags) ? " [".implode (", ", $task->tags)."] " : '')*/.'<br>' . PHP_EOL;
            }
            if ($tasks) {
                $returnData[$project->id]['tasks'] = $tasks;
                $tasksCounter = $tasksCounter + count($tasks);
                $projectsCounter++;
            } else {
                unset($returnData[$project->id]);
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
//        printf("%s (%s)\n", $file->getTitle(), $file->getId());
//
//        var_dump($file);die;
        if ($file->getId()  == DAILY_REPORT_TEMPLATE) {
            $downloadResult = downloadFile($service, $file);

            if ($downloadResult) {
                $fileFs = TMP_PATH. $file->getId().'.xls';
                if (file_put_contents($fileFs, $downloadResult)) {
                    printf("Template saved to %s: ".colorize("SUCCESS", "SUCCESS")."\n", $fileFs);
                    $templates[] = ($fileFs);
                } else {
                    printf("Template saved to %s: ".colorize("FAILED", "FAILURE")."\n", $fileFs);

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
    printf("Start from: ".colorize($startTasksDate."[".DATETIME_TIMEZONE_ASANA."]", "WARNING")."\n");
    $tasks = getAsanaTasks($startTasksDate);
    if (!is_array($tasks))
        printf("Something goes wrong during Asana request"."\n");
    else {
        printf("Tasks found: ".$tasks['tasksCounter'].", projects found: ".$tasks['projectsCounter']." \n");

        foreach ($templates as $template) {
            printf("Processing Report template %s \n", $template);
            foreach ($tasks['data'] as $taskData) {
//                var_dump($taskData);die;
                if (isset($taskData['project'])) {
                    $fileReport = generateXlsReports($taskData, $template);
                    if (file_exists($fileReport)) {
                        printf("Uploading report '".basename($fileReport)."' to google drive....\n");
                        insertFile($service, basename($fileReport), '', null, SHEET_INDEX, $fileReport);
                    }
                }

            }
        }
    }


} else {
    printf("Nothing to do here \n");
}

