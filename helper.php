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
function colorize($text, $status)
{
    return $text;
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


function getClientNameByProjectId($projectId)
{
    global $clientsProjects;
    foreach ($clientsProjects as $clientData) {
        foreach ($clientData['projects'] as $project) {
            if ($project['id'] == $projectId)
                return $clientData['name'];
        }
    }

    return NOT_DEFINED_CLIENT_FOLDER;
}

function getClientEmailsByProjectId($projectId)
{
    global $clientsProjects;
    foreach ($clientsProjects as $clientData) {
        foreach ($clientData['projects'] as $project) {
            if ($project['id'] == $projectId)
                return $clientData['send_to'];
        }
    }
    return [];
}

function getClientEmailsByClientName($findClientName)
{
    global $clientsProjects;
    foreach ($clientsProjects as $clientData) {
        if ($clientData['name'] == $findClientName) {
            return $clientData['send_to'];

        }

    }
    return [];
}

function getPropertyByKey($file, $key)
{
    $properties = $file->getProperties();

    foreach ($properties as $property) {
        if ($property->key == $key) {
            return $property->value;
        }
    }

    return null;
}


function logMessage($message, $level = LOG_INFO, $logHasStatus = false) {
    global $isCli;
    $statusTxt = "";
    switch ($level) {
        default:
        case LOG_INFO:
            $prefix = "INFO";
            $status = "NOTE";
            break;
        case LOG_ERR:
            $prefix = "ERROR";
            $status = "FAILURE";
            break;
        case LOG_WARNING:
            $prefix = "WARN";
            $status = "WARNING";
            break;
    }

    if ($logHasStatus !== false)
        switch ($logHasStatus) {
            case 1:
                $statusTxt = "SUCCESS";
                $statusColor = "SUCCESS";
                break;
            case 0:
                $statusTxt = "FAILED";
                $statusColor = "FAILURE";
                break;
        }

    if ($isCli) {
        $showMessage = colorizeCli($prefix, $status).": ".$message;
        $delimeter = "\n";
    } else {
        $showMessage = $prefix.": ".$message;
        $delimeter = "<br>";
    }

    if ($statusTxt) {
        $showMessage .= $isCli ? " [STATUS: ".colorizeCli($statusTxt, $statusColor)."]" : " [STATUS: ".$statusTxt."]";
        $message .= " [STATUS: ".$statusTxt."]";
    }
    $showMessage .= $delimeter;
    print($showMessage);
    pushToLog($prefix.": ".$message);

}

function pushToLog($msg) {
    global $log;
    if (LOG_SHOW_DATETIME)
        $msg = "<strong>".date(LOG_DATETIME_FORMAT)."</strong>"." ".$msg;
    $log[] = $msg;
}


function reportMessage($message) {
    global $report;
    $report[] = $message;
}



function logError($message) {
    logMessage($message, LOG_ERR);
}

function logStatusFailure($message) {
    logMessage($message, LOG_WARNING, 0);
}

function logStatusSuccess($message) {
    logMessage($message, LOG_INFO, 1);
}

function closeSession($sendMail = true) {
    logMessage("Close session");
    if ($sendMail)
        sendAdminEmail(prepareEmailMessage());
    die;
}


function prepareEmailMessage() {
    global $report, $log;
//    var_dump($log + $report);die;
    return implode("<br />", array_merge($report, ["<br><hr>"], $log));
}



function sendAdminEmail($messages) {
    global $emailConfig;
    logMessage( 'Send email to '.EMAIL_ADMIN);
    $mail = new PHPMailer;
    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = $emailConfig['host'];  // Specify main and backup SMTP servers
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = $emailConfig['username'];              // SMTP username
    $mail->Password = $emailConfig['password'];                           // SMTP password
    $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
    $mail->Port = isset($emailConfig['port']) ? $emailConfig['port'] : 587;                                    // TCP port to connect to

    $mail->From = $emailConfig['from'];
    $mail->FromName = $emailConfig['fromName'];
    $mail->addAddress(EMAIL_ADMIN);     // Add a recipient
    $mail->isHTML(true);                                  // Set email format to HTML

    $mail->Subject = $emailConfig['subject'];
    $mail->Body    = $messages;

    if(!$mail->send()) {
        logMessage( 'Message could not be sent.');
        logMessage( 'Mailer Error: ' . $mail->ErrorInfo);
    } else {
        logMessage( 'Message has been sent');
    }
}

function refreshToken(Google_Client $client)
{
    global $credentialsPath;
    $client->refreshToken($client->getRefreshToken());
    file_put_contents($credentialsPath, $client->getAccessToken());
    return $client;
}
