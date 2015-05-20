<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Requester;

/**
 * Autoloader
 * To include if you don't use composer autoloader 
 */
function autoload($class)
{
    if (preg_match('/'.__NAMESPACE__.'\\\/', $class)){
        require_once __DIR__ . '/' . str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
    }
}

function checkConstants($constants)
{
    foreach($constants as $constant => $value) {
        if (!defined($constant)) {
            define($constant, $value);
        }
    }
}

function load($folder)
{
    if (is_array($folder)) {
        foreach ($folder as $value) {
            requireAll(__DIR__ . '/' . $value);
        }
    } else {
       requireAll(__DIR__ . '/' . $folder);
    }
}

function requireAll($folder)
{
    foreach (scandir($folder) as $filename) {
        $path = $folder . '/' . $filename;
        if (is_file($path)) {
            require_once $path;
        }
    }
}

$neededConstants = [
    "MYSQL_USER" => 'root',
    "MYSQL_PWD" => 'root',
    "MYSQL_HOST" => 'localhost',
    "MYSQL_DB" => 'database',
    "MODELS_NAMESPACE" => '\Models\\'
];

checkConstants($neededConstants);

spl_autoload_register('Requester\autoload');
load('Requester');