<?php
require_once '../app/init.php';
require_once '../app/helper.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \Illuminate\Database\Capsule\Manager as DB;

$configuration = [
    'settings' => [
        'displayErrorDetails' => /*true*/true,
        'debug' => /*true*/true
    ],
];

$container = new \Slim\Container($configuration);

//Override the default Not Found Handler -> API JSON
$container['notFoundHandler'] = function ($container) {
    return function (Request $request, Response $response) use ($container) {
        return $container['response']
            ->withStatus(404)
            ->withHeader('Content-Type','application/json; charset=utf-8')
            ->write(json_error(114));
    };
};

$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig('../app/views', [
        'cache' => false
    ]);

    $view->addExtension(new \Slim\Views\TwigExtension(
        $container->router,
        $container->request->getUri()
    ));

    return $view;
};

$app = new \Slim\App($container);

// Register user
$app->get(
/**
 * @param name, pass
 * @return response - apikey
 */
    '/register', function (Request $request, Response $response) {
        $getVars = $request->getQueryParams();
        $name = $getVars['name'];
        $pass = $getVars['pass'];

        if ($name === null && $pass === null) {
            $response->getBody()->write(json_error(102));
        }
        else if ($name === null) {
            $response->getBody()->write(json_error(103));
        }
        else if ($pass === null) {
            $response->getBody()->write(json_error(104));
        }
        else {
            // everything's fine, let's register the user if it's possible
            $apikey = md5(microtime().rand());

            try {
                User::create(
                    [
                        'username' => $name,
                        'password' => md5($pass),
                        'apikey' => $apikey
                    ]
                );
                $response->getBody()->write(
                    json_complete(
                        [
                            'msg' => $name . " was created sucessfully",
                            'apikey' => $apikey
                        ]
                    )
                );
            } catch (Illuminate\Database\QueryException $e) {
                $error_msg = $e->errorInfo[2]; // 1 - code, 2 - msg
                $response->getBody()->write(json_error(99, "Can not create a user. Error : $error_msg"));
            }
        }

        return  $response->withAddedHeader('Content-Type','application/json; charset=utf-8');
});

// в следующих обращениях к апи нужен заголовог Authentication c apikey,
// apikey хранится у пользователья после регистрации, как, например, в ... вк апи


