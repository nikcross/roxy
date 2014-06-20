<?php

namespace ReSRC;

class Image {
    protected $params = array();
    protected $meta = array();
    protected $imageData = null;
    protected $path = null;

    public function __construct(array $params) {
        $this->params = $params;
    }

    public function getTargetPath() {
        if ($this->getType() === 'remote') {
            return $this->params['protocol'] . $this->params['src'];
        }
        return $this->params['src'];
    }

    public function getType() {
        if ($this->params['protocol'] === 'file://') {
            return 'local';
        }
        return 'remote';
    }

    public function hasFullPath() {
        return $this->path !== null;
    }

    public function getFullPath() {
        return $this->path;
    }

    public function setFullPath($path) {
        $this->path = $path;
    }

    public function setMetaData(array $meta) {
        $this->imageData = $meta['data'];

        unset($meta['data']);
        $this->meta = $meta;
    }

    public function getParams() {
        return $this->params;
    }

    public function getMeta() {
        return $this->meta;
    }

    public function getImageData() {
        return $this->imageData;
    }
}
