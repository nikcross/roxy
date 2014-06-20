<?php
// fairly standard autoloader, substituting namespaces for folder paths
spl_autoload_register(function($class) {
    $path = __DIR__ . '/' . str_replace('\\', '/', $class) . '.php';
    require_once($path);
});