// Upload a file (with further archive it)
$app->post(
/**
 * @param file [,compress (bool)]
 * @return response
 */
'/upload', function (Request $request, Response $response) {
    if (authenticate($request, $response) === true)
    {
        try {
            $files = $request->getUploadedFiles();

            if (empty($files)) {
                $response->getBody()->write(json_error(107));
            } else {
                /* @var \Slim\Http\UploadedFile $upFile */
                $upFile = array_shift($files); // 1st only

                DB::connection()->beginTransaction(); // in a case when something's going wrong,
                // the commit would never be hit, so the trans. would be automatically rollbacked.

                if (UPLOAD_ERR_OK === $upFile->getError()) {
                    $gzHeader = $request->getHeader('compress');
                    $compress = false;

                    if ($gzHeader!=null && $gzHeader!=[])
                    {
                        $fzlHeader = array_pop($gzHeader);
                        if (filter_var($fzlHeader, FILTER_VALIDATE_BOOLEAN) == true) {
                            $compress = true;
                        }
                    }

                    $uploadFileName = $upFile->getClientFilename();

                    if (!file_exists("../files/$uploadFileName" . ($compress ? ".gz" : ""))) {

                        $compressFailed = null;

                        // try to compress moved file
                        if ($compress) {
                            //zlib compress the file
                            $gzfile = $uploadFileName . ".gz";
                            $fp = gzopen("../files/$gzfile", 'w9');
                            if ($fp) {
                                gzwrite($fp, file_get_contents($upFile->file));
                                gzclose($fp);
                            } else {
                                $compress = false;
                                $compressFailed = true;
                                //throw new RuntimeException(sprintf('Error opening file %1s.gz', $uploadFileName));
                            }
                        } else{
                            $upFile->moveTo("../files/$uploadFileName");
                        }

                        if ($compress) {
                            File::create([
                                'filename' => $uploadFileName . ".gz",
                                'owner_id' => User::where("apikey", "=", $request->getHeader('Authentication')[0])->first()->id,
                                'compressed' => true
                            ]);
                        } else {
                            File::create([
                                'filename' => $uploadFileName,
                                'owner_id' => User::where("apikey", "=", $request->getHeader('Authentication')[0])->first()->id
                            ]);
                        }

                        DB::connection()->commit();

                        $msg = "File " . $uploadFileName . ($compress ? ".gz" : "") . " has been uploaded successfully.";

                        if ($compressFailed !== null) {
                            $msg = $msg . "But file compression has failed.";
                        }

                        $response->getBody()->write(
                            json_complete(
                                [
                                    'msg' => $msg
                                ]
                            )
                        );
                    } else {
                        $response->getBody()->write(json_error(108, "Error, the file already exists"));
                    }
                } else {
                    $response->getBody()->write(json_error(108, "Error occurred during file uploading: " . getUploadErrorStringById($upFile->getError())));
                }
            }
        }
        catch (InvalidArgumentException $e)
        {
            $response->getBody()->write(json_error(108, "Error occurred during file uploading (InvalidArgumentException): " . $e->getMessage()));
        }
        catch (RuntimeException $e)
        {
            $response->getBody()->write(json_error(108, "Error occurred during file uploading (RuntimeException): " . $e->getMessage()));
        }
    }

    return  $response->withAddedHeader('Content-Type','application/json; charset=utf-8');
});

//Refresh the file with new one
$app->post(
/**
 * @param file
 * @return response
 */
'/update', function (Request $request, Response $response) {
    if (authenticate($request, $response) === true) {
        try {
            $files = $request->getUploadedFiles();

            if (empty($files)) {
                $response->getBody()->write(json_error(107));
            } else {
                /* @var \Slim\Http\UploadedFile $upFile */
                $upFile = array_shift($files); // 1st only
                if (UPLOAD_ERR_OK === $upFile->getError()) {
                    $filename = $upFile->getClientFilename();
                    if (file_exists("../files/$filename")) {
                        $user_id = User::where("apikey", "=", $request->getHeader('Authentication')[0])->first()->id;
                        if (lockFile($response, $user_id, $filename)) {
                            unlink("../files/$filename");
                            if (move_uploaded_file($upFile->file, "../files/$filename")) {
                                if (unlockFile($response, $user_id, $filename)) {
                                    $response->getBody()->write(
                                        json_complete(
                                            [
                                                'msg' => 'File successfully updated'
                                            ]
                                        )
                                    );
                                }
                            } else {
                                $response->getBody()->write(json_error(111, "Error while moving to the store."));
                            }
                        }
                    } else {
                        $response->getBody()->write(json_error(111, "File not found."));
                    }
                } else {
                    $response->getBody()->write(json_error(108, "Error occurred during file uploading: " . getUploadErrorStringById($upFile->getError())));
                }
            }
        }
        catch (InvalidArgumentException $e)
        {
            $response->getBody()->write(json_error(108, "Error occurred during file uploading (InvalidArgumentException): " . $e->getMessage()));
        }
        catch (RuntimeException $e)
        {
            $response->getBody()->write(json_error(108, "Error occurred during file uploading (RuntimeException): " . $e->getMessage()));
        }
        catch (Exception $e) {
            $response->getBody()->write(json_error(111, "Error: " . $e->getMessage()));
        }
    }

    return  $response->withAddedHeader('Content-Type','application/json; charset=utf-8');
});

