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