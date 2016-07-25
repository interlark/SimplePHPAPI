<?php
require_once '../vendor/autoload.php';
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Illuminate\Database\Capsule\Manager as DB;

// json errors
$json_errors = [
    ['code' => 99, 'msg' => ''], // custom error
    ['code' => 100, 'msg' => 'Operation done succeedded.'],
    ['code' => 101, 'msg' => 'Unknown error, please contact to administrator.'], // default
    ['code' => 102, 'msg' => 'Please, put the name and the password for the account you wanna register in the system.'],
    ['code' => 103, 'msg' => 'Please, put the name for the account you wanna register in the system.'],
    ['code' => 104, 'msg' => 'Please, put the password for the account you wanna register in the system.'],
    ['code' => 105, 'msg' => 'Please, use your api key to authenticate in the system.'],
    ['code' => 106, 'msg' => 'Your api key is wrong.'],
    ['code' => 107, 'msg' => 'Please, send the file in the POST. File not found.'],
    ['code' => 108, 'msg' => ''], // file upload error
    ['code' => 109, 'msg' => 'File is not locked for writing. Possibly you are not the owner or the file is already locked.'],
    ['code' => 110, 'msg' => 'Fatal Error: File is not unlocked. Possibly you are not the owner or the file is already unlocked.'],
    ['code' => 111, 'msg' => ''], // update
    ['code' => 112, 'msg' => ''],  // download a file
    ['code' => 113, 'msg' => 'Please set the filename you want to get metadata about.'], //metadata
    ['code' => 114, 'msg' => 'Method not found.'] //notfound handler
];
// 'code' == 200 - means successful operation

function json_error($errorCode, $error_message = ''){
    global $json_errors;

    foreach($json_errors as $err)
    {
        if ($err['code']===$errorCode)
        {
            if ($error_message != '')
            {
                $err['msg'] = $error_message;
            }

            return json_encode($err, JSON_UNESCAPED_UNICODE);
        }
    }

    return(json_encode($json_errors[2])); // default
}

function json_complete($message){ // msg is an array

    $message['code'] = 200; //successful code
    return(json_encode($message, JSON_UNESCAPED_UNICODE));
}

function authenticate(Request $request, Response $response) {
    $apikeyheader = $request->getHeader('Authentication');
    if (empty($apikeyheader)) {
        $response->getBody()->write(json_error(105));
        return null;
    }
    else {
        $apikey =  $apikeyheader[0];
        if (null === User::where("apikey", "=", $apikey)->first())
        {
            $response->getBody()->write(json_error(106));
            return false;
        }

        return true;
    }
}

function getUploadErrorStringById($code)
{
    if(is_numeric($code) && ($code >= 0 && $code <= 8)) {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
                $message = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by extension";
                break;
            default:
                $message = "Unknown upload error";
                break;
        }

        return $message;
    } else {
        return "Internal error occurred in function getUploadErrorStringById(...)";
    }
}

function lockFile(Response $response, $user_id, $filename)
{
    if (0 === DB::connection()->table('files')->where('owner_id', '=', $user_id)->where('filename', '=', $filename)->update([
        'locked' => true
    ])) {
        $response->getBody()->write(json_error(109));
        return false;
    }
    return true;
}

function unlockFile(Response $response, $user_id, $filename)
{
    if (0 === DB::connection()->table('files')->where('owner_id', '=', $user_id)->where('filename', '=', $filename)->update([
            'locked' => false
        ])) {
        $response->getBody()->write(json_error(110));
        return false;
    }
    return true;
}

// curl headers
function getCurlHeaders($apikey = null)
{
    $request_headers = array();
    $request_headers[] = 'user-agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.106 Safari/537.36';
    $request_headers[] = 'accept: application/json';
    $request_headers[] = 'accept-language: en-US,en;q=0.8';
    $request_headers[] = 'accept-encoding: gzip, deflate';
    if ($apikey != null) {
        $request_headers[] = "Authentication: $apikey";
    }
    return $request_headers;
}

?>
