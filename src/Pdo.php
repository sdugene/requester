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
    private static function connexion()
    {
        $login = MYSQL_USER;
        $pwd = MYSQL_PWD;
        $host = MYSQL_HOST;
        $base = MYSQL_DB;

        try {
            $bdd = new \PDO('mysql:host='.$host.';dbname='.$base, $login, $pwd);
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
    public static function getBdd()
    {
        if(is_null(self::$_bdd)) {
            self::$_bdd = self::connexion();
        }
        return self::$_bdd;
    }
}