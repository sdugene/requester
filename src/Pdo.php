<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Requester;

/**
 * Description of Pdo
 *
 * @author Sébastien Dugène
 */
class Pdo
{
    private static $_bdd = null;
    
    private function __construct(){
        // I must be empty
    }
    
    
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
    
    
    public static function getBdd()
    {
        if(is_null(self::$_bdd)) {
            self::$_bdd = self::connexion();
        }
        return self::$_bdd;
    }
}