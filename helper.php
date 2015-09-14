<?php

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
 * Colorize Console text
 * @param $text
 * @param $status
 * @return string
 * @throws Exception
 */
function colorizeCli($text, $status)
{
    $out = "";
    switch ($status) {
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

function getStartTasksDate()
{
    $weekDay = date("w");
    if ($weekDay < 7 && $weekDay > 1) {
        $previous = "-1";
    } elseif ($weekDay == 7) {
        $previous = "-2";
    } else {
        $previous = "-3";
    }

    $datetime = new DateTime(date('Y-m-d ' . TIME_CHECK_FROM, strtotime($previous . ' day')), new DateTimeZone(DATETIME_TIMEZONE_CURRENT));
    $datetime->setTimezone(new DateTimeZone(DATETIME_TIMEZONE_ASANA));
    return $datetime->format('Y-m-d\TH:i:s\Z');

}


function getClientNameByProjectId($projectId) {
    global $clientsProjects;
    foreach ($clientsProjects as $clientName => $clientData) {
        foreach ($clientData['projects'] as $project) {
            if ($project['id'] == $projectId)
                return $clientData['name'];
        }
    }

    return NOT_DEFINED_CLIENT_FOLDER;
}

function getClientEmailsByProjectId($projectId) {
    global $clientsProjects;
    foreach ($clientsProjects as $clientName => $clientData) {
        foreach ($clientData['projects'] as $project) {
            if ($project['id'] == $projectId)
                return $project['send_to'];
        }
    }
}

function getPropertyByKey($file, $key) {
    $properties = $file->getProperties();

    foreach ($properties as $property) {
        if ($property->key == $key) {
            return $property->value;
        }
    }

    return null;
}