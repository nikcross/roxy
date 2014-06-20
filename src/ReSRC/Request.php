<?php

namespace ReSRC;

class Request {

    protected $ignoreList = array();
    protected $params = array();

    public function __construct($params) {
        $this->params = $params;

        /**
         * most browsers will optimisticly request a favicon; rather than bother
         * trying to route it we'll just ignore it and bail early.
         */
        $this->ignoreList = array(
            "/favicon.ico"
        );
    }

    public function getUri() {
        return $this->params["REQUEST_URI"];
    }

    public function shouldIgnore() {
        return in_array($this->getUri(), $this->ignoreList);
    }

    public function getHeaders() {
        $headers = array();
        foreach ($this->params as $key => $value) {
            if (substr($key, 0, 5) !== "HTTP_") {
                continue;
            }
            // whip off the HTTP_ prefix
            $key = substr($key, 5);
            // convert HEADER_NAME to Header Name
            $key = ucwords(strtolower(str_replace("_", " ", $key)));
            // convert Header Name to Header-Name
            $key = str_replace(" ", "-", $key);

            $headers[$key] = $value;
        }
        return $headers;
    }
}