// Download the file
$app->get(
/**
 * @param filename
 * @return file
 */
'/getfile', function (Request $request, Response $response) {
    if (authenticate($request, $response) === true) {
        $getVars = $request->getQueryParams();
        $filename = $getVars['filename'];

        if ($filename === null) {
            $response->getBody()->write(json_error(112, "Please set the filename you want to get."));
        } else {
            $filepath = "../files/$filename";
            if (file_exists($filepath)) {
                // get mime-type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimetype = finfo_file($finfo, $filepath);
                if (!$mimetype) {
                    $mimetype = 'application/octet-stream';
                }
                finfo_close($finfo);
                $response = $response->withHeader('Content-Description', 'File Transfer')
                    ->withHeader('Content-Type', $mimetype)
                    ->withHeader('Content-Disposition', 'attachment; filename="'.basename($filepath).'"') //if via browser
                    ->withHeader('Expires', '0')
                    ->withHeader('Cache-Control', 'must-revalidate')
                    ->withHeader('Content-Length', filesize($filepath));
                readfile($filepath);
            } else {
                $response->getBody()->write(json_error(112, "Requested file not found on the server."));
            }
        }
    }

    return $response;
});

// get list of all files on the server
$app->get(
/**
 * @return filelist
 */
'/getfilelist', function (Request $request, Response $response) {
    if (authenticate($request, $response) === true) {
        $r_arr = File::all(['filename'])->toArray();
        $response->getBody()->write(json_complete([$r_arr]));
    }

    return $response->withAddedHeader('Content-Type','application/json; charset=utf-8');
});

// get file's metadata
$app->get(
/**
 * @param filename
 * @return file's metadata
 */
    '/getfilemetadata', function (Request $request, Response $response) {
    if (authenticate($request, $response) === true) {
        $getVars = $request->getQueryParams();
        $filename = $getVars['filename'];

        if ($filename === null) {
            $response->getBody()->write(json_error(113));
        } else {
            $filepath = "../files/$filename";
            if (file_exists($filepath)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimetype = finfo_file($finfo, $filepath);
                if (!$mimetype) {
                    $mimetype = 'not determined';
                }
                finfo_close($finfo);

                $finfo = finfo_open(FILEINFO_MIME_ENCODING);
                $mimeenc = finfo_file($finfo, $filepath);
                if (!$mimeenc) {
                    $mimeenc = 'not determined';
                }
                finfo_close($finfo);

                $dbcomp = File::where('filename', '=', '111.png')->get(['compressed'])->toArray()[0]['compressed'];

                $metadata = [
                    'filesize (bytes)' => filesize($filepath),
                    'mime type' => $mimetype,
                    'mime encoding' => $mimeenc,
                    'is locked' => filter_var($dbcomp, FILTER_VALIDATE_BOOLEAN)
                ];

                $response->getBody()->write(json_complete($metadata));

            } else {
                $response->getBody()->write(json_error(112, "Requested file not found on the server."));
            }
        }
    }

    return $response->withAddedHeader('Content-Type','application/json; charset=utf-8');
});

