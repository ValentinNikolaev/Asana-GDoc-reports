<?php

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/helper.php';
//require __DIR__ . '/asana.php';
use Ulrichsg\Getopt\Getopt;
use Ulrichsg\Getopt\Option;

$credentialsPath = expandHomeDirectory(CREDENTIALS_PATH);

$getopt = new Getopt(array(
    (new Option('r', 'refresh_token'))->setDescription('Refresh token if exists'),
    (new Option('d', 'remove'))->setDescription('Remove credentials to authorize new user'),
    (new Option('v', 'version', Getopt::NO_ARGUMENT))->setDescription('Display version information')
));

try {
    $getopt->parse();
    if ($getopt->getOption('v')) {
        logMessage(VERSION);
        closeSession(false);
    }

    if ($getopt->getOption('refresh_token')) {
        refreshTokenCli();
        closeSession(false);
    }

    if ($getopt->getOption('remove')) {
        removeCredentials();
        closeSession(false);
    }

} catch (UnexpectedValueException $e) {
    echo "Error: ".$e->getMessage()."\n";
    echo $getopt->getHelpText();
    exit(1);
}


// Load previously authorized credentials from a file.


$templates = [];

/**
 * Returns an authorized API client.
 * @return Google_Client the authorized client object
 */
function getClient()
{
    global $credentialsPath, $client;
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(GAPI_SCOPES);
    $client->setAuthConfigFile(CLIENT_SECRET_PATH);
    $client->setAccessType('offline');

    if (file_exists($credentialsPath)) {
        $accessToken = file_get_contents($credentialsPath);
    } else {
        logStatusFailure("Please, get token via http://".BASE_SERVER."/connect.php");
        closeSession();
    }

    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        logMessage("Token Expired. Refreshing via curl connect");
        $ch = curl_init("http://".BASE_SERVER."/connect.php");
        curl_exec($ch);
        curl_close($ch);

    }
    return $client;
}

function refreshTokenCli() {
    global $credentialsPath;
    $client = new Google_Client();
    $client->setApplicationName(APPLICATION_NAME);
    $client->setScopes(GAPI_SCOPES);
    $client->setAuthConfigFile(CLIENT_SECRET_PATH);
    $client->setAccessType('offline');
    if (file_exists($credentialsPath)) {
        $accessToken = file_get_contents($credentialsPath);
        $client->setAccessToken($accessToken);
    } else {
        logStatusFailure($credentialsPath." exists");
        closeSession(false);
    }
    refreshToken($client);
}

function removeCredentials() {
    global $credentialsPath;
    if (is_file($credentialsPath))
    {
        if (unlink($credentialsPath))
            logStatusSuccess("Credentials deleted");
        else
            logStatusSuccess("Credentials deleted");
    } else {
        logStatusFailure($credentialsPath." exists");
    }
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
    $exportLinks = $file->getExportLinks();
    if (array_key_exists(GDOC_SHEET_MIME, $exportLinks)) {
        logStatusSuccess("Get export link for a file $file->name");
        $downloadUrl = $exportLinks[GDOC_SHEET_MIME];
    } else {
        logStatusFailure("Get export link for a file $file->name");
        return null;
    }

    if ($downloadUrl) {
        $request = new Google_Http_Request($downloadUrl, 'GET', null, null);
        $httpRequest = $service->getClient()->getAuth()->authenticatedRequest($request);
        if ($httpRequest->getResponseHttpCode() == 200) {
            return $httpRequest->getResponseBody();
        } else {
            logStatusFailure("Download File $file->name");
            return null;
        }
    } else {
        logStatusFailure("Empty export link for a file $file->name");
        return null;
    }
}

/**
 * Retrieve a list of File resources.
 *
 * @param Google_Service_Drive $service Drive API service instance.
 * @return Array List of Google_Service_Drive_DriveFile resources.
 */
