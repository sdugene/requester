<?php
/**
 *  Autoloader
 */

namespace Requester;

function autoload($folder)
{
    if (is_array($folder)) {
        foreach ($folder as $value) {
            requireAll(__DIR__ . $value);
        }
    } else {
       requireAll(__DIR__ . $folder);
    }
}

function requireAll($folder)
{
    foreach (scandir(dirname($folder)) as $filename) {
        $path = dirname($folder) . '/' . $filename;
        if (is_file($path)) {
            require_once $path;
        }
    }
}

autoload('classes');