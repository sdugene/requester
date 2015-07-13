<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Requester;

/**
 * Description of EntityManager
 *
 * @author Sébastien Dugene
 */
class EntityManager
{
    private static $_instance = null;
    
    
    private function __construct() {
        // I must be empty
    }
    
    
    public static function getManager()
    {
        if(is_null(self::$_instance)) {
            self::$_instance = new EntityManager();
        }
        return self::$_instance;
    }
    
    
    /// METHODS
    public function mappingGetValue($entity, $string)
    {
        $request = new Request($entity);
        return $request->getMapping()->getValue($string);
    }
    
    
    public function entity(object $class)
    {
        if (is_object($class)) {
            return new Request($class);
        } else {
            return '{"ERROR":"$class must be an object"}';
        }
    }
}