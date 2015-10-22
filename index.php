<?php
header('Location: connect.php');
require __DIR__. '/config.php';
require __DIR__ . '/asana.php';

/**
 * set debug level
 */
switch (DEBUG) {
    case 1:
        error_reporting(E_ALL);
        ini_set('display_errors', true);
        break;
}

$weekDay = date("w");
if ($weekDay < 7 && $weekDay > 1) {
    $previous = "-1";
} elseif ($weekDay == 7) {
    $previous = "-2";
} else {
    $previous = "-3";
}


$datetime = new DateTime(date('Y-m-d '.TIME_CHECK_FROM, strtotime($previous.' day')), new DateTimeZone(DATETIME_TIMEZONE_CURRENT));
$datetime->setTimezone( new DateTimeZone(DATETIME_TIMEZONE_ASANA) );
$startTasksDate = $datetime->format('Y-m-d\TH:i:s\Z');
echo 'Start from:'.$startTasksDate." [".DATETIME_TIMEZONE_ASANA."]<br>";


/**
 * Create a client using Asana API key
 */

$asana = new Asana(array(
    'apiKey' => ASANA_API_KEY
));

// Get all workspaces
$workspaces = $asana->getWorkspaces();
// As Asana API documentation says, when response is successful, we receive a 200 in response so...
if ($asana->responseCode != '200' || is_null($workspaces)) {
    echo 'Error while trying to connect to Asana, response code: ' . $asana->responseCode;
    return;
}
$workspacesJson = json_decode($workspaces);
foreach ($workspacesJson->data as $workspace) {
    echo '<h3>*** ' . $workspace->name . ' (id ' . $workspace->id . ')' . ' ***</h3><br />' . PHP_EOL;
    // Get all projects in the current workspace (all non-archived projects)
    $projects = $asana->getProjectsInWorkspace($workspace->id, $archived = false);
    // As Asana API documentation says, when response is successful, we receive a 200 in response so...
    if ($asana->responseCode != '200' || is_null($projects)) {
        echo 'Error while trying to connect to Asana, response code: ' . $asana->responseCode;
        continue;
    }
    $projectsJson = json_decode($projects);
    foreach ($projectsJson->data as $project) {

        // Get all tasks in the current project
        $tasks = $asana->getTasksByFilter(['project' => $project->id, 'workspace' => $workspace->id], ['modified_since' => $startTasksDate/*, 'opt_fields' => 'tags, name'*/]);
//        var_dump($tasks);die;

        $tasksJson = json_decode($tasks);
        if ($asana->responseCode != '200' || is_null($tasks)) {
            echo 'Error while trying to connect to Asana, response code: ' . $asana->responseCode;
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
            echo '<strong>[ ' . $project->name . ' (id ' . $project->id . ')' . ' ]</strong><br>' . PHP_EOL;
            echo implode("", $tasks);
            echo '<hr>';
        }


    }
}
