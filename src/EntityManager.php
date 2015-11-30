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

    /**
     * @param $user
     * @param $password
     * @param $host
     * @param $database
     * @return void
     */
	public function setDatabase($user = MYSQL_USER, $password = MYSQL_PWD, $host = MYSQL_HOST, $database = MYSQL_DB) {
		Pdo::getBdd($user, $password, $host, $database);
	}
}