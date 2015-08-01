<?php

namespace Requester;

/**
 * Description of EntityManager
 *
 * @author Sébastien Dugene
 */
class EntityManager
{
    private static $_instance = null;

    /**
     * @return void
     */
    private function __construct() {
        // I must be empty
    }

    /**
     * @return EntityManager
     */
    public static function getManager()
    {
        if(is_null(self::$_instance)) {
            self::$_instance = new EntityManager();
        }
        return self::$_instance;
    }

    /// METHODS
    /**
     * @param $entity
     * @param $string
     * @return string
     */
    public function mappingGetValue($entity, $string)
    {
        $request = new Request($entity);
        $table = $request->getClassName();
        return $request->getMapping()->getName($table) . '.' . $request->getMapping()->getValue($string);
    }

    /**
     * @param $class
     * @return Request|string
     */
    public function entity($class)
    {
        return is_object($class) ? new Request($class) : '{"ERROR":"$class must be an object"}';
    }
}