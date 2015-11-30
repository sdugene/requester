<?php

namespace Requester;

/**
 * Description of Pdo
 *
 * @author Sébastien Dugène
 */
class Pdo
{
    private static $_bdd = null;

    /**
     * @return void
     */
    private function __construct(){
        // I must be empty
    }

    /**
     * @return \PDO
     */
    private static function connexion($user, $password, $host, $database)
    {
        try {
            $bdd = new \PDO('mysql:host='.$host.';dbname='.$database, $user, $password);
            $bdd->exec('SET CHARACTER SET UTF8');
            $bdd->setAttribute(\PDO::ATTR_EMULATE_PREPARES,false);
            return $bdd ;
        } catch(Exception $e) {
            trigger_error('PDO ERROR : '.$e->getMessage(), E_USER_ERROR);
        }
    }

    /**
     * @return null|\PDO
     */
    public static function getBdd($user = MYSQL_USER, $password = MYSQL_PWD, $host = MYSQL_HOST, $database = MYSQL_DB)
    {
        if(is_null(self::$_bdd)) {
            self::$_bdd = self::connexion($user, $password, $host, $database);
        }
        return self::$_bdd;
    }
}