<?php

namespace ReSRC;

class ReSRC {
    protected $apiToken;
    protected $cacheDir;
    protected $host;
    protected $path = '/direct';
    protected $protocol;
    protected $debug;
    protected $silent;

    public function __construct($options = array()) {

        if (!isset($options['token'])) {
            throw new Exception('API token required');
        }

        $this->apiToken = $options['token'];
        $this->protocol = isset($options['protocol']) ? $options['protocol'] : 'https';
        $this->host     = isset($options['host']) ? $options['host'] : 'app.resrc.it';
        $this->cacheDir = isset($options['cacheDir']) ? $options['cacheDir'] : realpath(__DIR__ . "/../../cache");
        $this->debug    = isset($options['debug']) ? $options['debug'] : false;
        $this->silent   = isset($options['silent']) ? $options['silent'] : false;
    }

    public function processImage(Image $image) {
        // first of all send a preflight to the ReSRC.it API. This essentially
        // bundles up all our parameters minus actual image data, giving the API
        // a chance to return us a cached image without wasting bandwidth
        $response = $this->getPreflightRequest($this->host, $image)->execute();

        $this->log("received preflight response, status code [".$response->getStatusCode()."]");

        if ($response->getStatusCode() === 404) {
            $this->log("preflight cache miss, issuing full request to [".$response->getHeader("Host")."]...");

            $response = $this->getFullRequest($response->getHeader("Host"), $image)->execute();

            $this->log("received full response, status code [".$response->getStatusCode()."]");
        }

        if ($response->getStatusCode() === 200) {
            $this->log("Received 200 OK, checking cache headers");
            if (strpos($response->getHeader("Cache-Control"), "max-age=0") === false) {
                $this->log("Response is cacheable, writing to disk...");
                // excellent; save the image to disk
                $this->storeImageResponse($image, $response);
            }
        } else {
            $message = "Non 200 response code received from API: ".$response->getStatusCode();
            $this->log($message);
        }

        return $response;
    }

    protected function getRequest($host, Image $image, $isFullRequest) {
        $params = $image->getParams();
        $meta   = $image->getMeta();

        $params = array(
            "Parameters"    => $params['parameters'],
            "LastModified"  => $meta['Last-Modified'],
            "ContentType"   => $meta['Content-Type'],
            "ContentLength" => $meta['Content-Length'],
            "RemotePath"    => $image->getTargetPath(),
        );

        if ($isFullRequest) {
            $params["Image"] = $image->getImageData();
        }

        $request = new Curl\Request;
        if ($this->debug) {
            $request->setOpt(CURLOPT_VERBOSE, true);
        }

        $url = $this->protocol . '://' . $host . $this->path;

        $request->setMethod("POST")
                ->setHeader("Authorization", "Basic ".$this->apiToken)
                ->setParams($params)
                ->setUrl($url);

        if ($isFullRequest) {
            $this->log("-> sending full request to [".$url."]");
        } else {
            $this->log("-> sending preflight request to [".$url."]");
            $this->debug("-> ".var_export($params, true));
        }

        return $request;
    }

    protected function getPreflightRequest($host, $image) {
        return $this->getRequest($host, $image, false);
    }

    protected function getFullRequest($host, $image) {
        return $this->getRequest($host, $image, true);
    }

    public function hasCachedImage(Image $image) {
        $hasCache = file_exists(
            $this->getFullImagePath($image)
        );
        if (!$hasCache) {
            $this->log("no up to date local cache for image");
        } else {
            $this->log("got valid local cache entry");
        }
        return $hasCache;
    }

    public function storeImageResponse(Image $image, Curl\Response $response) {
        $this->ensureImageDirectoryExists($image);

        $path = $this->getFullImagePath($image);

        if (file_exists($path) && !is_writable($path)) {
            $this->log("cannot write to path [".$path."]");
            throw new Exception("Cache file path [".$path."] exists and is not writable");
        }

        $this->log("writing transformed image data");

        $result = file_put_contents($path, $response->getBody());

        if ($response->getHeader("Last-Modified")) {
            touch($path, strtotime($response->getHeader("Last-Modified")));
        }

        if ($result === false) {
            $this->log("could not write transformed image data");
            throw new Exception("Error when writing cache file [".$path."]");
        }
    }