function retrieveFiles($service, $findDirs = false)
{
    $result = array();
    $pageToken = NULL;

    do {
        try {
            $parameters = array(
                'q' => "mimeType " . ($findDirs ? '=' : '!=') . "'".GDOC_FOLDER_MIME."' and trashed = false"
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

function catchGoogleExceptions($e) {
    global $credentialsPath, $client;
    logError("An error occurred ".$e->getCode().": " . $e->getMessage());
//    var_dump($e);
    switch ($e->getCode()) {
        case '401':
            refreshToken($client);
            logError("Token refreshed. Please restart app") ;
            break;
    }
    closeSession();
}

function getMerged($address, $mergedCells) {
    if ($mergedCells) {
        $address = strtoupper($address);
        foreach ($mergedCells as $mergedRange) {
            if (strpos($mergedRange,':') !== false) {
                // get the cells in the range
                $aReferences = PHPExcel_Cell::extractAllCellReferencesInRange($mergedRange);
                if ($aReferences)
                    foreach ($aReferences as $aCell) {
                        if ($aCell == $address)
                            return prepareMergeRange($mergedRange);
                    }
            }
        }
    }

    return [];
}

function prepareMergeRange($mergedRange) {
    $result = [];
    if (strpos($mergedRange,':') !== false) {
        $mergedRangeArray = explode(":", $mergedRange);

        foreach ($mergedRangeArray as $key => $cellAddress) {
            $cellAddressArray = str_split($cellAddress);
            foreach ($cellAddressArray as $char) {
                if ($key == 0) {
                    $k = 'start';
                } else {
                    $k = 'end';
                }
//                echo ;

                if (ctype_alpha($char)) {

                    $k2 = 'column';
                } else {
                    $k2 = 'row';
                }

                if (!isset($result[$k]))
                    $result[$k] = [];

                if (!isset($result[$k][$k2]))
                    $result[$k][$k2] = '';


                if (!isset($result[$k]['column']))
                    $result[$k][$k2] = $char;
                else
                    $result[$k][$k2] .= $char;
            }

        }

    }

    if ($result) {
        if (!array_key_exists('start', $result) || !array_key_exists('end', $result))
            return [];
        foreach ($result as $c => $cData) {
            if (!array_key_exists('column', $cData) || !array_key_exists('row', $cData))
                return [];
        }
    }


    return $result;
}

function generateXlsReports($data, $fileName, $clientName)
{
    try {
        $alphas = range('A', 'Z');
        $highestRowBoard = 50;
        logMessage("Start making of the report for client " . $clientName);
        $objReader = PHPExcel_IOFactory::createReader('Excel2007');
        $objPHPExcel = $objReader->load($fileName);// Change the file
        // Set active sheet index to the first sheet, so Excel opens this as the first sheet
        $objPHPExcel->setActiveSheetIndex(0);
        $sheet = $objPHPExcel->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        /*$highestColumn = $sheet->getHighestColumn();
        if (!in_array($highestColumn, $alphas)) {
            printf(colorizeCli("FAILED:", "FAILURE") . 'Too many columns in a template. Highest Column %s \n', $highestColumn);
            return;
        }*/

        $mergedCells = $objPHPExcel->getActiveSheet()->getMergeCells();
        $highestRow = $highestRow > $highestRowBoard ? $highestRowBoard : $highestRow;

        $tableStartCell = '';
        $tableCells = [];
        $dateReport = date('m/d/Y');

        /*$styleArray = array(
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN
                )
            ),
            'alignment' => array(
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_TOP,
            ),
        );*/

        $th = [];
        $projectTitleCell = [];

        /** try to find meta tags */
        for ($row = 1; $row <= $highestRow; $row++) {
            foreach ($alphas as $column) {
                $cellAddress = $column . $row;
                $cellValue = trim($sheet->getCell($cellAddress)->getFormattedValue());
                switch ($cellValue) {
                    case '<date>':
                        $objPHPExcel->getActiveSheet()->setCellValue($cellAddress, $dateReport);
                        break;
                    case '<person>':
                        $objPHPExcel->getActiveSheet()->setCellValue($cellAddress, RESPONSE_PERSON);
                        break;
                    case '<project_title>':
                        $projectTitleCell = [
                            'merged' => getMerged($cellAddress, $mergedCells),
                            'row' => $row,
                            'column' => $column,
                            'style' => $sheet->getStyle($cellAddress)->getSharedComponent()
                        ];
//                    $objPHPExcel->getActiveSheet()->setCellValue($cellAddress, $data['project']->name);
                        break;
                    case '<task_type>':
                        if (!$tableStartCell)
                            $tableStartCell = $cellAddress;
                        $tableCells[] = [
                            'key' => 'tags',
                            'style' => $objPHPExcel->getActiveSheet()->getStyle($cellAddress)->getSharedComponent()
                        ];
                        /** will work for one line th */
                        $th[$column] = [
                            'cellValue' => $sheet->getCell($column . ($row - 1))->getFormattedValue(),
                            'style' => $sheet->getStyle($column . ($row - 1))->getSharedComponent(),
                        ];

                        break;
                    case '<task_completed>':
                        if (!$tableStartCell)
                            $tableStartCell = $cellAddress;
                        $tableCells[] = [
                            'key' => 'completed',
                            'style' => $sheet->getStyle($cellAddress)->getSharedComponent()
                        ];
                        $th[$column] = [
                            'cellValue' => $sheet->getCell($column . ($row - 1))->getFormattedValue(),
                            'style' => $sheet->getStyle($column . ($row - 1))->getSharedComponent(),
                        ];
                        break;
                    case '<notes>':
                        if (!$tableStartCell)
                            $tableStartCell = $cellAddress;
                        $tableCells[] = [
                            'key' => 'notes',
                            'style' => $objPHPExcel->getActiveSheet()->getStyle($cellAddress)->getSharedComponent()
                        ];
                        $th[$column] = [
                            'cellValue' => $sheet->getCell($column . ($row - 1))->getFormattedValue(),
                            'style' => $sheet->getStyle($column . ($row - 1))->getSharedComponent(),
                        ];
                        break;
                    case '<link>':
                        if (!$tableStartCell)
                            $tableStartCell = $cellAddress;
                        $tableCells[] = [
                            'key' => 'link',
                            'style' => $objPHPExcel->getActiveSheet()->getStyle($cellAddress)->getSharedComponent()
                        ];
                        $th[$column] = [
                            'cellValue' => $sheet->getCell($column . ($row - 1))->getFormattedValue(),
                            'style' => $sheet->getStyle($column . ($row - 1))->getSharedComponent(),
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
            $projectsCounter = 0;
            foreach ($data as $projectId => $projectData) {
                $projectsCounter++;
//            var_dump(array_keys($projectData));
                $projectName = isset($projectData['project']) ? $projectData['project']->name : 'Project';

                if ($projectsCounter > 1 && $th) {
                    $thCellRow = $tableStartCellRowLoop + 3;

                    if ($projectTitleCell) {
                        $titleCell = $projectTitleCell['column'] . ($thCellRow - 1);
                        $objPHPExcel->getActiveSheet()->setCellValue($titleCell, $projectName);
                        $objPHPExcel->getActiveSheet()->duplicateStyle($projectTitleCell['style'], $titleCell);
                        // @toDo add support multiline merge

                        if ($projectTitleCell['merged']) {
                            $sheet->mergeCells(
                                $projectTitleCell['merged']['start']['column'] . ($thCellRow - 1) . ":" .
                                $projectTitleCell['merged']['end']['column'] . ($thCellRow - 1)
                            );
                        }
                        $thCellRow++;
                    }

                    foreach ($th as $thCellColumn => $thCell) {
                        $thCellAddress = $thCellColumn . $thCellRow;
                        $objPHPExcel->getActiveSheet()->setCellValue($thCellAddress, $thCell['cellValue']);
                        $objPHPExcel->getActiveSheet()->duplicateStyle($thCell['style'], $thCellAddress);
//                    $objPHPExcel->getActiveSheet()->getStyle($thCellAddress)->applyFromArray($styleArray);
//                    $objPHPExcel->getActiveSheet()->getStyle($thCellAddress)->getAlignment()->setWrapText(true);
                    }

                    $tableStartCellRowLoop = $thCellRow + 1;
                } else {
                    if ($projectTitleCell) {
                        $titleCell = $projectTitleCell['column'] . $projectTitleCell['row'];
                        $objPHPExcel->getActiveSheet()->setCellValue($titleCell, $projectName);

                    }

                }


                foreach ($projectData['tasks'] as $key => $task) {
                    $tableStartCellColumnLoop = $tableStartCellColumn;

                    foreach ($tableCells as $cell) {
                        $tableCellAddress = $tableStartCellColumnLoop . $tableStartCellRowLoop;
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

                        } else {
                            $value = '';
                        }
                        $objPHPExcel->getActiveSheet()->setCellValue($tableCellAddress, $value);
                        if (isset($cell['style']))
                            $objPHPExcel->getActiveSheet()->duplicateStyle($cell['style'], $tableCellAddress);
//                    $objPHPExcel->getActiveSheet()->getStyle($tableCellAddress)->applyFromArray($styleArray);
//                    $objPHPExcel->getActiveSheet()->getStyle($tableCellAddress)->getAlignment()->setWrapText(true);
                        if ($url)
                            $objPHPExcel->getActiveSheet()->getCell($tableCellAddress)->getHyperlink()->setUrl($url);
                        $tableStartCellColumnLoop = $alphas[array_search($tableStartCellColumnLoop, $alphas) + 1];
                    }
                    $tableStartCellRowLoop = $tableStartCellRow + $key;
                }
            }
        }

        $folder = createProjectReportDir($clientName);
        $fileName = $clientName . " " . date(DATE_FORMAT_FNAME) . ".xls";
        $fileReport = $folder . $fileName;
        logMessage("Saving report to $fileReport ...");
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
        $objWriter->save($fileReport);
    } catch (Exception $e) {
        catchGoogleExceptions($e);
    }
    return $fileReport;
}

function createProjectReportDir($projectName = '')
{
    if ($projectName) {
        $pathDir = REPORTS_PATH . $projectName . '/';
        if (!file_exists($pathDir)) {
            logMessage("Project dir '$pathDir' doesn't exists. Try to create... \n");
            if (mkdir($pathDir, 0777, true)) {
                logStatusSuccess("Project '$pathDir' create" );
                return $pathDir;
            } else {
                logStatusFailure("Project '$pathDir' create");
            }
        } else
            return $pathDir;
    }

    return REPORTS_PATH;
}

/**
 * Insert a new permission.
 *
 * @param Google_Service_Drive $service Drive API service instance.
 * @param String $fileId ID of the file to insert permission for.
 * @param String $value User or group e-mail address, domain name or NULL for
"default" type.
 * @param String $type The value "user", "group", "domain" or "default".
 * @param String $role The value "owner", "writer" or "reader".
 * @return Google_Servie_Drive_Permission The inserted permission. NULL is
 *     returned if an API error occurred.
 */
function insertPermission($service, $fileId, $value, $type, $role) {
    $newPermission = new Google_Service_Drive_Permission();
    $newPermission->setValue($value);
    $newPermission->setType($type);
    $newPermission->setRole($role);
    try {
        return $service->permissions->insert($fileId, $newPermission);
    } catch (Exception $e) {
       catchGoogleExceptions($e);
    }
    return NULL;
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
function insertFolder($service, $title, $parentId = 'root')
{
    $dir = new Google_Service_Drive_DriveFile();
    $dir->setTitle($title);
    $dir->setMimeType('application/vnd.google-apps.folder');

    // Set the parent folder.
    if ($parentId != null) {
        $parent = new Google_Service_Drive_ParentReference();
        $parent->setId($parentId);
        $dir->setParents(array($parent));
    }

    try {
        $createdDir = $service->files->insert($dir);

        // Uncomment the following line to print the File ID
        // print 'File ID: %s' % $createdFile->getId();

        return $createdDir;
    } catch (Exception $e) {
        catchGoogleExceptions($e);
    }
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
function insertFile($service, $title, $description, $parentId, $mimeType, $filename, $properties = [])
{
    $file = new Google_Service_Drive_DriveFile();

    $file->setTitle($title);
    $file->setDescription($description);
    $file->setMimeType($mimeType);
    $file->setProperties($properties);

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
        catchGoogleExceptions($e);
    }
}

/**
 * Print a file's metadata.
 *
 * @param Google_Service_Drive $service Drive API service instance.
 * @param string $fileId ID of the file to print metadata for.
 */
function removeFileIfExists($service, $title, $folderId) {
    $result = array();
    $pageToken = NULL;

    do {
        try {
            $parameters = array(
                'q' => "title = '$title' and trashed = false and '$folderId' in parents"
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

    if ($result)
        foreach ($result as $file) {
            logMessage("Deleting exist file $file->title");
//            $service->permissions->insert($file->getId(), $service->about->get()->permissionId);
            deleteFile($service, $file->getId());
        }

}

/**
 * Permanently delete a file, skipping the trash.
 *
 * @param Google_Service_Drive $service Drive API service instance.
 * @param String $fileId ID of the file to delete.
 */
function deleteFile($service, $fileId) {
    try {

        $service->files->delete($fileId);

    } catch (Exception $e) {
        catchGoogleExceptions($e);
    }
}


function getAsanaTasks($startTasksDate = 'now')
{
    global $humanTags;
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
        logStatusFailure('Error while trying to connect to Asana, response code: ' . $asana->responseCode);
        return;
    }
    $workspacesJson = json_decode($workspaces);
    foreach ($workspacesJson->data as $workspace) {
//        echo '<h3>*** ' . $workspace->name . ' (id ' . $workspace->id . ')' . ' ***</h3><br />' . PHP_EOL;
        // Get all projects in the current workspace (all non-archived projects)
        $projects = $asana->getProjectsInWorkspace($workspace->id, $archived = false);
        // As Asana API documentation says, when response is successful, we receive a 200 in response so...
        if ($asana->responseCode != '200' || is_null($projects)) {
            logStatusFailure('Error while trying to connect to Asana [get project, workspace ' . $workspace->name . '], response code: ' . $asana->responseCode);
            continue;
        }
        $projectsJson = json_decode($projects);
        foreach ($projectsJson->data as $project) {
            $rDataKey = getClientNameByProjectId($project->id);
            $returnData[$rDataKey][$project->id] = [
                'workspace' => $workspace,
                'project' => $project,
                'tasks' => [],
            ];
            // Get all tasks in the current project
            $tasks = $asana->getTasksByFilter(['project' => $project->id, 'workspace' => $workspace->id], ['modified_since' => $startTasksDate/*, 'opt_fields' => 'tags, name'*/]);
//        var_dump($tasks);die;

            $tasksJson = json_decode($tasks);
            if ($asana->responseCode != '200' || is_null($tasks)) {
                logStatusFailure('Error while trying to connect to Asana [get tasks, project "' . $project->name . '"], response code: ' . $asana->responseCode);
                unset($returnData[$rDataKey][$project->id]);
                continue;
            }
            $tasks = array();
            foreach ($tasksJson->data as $task) {
                $taskFullInfo = $asana->getTask($task->id);

//        var_dump($tasks);die;

                $taskJson = json_decode($taskFullInfo);
                if ($asana->responseCode != '200' || is_null($tasks)) {
                    logStatusFailure('Error while trying to connect to Asana [get task Info. Project "' . $project->name . '". Task "' . $task->name . '"], response code: ' . $asana->responseCode);
                    unset($returnData[getClientNameByProjectId($project->id)][$project->id]);
                    continue;
                }

                $lastChar = substr(trim($taskJson->data->name), -1);
                if ($lastChar != ':') {
                    $taskTags = [];
                    if ($taskJson->data->tags) {
                        foreach ($taskJson->data->tags as $taskTag) {
                            $taskTags[] = strtr($taskTag->name, $humanTags);
                        }
                    }
                    $tasks[] = array(
                        'txt_link' => 'https://app.asana.com/0/' . $project->id . '/' . $taskJson->data->id,
                        'link' => [
                            'url' => 'https://app.asana.com/0/' . $project->id . '/' . $taskJson->data->id,
                            'title' => 'https://app.asana.com/0/' . $project->id . '/' . $taskJson->data->id
                        ],
                        'task_type' => '',
                        'completed' => $taskJson->data->name,
                        'notes' => '',
                        'created_at' => $taskJson->data->created_at,
                        'modified_at' => $taskJson->data->modified_at,
                        'tags' => implode(", ", $taskTags),
                    );
                }
//                    $tasks[] = '+ <a target="_blank" href="https://app.asana.com/0/'.$project->id.'/'.$task->id.'">' . $task->name . '</a> '
//                        /*.(($task->tags) ? " [".implode (", ", $task->tags)."] " : '')*/.'<br>' . PHP_EOL;
            }
            if ($tasks) {
                $returnData[$rDataKey][$project->id]['tasks'] = $tasks;
                $tasksCounter = $tasksCounter + count($tasks);
                $projectsCounter++;
            } else {
                unset($returnData[$rDataKey][$project->id]);
            }
        }

        // remove empty entities
        if ($returnData)
            foreach ($returnData as $clientName => $clientData) {
                if (!$clientData)
                    unset($returnData[$clientName]);
                else
                    foreach ($clientData as $projectId => $projectData) {
                        if (!$projectData)
                            unset($returnData[$clientName][$projectId]);
                    }
            }
    }

//    var_dump($returnData);die;

    return [
        'data' => $returnData,
        'tasksCounter' => $tasksCounter,
        'projectsCounter' => $projectsCounter,
    ];
}


// Get the API client and construct the service object.
$client = getClient();
$service = new Google_Service_Drive($client);
logMessage ('Current account: '.getConnectedEmail($client) );
$user = $service->about->get()->getUser();
logMessage('Drive owner:' .$user->displayName.' <'.$user->emailAddress.'>');

// Print the names and IDs for up to 10 files.
logMessage("Getting templates...");
$gFiles = retrieveFiles($service);

if (count($gFiles) == 0) {
    logMessage("No templates found", LOG_WARNING);
    closeSession();
} else {

    foreach ($gFiles as $file) {

        /*printf("%s (%s) %s (%s)\n",
            $file->getTitle(),
            $file->getId(),
            $file->getmimeType()/*,
            implode(",", $file->getParents())*//*,'');*/
//
//        var_dump($file);die;
        if ($file->getId() == DAILY_REPORT_TEMPLATE) {
            $downloadResult = downloadFile($service, $file);

            if ($downloadResult) {
                $fileFs = TMP_PATH . $file->getId() . '.xls';
                if (file_put_contents($fileFs, $downloadResult)) {
                    logStatusSuccess("Template saved to $fileFs");
                    $templates[] = ($fileFs);
                } else {
                    logStatusFailure("Template saved to $fileFs");

                }
            } else {
                logStatusFailure("Can not download $fileFs");
            }

        }
    }
}

if (!$templates)
    closeSession();


/**
 * Process reports
 */
$gProjectDir = false;

logMessage("Templates download: ".count($templates));
if ($templates) {
    /**
     * working with gDrive folders
     */

    logMessage("Getting GDrive folders...");
    $gDirs = retrieveFiles($service, true);

    if (count($gDirs) == 0) {
        logMessage("No folders were found");

    } else {
        foreach ($gDirs as $dir) {
            if (!$gProjectDir && $dir->getTitle() == GDOC_REPORT_DIR_NAME) {
                $gProjectDir = $dir;
            }
        }
    }

    if (!$gProjectDir) {
        logMessage("Create GDrive folder '" . GDOC_REPORT_DIR_NAME);
        $gProjectDir = insertFolder($service, GDOC_REPORT_DIR_NAME);
    }
    logMessage("Set permissions for a report dir....");
//    insertPermission($service, $gProjectDir->getId(), null, 'anyone', 'reader'  );
//    insertPermission($service, $gProjectDir->getId(), $user->emailAddress, 'user', 'owner'  );
    $startTasksDate = getStartTasksDate();
    logMessage("Processing Asana tasks....");
    logMessage("Start from: " . $startTasksDate . "[" . DATETIME_TIMEZONE_ASANA . "]");
    $tasks = getAsanaTasks($startTasksDate);
    if (!is_array($tasks))
        logMessage("Something goes wrong during Asana request");
    else {
        logMessage("Tasks found: " . $tasks['tasksCounter'] . ", projects found: " . $tasks['projectsCounter']);

        foreach ($templates as $template) {
            logMessage("Processing Report template '$template' ");
            reportMessage("<h1><strong>Reports:</strong></h1>");
            reportMessage("<h2><a href='".BASE_SERVER."doc_list.php'><strong>Make report drafts!</strong></a></h2>");
            foreach ($tasks['data'] as $clientName => $taskData) {
//                var_dump($taskData);die;
//                if (isset($taskData['project'])) {

                    $fileReport = generateXlsReports($taskData, $template, $clientName);
//                die;
                    if (file_exists($fileReport)) {
                        reportMessage("<br><i>$clientName:</i>");
                        logMessage("Uploading report '" . basename($fileReport) . "' to google drive....\n");

                        $saveDir = $gProjectDir;
                        $foundProjectGDir = false;
                        $reportFolderName = $clientName;

                        $fileReportName = basename($fileReport);
                        foreach ($gDirs as $dir) {
                            if (!$foundProjectGDir && $dir->getTitle() == $reportFolderName) {
                                $saveDir = $dir;
                                $foundProjectGDir = true;
                            }
                        }

                        if (!$foundProjectGDir) {
                            logMessage("Create GDrive folder for project '" . $reportFolderName);
                            $saveDir = insertFolder($service, $reportFolderName, $gProjectDir->getId());
                        }
                        $properties = [
                            [
                                'key'=> 'isAsanaGDocReport',
                                'value' => 'true',
                                'visibility' => 'PUBLIC',

                            ],
                            [
                                'key' => 'asanaClientName',
                                'value' => $clientName,
                                'visibility' => 'PUBLIC',
                            ]
                        ];
                        removeFileIfExists($service, $fileReportName, $saveDir->getId());
                        logMessage("Sending file '" . $fileReportName . "' to google drive");
                        $insertedFile = insertFile($service, $fileReportName, '', $saveDir->getId(), GDOC_SHEET_MIME, $fileReport, $properties);
//                        insertPermission($service, $insertedFile->getId() , $user->emailAddress, 'user', 'owner'  );
                        reportMessage("<a href='https://docs.google.com/spreadsheets/d/".$insertedFile->getId()."/edit' target='_blank'>".$insertedFile->getTitle()."</a>");
                    }
//                }

            }
        }
        closeSession();
    }
}