$app->get(
/**
 * Tests
 */
    '/testapi', function (Request $request, Response $response) {
        $getVars = $request->getQueryParams();
        $username = $getVars['username'];
        $password = $getVars['password'];
        if ($username === null) {
            $username = 'random_user';
        }
        if ($password === null) {
            $password = 'random_password';
        }

        $params = [];
        $params['username'] = $username;
        $params['password'] = $password;

        // register test
        $service_url = "https://xsolla.local/register?name=$username&pass=$password";
        $curl = curl_init($service_url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, getCurlHeaders());
        $curl_response = curl_exec($curl);
        $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $headers = nl2br(rtrim(substr($curl_response, 0, $header_size)));
        $json = substr($curl_response, $header_size);

        // http://stackoverflow.com/questions/10975775/how-to-determine-if-a-string-was-compressed
        if (0 === mb_strpos($json , "\x1f" . "\x8b" . "\x08")) // if it was compressed -> uncompress
        {
            $json = gzdecode($json);
        }

        curl_close($curl);

        $params['register_response_headers'] = $headers;
        $params['register_json'] = $json;
        $params['register_code'] = json_decode($json)->code;
        if ($params['register_code'] === 200) {
            $params['register_apikey'] = json_decode($json)->apikey;
        }

        if ($params['register_code'] === 200) {
            // upload file 'THE_CATCHER_IN_THE_RYE.txt' with compression
            $service_url = "https://xsolla.local/upload";
            $curl = curl_init($service_url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $pcurl = getCurlHeaders($params['register_apikey']);
            $pcurl[] = 'compress: true';
            $pcurl[] = 'Expect:';
            curl_setopt($curl, CURLOPT_HTTPHEADER, $pcurl);
            $args['file'] = curl_file_create('../test_api/upload_files/THE_CATCHER_IN_THE_RYE.txt');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $args);

            $curl_response = curl_exec($curl);
            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $headers = nl2br(rtrim(substr($curl_response, 0, $header_size)));
            $json = substr($curl_response, $header_size);

            // http://stackoverflow.com/questions/10975775/how-to-determine-if-a-string-was-compressed
            if (0 === mb_strpos($json, "\x1f" . "\x8b" . "\x08")) // if it was compressed -> uncompress
            {
                $json = gzdecode($json);
            }

            curl_close($curl);

            $params['upload1_response_headers'] = $headers;
            $params['upload1_json'] = $json;
            $params['upload1_code'] = json_decode($json)->code;

            // upload file 'art.png' without compression
            $service_url = "https://xsolla.local/upload";
            $curl = curl_init($service_url);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $pcurl = getCurlHeaders($params['register_apikey']);
            $pcurl[] = 'Expect:';
            curl_setopt($curl, CURLOPT_HTTPHEADER, $pcurl);
            $args['file'] = curl_file_create('../test_api/upload_files/art.png');
            curl_setopt($curl, CURLOPT_POSTFIELDS, $args);

            $curl_response = curl_exec($curl);
            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $headers = nl2br(rtrim(substr($curl_response, 0, $header_size)));
            $json = substr($curl_response, $header_size);

            // http://stackoverflow.com/questions/10975775/how-to-determine-if-a-string-was-compressed
            if (0 === mb_strpos($json, "\x1f" . "\x8b" . "\x08")) // if it was compressed -> uncompress
            {
                $json = gzdecode($json);
            }

            curl_close($curl);

            $params['upload2_response_headers'] = $headers;
            $params['upload2_json'] = $json;
            $params['upload2_code'] = json_decode($json)->code;

            if ($params['upload1_code'] === 200) {
                // update file 'THE_CATCHER_IN_THE_RYE.txt.gz'
                $service_url = "https://xsolla.local/update";
                $curl = curl_init($service_url);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HEADER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                $pcurl = getCurlHeaders($params['register_apikey']);
                $pcurl[] = 'Expect:';
                curl_setopt($curl, CURLOPT_HTTPHEADER, $pcurl);
                $args['file'] = curl_file_create('../test_api/upload_updated_files/THE_CATCHER_IN_THE_RYE.txt.gz');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $args);

                $curl_response = curl_exec($curl);
                $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                $headers = nl2br(rtrim(substr($curl_response, 0, $header_size)));
                $json = substr($curl_response, $header_size);

                // http://stackoverflow.com/questions/10975775/how-to-determine-if-a-string-was-compressed
                if (0 === mb_strpos($json, "\x1f" . "\x8b" . "\x08")) // if it was compressed -> uncompress
                {
                    $json = gzdecode($json);
                }

                curl_close($curl);

                $params['update_response_headers'] = $headers;
                $params['update_json'] = $json;
                $params['update_code'] = json_decode($json)->code;
            }

            if ($params['upload2_code'] === 200) {
                // get file content, in this case, we would get art.png
                $service_url = "https://xsolla.local/getfile?filename=art.png";
                $curl = curl_init($service_url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HEADER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($curl, CURLOPT_HTTPHEADER, getCurlHeaders($params['register_apikey']));
                $curl_response = curl_exec($curl);
                $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                $headers = nl2br(rtrim(substr($curl_response, 0, $header_size)));
                $resp_data = substr($curl_response, $header_size);

                // http://stackoverflow.com/questions/10975775/how-to-determine-if-a-string-was-compressed
                if (0 === mb_strpos($resp_data , "\x1f" . "\x8b" . "\x08")) // if it was compressed -> uncompress
                {
                    $resp_data = gzdecode($resp_data);
                }

                $http_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                curl_close($curl);

                $params['getfile_response_headers'] = $headers;
                $params['getfile_data'] = $resp_data; //raw file content
                $params['getfile_code'] = $http_status;
                //for test unit
                $params['getfile_data_base64'] = 'data:image/png;base64,' . base64_encode($params['getfile_data']);
            }

            // get file list
            $service_url = "https://xsolla.local/getfilelist";
            $curl = curl_init($service_url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HEADER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            $pcurl = getCurlHeaders($params['register_apikey']);
            curl_setopt($curl, CURLOPT_HTTPHEADER, $pcurl);

            $curl_response = curl_exec($curl);
            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $headers = nl2br(rtrim(substr($curl_response, 0, $header_size)));
            $json = substr($curl_response, $header_size);

            // http://stackoverflow.com/questions/10975775/how-to-determine-if-a-string-was-compressed
            if (0 === mb_strpos($json, "\x1f" . "\x8b" . "\x08")) // if it was compressed -> uncompress
            {
                $json = gzdecode($json);
            }

            curl_close($curl);

            $params['getfilelist_response_headers'] = $headers;
            $params['getfilelist_json'] = $json;
            $params['getfilelist_code'] = json_decode($json)->code;

            // get file meta (file art.jpg)
            if ($params['upload2_code'] === 200) {
                $service_url = "https://xsolla.local/getfilemetadata?filename=art.jpg";
                $curl = curl_init($service_url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HEADER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                $pcurl = getCurlHeaders($params['register_apikey']);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $pcurl);

                $curl_response = curl_exec($curl);
                $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                $headers = nl2br(rtrim(substr($curl_response, 0, $header_size)));
                $json = substr($curl_response, $header_size);

                // http://stackoverflow.com/questions/10975775/how-to-determine-if-a-string-was-compressed
                if (0 === mb_strpos($json, "\x1f" . "\x8b" . "\x08")) // if it was compressed -> uncompress
                {
                    $json = gzdecode($json);
                }

                curl_close($curl);

                $params['getfilemeta_response_headers'] = $headers;
                $params['getfilemeta_json'] = $json;
                $params['getfilemeta_code'] = json_decode($json)->code;
            }

            // metadata for art.png
            if ($params['upload2_code'] === 200) {
                $service_url = "https://xsolla.local/getfilemetadata?filename=art.png";
                $curl = curl_init($service_url);
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_HEADER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                $pcurl = getCurlHeaders($params['register_apikey']);
                curl_setopt($curl, CURLOPT_HTTPHEADER, $pcurl);;

                $curl_response = curl_exec($curl);
                $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                $headers = nl2br(rtrim(substr($curl_response, 0, $header_size)));
                $json = substr($curl_response, $header_size);

                // http://stackoverflow.com/questions/10975775/how-to-determine-if-a-string-was-compressed
                if (0 === mb_strpos($json, "\x1f" . "\x8b" . "\x08")) // if it was compressed -> uncompress
                {
                    $json = gzdecode($json);
                }

                curl_close($curl);

                $params['metadata_response_headers'] = $headers;
                $params['metadata_json'] = $json;
                $params['metadata_code'] = json_decode($json)->code;
            }
        }

        return $this->view->render($response, 'test.twig', $params);
});

$app->run();

?>