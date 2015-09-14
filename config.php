<?php

$scopes = array(
    Google_Service_Drive::DRIVE, Google_Service_Drive::DRIVE_APPDATA, Google_Service_Drive::DRIVE_FILE, Google_Service_Drive::DRIVE_METADATA,
    Google_Service_Gmail::GMAIL_INSERT, Google_Service_Gmail::GMAIL_MODIFY, Google_Service_Gmail::MAIL_GOOGLE_COM)
;

$humanTags = [
    'dev' => 'backend development',
    'bugs' => 'bug fixing ',
    'css' => 'slicing the pages',
    'frontend' => 'frontend work(javascript)',
    'research' => 'investigation, research',
    'docs' => 'documenting',
    'chats' => 'getting in touch with 3rd party',
    'server' => 'server setup',
    'deploy' => 'deployment',
    'unittests' => 'writing tests',
    'qa' => 'testing',
];


define('GAPI_SCOPES', implode(' ', $scopes));
define('DEBUG', 1);
define('ASANA_API_KEY', '3ivyLe5P.NK19uB8GNejRbxSigjLSW09');
define('TIME_CHECK_FROM', '18:45');
define('DATETIME_TIMEZONE_CURRENT', 'Europe/Kiev');
define('DATETIME_TIMEZONE_ASANA', 'HST');

define('DAILY_REPORT_TEMPLATE', '17mpulibwqYKlWLmBZx_sM6_nEHWfprrsgVCTNSQ6Gfk');
define('GDOC_SHEET_MIME', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
define('GDOC_SHEET_MIME_GET', 'application/vnd.google-apps.spreadsheet');
define('GDOC_FOLDER_MIME', 'application/vnd.google-apps.folder');
define('GDOC_PDF_MIME', 'application/pdf');

define('APPLICATION_NAME', 'Asana GDoc CLI');
define('CREDENTIALS_PATH', '~/.credentials/drive-api-asana-gdoc.json');
define('CREDENTIALS_PATH_PHP', __DIR__.'/credentials/drive-api-asana-gdoc.json');
define('TMP_PATH', __DIR__.'/tmp/');
define('REPORTS_PATH', __DIR__.'/reports/');
define('CLIENT_SECRET_PATH', 'client_secret.json');
define('RESPONSE_PERSON', 'Eugene Pyvovarov');

define('GDOC_REPORT_DIR_NAME', 'Reports');

define("DATE_FORMAT_FNAME", "m_d_Y");