    protected function ensureImageDirectoryExists(Image $image) {
        $path = $this->getFullImagePath($image);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            $this->log("cache directory [".$dir."] does not exist, creating...");
            // as much as error suppression is bad form, we need it here to prevent
            // PHP throwing a warning; we're handling the failure ourselves with an
            // exception anyway
            if (!@mkdir($dir, 0755)) {
                $this->log("could not create cache directory [".$dir."]");
                throw new Exception("Cache directory [".$dir."] is not writable");
            }
        }
    }

    public function outputResponse(Request $request, Curl\Response $response) {
        $headers = $request->getHeaders();

        if (isset($headers["If-Modified-Since"])) {

            $this->log("got request If-Modified-Since header, checking validity");

            if ($headers["If-Modified-Since"] === $response->getHeader("Last-Modified")) {
                $this->log("last modified match; returning 304");
                header("HTTP/1.1 304 Not Modified");
                return;
            }

            $this->log("Last modified does not match");
        }

        $this->log("<- outputting image data");
        $this->debug("<- ".var_export($response->getHeaders(), true));

        // always extract status as a standalone and emit that first
        header($response->getHeader("status"));

        foreach ($response->getHeaders() as $header => $value) {
            header($header . ": " . $value);
        }

        echo $response->getBody();
    }

    public function getRequestParams(Request $request) {
        $string = $request->getUri();
        $this->log("parsing input string [".$string."]");

        if (preg_match("#^((?P<parameters>.*)/)?(?P<protocol>file://|https?://)(?P<src>.+)$#", $string, $matches)) {
            // all good!
            return array(
                "parameters" => $matches["parameters"],
                "protocol"   => $matches["protocol"],
                "src"        => $matches["src"]
            );
        }

        $this->log("input string is not valid");
        throw new Exception("The input string [".$string."] does not appear to be valid");
    }

    protected function getFullImagePath(Image $image) {
        if (!$image->hasFullPath()) {
            // ask the image for all its information, including
            // any metadata like Last-Modified, since we use that
            // in determinining cache validity
            $params = $image->getParams();
            $meta   = $image->getMeta();

            $extPos    = strrpos($params['src'], '.');
            $extension = substr($params['src'], $extPos);

            $cacheParams = array(
                $params['src'],
                $params['protocol'],
                $params['parameters'],
                $meta['Last-Modified'],
            );

            $image->setFullPath(
                $this->cacheDir . "/" . sha1(implode("-", $cacheParams)) . $extension
            );
        }

        return $image->getFullPath();
    }

    public function readImage(array $params) {
        $image = new Image($params);

        // from the params provided work out how to read the image
        $path = $image->getTargetPath();

        if ($image->getType() === 'remote') {
            $this->log("reading remote path [".$path."]");
            $meta = $this->readRemotePath($path);
        } else {
            $this->log("reading local filesystem path [".$path."]");
            $meta = $this->readLocalPath($path);
        }

        $image->setMetaData($meta);

        return $image;
    }

    protected function readLocalPath($path) {
        $data = file_get_contents($path);
        $modified = gmdate('D, d M Y H:i:s', filemtime($path)).' GMT';
        $length = strlen($data);
        // @TODO ideally MimeType will take a path instead, that
        // way we can pass everything directly into toImageMeta
        $mime = $this->getMimeType($data);

        return $this->toImageMeta($data, $modified, $mime, $length);
    }

    protected function readRemotePath($path) {
        $data = file_get_contents($path);
        $modified = null;
        $mime = null;
        $length = null;

        foreach ($http_response_header as $header) {
            if (preg_match('/^Last-Modified:\s(.+)$/', $header, $matches)) {
                $modified = $matches[1];
            }
            if (preg_match('/^Content-Type:\s(.+)$/', $header, $matches)) {
                $mime = $matches[1];
            }
            if (preg_match('/^Content-Length:\s(.+)$/', $header, $matches)) {
                $length = $matches[1];
            }
        }
        return $this->toImageMeta($data, $modified, $mime, $length);
    }

    protected function getMimeType($data) {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->buffer($data);
    }

    protected function toImageMeta($data, $modified, $mime, $length) {
        return array(
            'data'           => $data,
            'Last-Modified'  => $modified,
            'Content-Type'   => $mime,
            'Content-Length' => $length,
        );
    }

    public function log($str) {
        if ($this->silent) {
            return;
        }

        $timestamp = date("d/m/Y H:i:s");
        $stdout = fopen("php://stdout", "w");
        fwrite($stdout, $timestamp." - ".$str."\n");
        fclose($stdout);
    }

    public function debug($str) {
        if ($this->debug) {
            $this->log($str);
        }
    }

    public function fetchCachedImage(Image $image) {
        // we replace the original image with the correct
        // cached equivalent, including header information
        $meta = $this->readLocalPath(
            $this->getFullImagePath($image)
        );
        $image->setMetaData($meta);
        $response = new Curl\Response();
        $response->setHeaders(array_merge(
            $image->getMeta(),
            array("status" => "HTTP/1.1 200 OK")
        ));
        $response->setBody(
            $image->getImageData()
        );
        return $response;
    }
}
