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
    
    private $dbUser = null;
    private $dbPassword = null;
    private $dbHost = null;
    private $dbName = null;

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
        $request = is_object($class) ? new Request($class) : '{"ERROR":"$class must be an object"}';
    	if ($this->dbUser != null) {
    		$request->setDatabase($this->dbUser, $this->dbPassword, $this->dbHost, $this->dbName);
    	}
    	
    	return $request;
    }

    /**
     * @param $user
     * @param $password
     * @param $host
     * @param $database
     * @return object EntityManager
     */
	public function setDatabase($user = MYSQL_USER, $password = MYSQL_PWD, $host = MYSQL_HOST, $database = MYSQL_DB) {
		$this->dbUser = $user;
		$this->dbPassword = $password;
		$this->dbHost = $host;
		$this->dbName = $database;
		return $this;
	}
}