<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Requester;

/**
 * Description of Sql
 *
 * @author Sébastien Dugène
 */
abstract class Sql
{
    protected $tableName;
    protected $forbiden;
    
    
    protected function connexionPDO() {
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
    
    
    protected function criteria($criteria, $operator = 'AND', $sql = '')
    {
        foreach ($criteria as $key => $value) {
            if ($sql !== '') {
                $sql .= ' '.$operator.' ';
            }
            if ($this->findOperator($key, $value)){
                $sql .= $this->findOperator($key, $value);
            } elseif (is_array($value)){
                $value = $this->criteria($value, $key);
                $sql .= '(' . $value . ')';
            } elseif (is_numeric($value) && $key == 'id') {
                $sql .= addslashes($key)." = ".$value;
            } else {
                $sql .= addslashes($key)." = '".addslashes($value)."'";
            }
        }

        if (!empty($sql)) {
            return "WHERE ".$sql;
        } else {
            return false;
        }
    }


    protected function fill($result = [])
    {
        foreach ($result as $key => $value) {
            if (property_exists($this, $key)) {
                $reflector = new \ReflectionClass(get_class($this));

                $prop = $reflector->getProperty($key);
                if (!$prop->isPrivate()) {
                    $this->$key = $value;
                }
            } else {
                $this->$key = $value;
            }
        }
    }
    
    
    private function findOperator($key, $value)
    {
        $operators = ['>', '>=', '<', '<=', '!='];
        $arrayOperators = ['IN', 'NOT IN'];
        
        if (is_array($value) && in_array($key, $operators)) {
            return addslashes($value[0]) . " " . $key . " '".addslashes($value[1])."'";
        }
        elseif (is_array($value) && in_array($key, $arrayOperators)) {
            return addslashes($value[0]) . " " . $key . " ".addslashes($value[1]);
        }
        
        return false;
    }


    protected function getPublic()
    {
        $publics = array();
        foreach ($this as $key => $value) {
            if (property_exists($this, $key)) {
                $reflector = new \ReflectionClass(get_class($this));

                $prop = $reflector->getProperty($key);
                if (!$prop->isPrivate() && !$prop->isProtected()) {
                    $publics[$key] = $value;
                }
            } else {
                $publics[$key] = $value;
            }
        }
        return $publics;
    }
    
    
    protected function join($join)
    {
        $sql = '';
        foreach ($join as $method => $array) {
            $sql .= strtoupper($method).' JOIN '.$array['table'];
            $sql .= $this->joinCriteria($array['criteria'], $array['table']);
        }
        return $sql;
    }
    
    
    private function joinCriteria($criteria, $table)
    {
        $sql = '' ;
        foreach ($criteria as $key => $value) {
            $json = json_encode($value);
            $search = ['#parent#','#table#'];
            $replace = [$this->tableName, $table];
            
            $jsonFinal = str_replace($search, $replace, $json);
            $value = json_decode($jsonFinal, true);
            foreach ($value as $operator => $data) {
                $sql .= ' '.$key.' '.$this->findOperator($operator, $data);
            }
        }
        return $sql;
    }
    
    
    protected function order($order)
    {
        $orderQuery = '';
        if ($order !== false) {
            $orderList = '';
            foreach($order as $key => $value) {
                if ($orderList !== '') {
                    $orderList .= ', ';
                }
                $orderList .= $key.' '.strtoupper($value);
            }
            $orderQuery = ' ORDER BY '.trim($orderList);
        }
        return $orderQuery;
    }


    protected function query($query, $maxLine = false)
    {
        $limit = '' ;
        if($maxLine !== false && is_numeric($maxLine)){
            $limit = " LIMIT " . $maxLine ;
        }
        $sql = $this->queryPDO("SELECT * FROM ".$this->tableName." ".$query.$limit);

        if ($sql->rowCount() > 0) {
            if ($maxLine === 1) {
                $aResult = $sql->fetchAll(\PDO::FETCH_ASSOC);
                $sql->closeCursor();
                return $aResult[0];
            } else {
                $class = 'Engine\\'.ucfirst($this->tableName);
                $aResult = $sql->fetchAll(\PDO::FETCH_CLASS, $class);
                $sql->closeCursor();
                return $aResult;
            }
        } else {
            return [];
        }
    }


    protected function queryPDO($query) {
        $bdd = $this->connexionPDO() ;
        $sql = $bdd->prepare($query, [\PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL]);

        $fd = fopen( ROOT . "debug/sql.log", "a+");

        if (!$sql) {
            $error = $bdd->errorInfo();
            fwrite($fd, date('Y-m-d H:i:s').' - '.$query.' : '.$error[2]."\n");
        } else {
            $sql->execute();
            fwrite($fd, date('Y-m-d H:i:s').' - '.$query.' - '.$sql->rowCount().' ligne(s) affectée(s)'."\n");
            if (preg_match("/INSERT/i", $query)) {
                return $bdd->lastInsertId();
                $sql->closeCursor();
            } else {
                return $sql;	
            }
        }
        fclose($fd);
    }
}