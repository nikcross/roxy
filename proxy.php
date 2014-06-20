<?php

require("src/bootstrap.php");

use ReSRC\ReSRC;

// always convert any errors to exceptions so we can catch them properly...
set_error_handler(function($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
});
// ... and make sure we hear about all errors too
error_reporting(-1);

$resrc = new ReSRC(array(
    // because we run the SDK via a wrapper script we expect the appropriate
    // env var to exist
    'token' => $_ENV["API_TOKEN"],
    'debug' => false,
));

$request = new \ReSRC\Request($_SERVER);

if ($request->shouldIgnore()) {
    exit(0);
}

try {
    $timezone = date_default_timezone_get();
} catch (\Exception $e) {
    $timezone = "";
}

if ($timezone === "") {
    date_default_timezone_set("Europe/London");
    $resrc->log("WARNING: Your PHP configuration does not have a default timezone set");
    $resrc->log("We have assumed 'Europe/London' but you are encouraged to change this");
}

try {
    // the first thing we try and do is parse the input string we get. This throws
    // an exception if the string doesn't look valid, otherwise it returns an array
    // of parameters we can send to the ReSRC.it API
    $params = $resrc->getRequestParams($request);

    // based on those parameters we fetch an actual resource object, either from
    // the local filesystem or via http
    $image = $resrc->readImage($params);

    // now we check our actual local cache; if we've got it then great, if not, we hit the API
    if (!$resrc->hasCachedImage($image)) {
        // ping off to resrc.it API
        $response = $resrc->processImage($image);
    } else {
        $response = $resrc->fetchCachedImage($image);
    }

    // however we got here now we've got a valid HTTP response, so based
    // on the inbound request work out what reply to send (200, 304 etc)
    $resrc->outputResponse($request, $response);

} catch (\ReSRC\Exception $e) {
    die("[".get_class($e)."]: ".$e->getMessage()."\n");
} catch (ErrorException $e) {
    die("PHP Exception: ".$e->getMessage()." (".$e->getFile()." line ".$e->getLine().")");
}
