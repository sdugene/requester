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
class Pdo {
 
    /**
     * @var Singleton
     * @access private
     * @static
     */
    private static $_instance = null;
    private static $_bdd = null;
 
    /**
     * Constructeur de la classe
     *
     * @param void
     * @return void
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
            die('Erreur : '.$e->getMessage());
        }
    }
 
    /**
     * Méthode qui crée l'unique instance de la classe
     * si elle n'existe pas encore puis la retourne.
     *
     * @param void
     * @return Singleton
     */
    public static function getInstance()
    {
        if(is_null(self::$_instance)) {
            self::$_instance = new Pdo();  
        }
        return self::$_instance;
    }
 
    /**
     * Méthode qui crée l'unique instance de la connexion
     * si elle n'existe pas encore puis la retourne.
     *
     * @param void
     * @return Singleton
     */
    public static function getBdd()
    {
        if(is_null(self::$_bdd)) {
            self::$_bdd = self::connexion();
            \Engine\Functions\Functions::print2log('connexion');
        }
        return self::$_bdd;
    }
}