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
                if (!array_key_exists($key, $this->properties)) {
                    $sql .= $key." = ".$value;
                } else {
                    $sql .= $this->tableName.'.'.$this->properties[$key]." = ".$value;
                }
            } elseif ($value == 'NULL') {
                if (!array_key_exists($key, $this->properties)) {
                    $sql .= $key." IS NULL";
                } else {
                    $sql .= $this->tableName.'.'.$this->properties[$key]." IS NULL";
                }
            } else {
                if (!array_key_exists($key, $this->properties)) {
                    $sql .= $key." = '".addslashes($value)."'";
                } else {
                    $sql .= $this->tableName.'.'.$this->properties[$key]." = '".addslashes($value)."'";
                }
            }
        }

        if (!empty($sql)) {
            return "WHERE ".$sql;
        } else {
            return false;
        }
    }


    protected function fill($result = [], $entity = false)
    {
        if ($entity === false) {
            $entity = $this->entity;
        }
        foreach ($result as $key => $value) {
            $columns = array_flip($this->properties);
            if (!array_key_exists($key, $columns)) {
                $entity->$key = $value;
            } elseif (property_exists($this->entity, $columns[$key])) {
                $prop = $this->reflectionClass->getProperty($columns[$key]);
                if (!$prop->isPrivate()) {
                    $entity->$columns[$key] = $value;
                }
            } else {
                $entity->$columns[$key] = $value;
            }
        }
        return $entity;
    }
    
    
    private function findOperator($key, $value)
    {
        $operators = ['=', '>', '>=', '<', '<=', '!=', 'LIKE'];
        $arrayOperators = ['IN', 'NOT IN', 'IS'];
        
        if (is_array($value) && in_array($key, $operators)) {
            return $this->tableName.'.'.$this->properties[$value[0]]. " ".$key. " '".addslashes($value[1])."'";
        }
        elseif (is_array($value) && in_array($key, $arrayOperators)) {
            return $this->tableName.'.'.$this->properties[$value[0]]. " " .$key. " ".addslashes($value[1]);
        }
        
        return false;
    }
    
    
    private function getPrefixedColumn($tableName)
    {
        $name = $this->mapping->getName($tableName);
        
        $query = "DESCRIBE ".$name ;
        $sql = $this->queryPDO($query);
        $tableFields = $sql->fetchAll(\PDO::FETCH_COLUMN);
        $sql->closeCursor();
        
        foreach ($tableFields as $key => $value) {
            $tableFields[$key] = $name.'.'.$value.' as '.str_replace('@', '',$tableName).'_'.$value;
        }
        return implode(', ', $tableFields);
    }


    protected function getPublic()
    {
        $publics = array();
        foreach ($this as $key => $value) {
            if (property_exists($this, $key)) {
                $prop = $this->reflectionClass->getProperty($key);
                if (!$prop->isPrivate() && !$prop->isProtected()) {
                    $publics[$key] = $value;
                }
            } else {
                $publics[$key] = $value;
            }
        }
        return $publics;
    }
    
    
    protected function group($group)
    {
        $groupQuery = '';
        if ($group !== false) {
            $groupList = '';
            foreach($group as $key => $value) {
                if ($groupList !== '') {
                    $groupList .= ', ';
                }
                $groupList .= $this->properties[$key].' '.strtoupper($value);
            }
            $groupQuery = ' GROUP BY '.trim($groupList);
        }
        return $groupQuery;
    }
    
    
    protected function join($join)
    {
        $sql = '';
        foreach ($join as $method => $join) {
            $table = key($join);
            $column = $this->getPrefixedColumn($table);
            $sql .= strtoupper($method).' JOIN '.$this->mapping->getName($table);
            $sql .= $this->joinCriteria($join[$table], $table);
        }
        return [
            'sql' => $sql,
            'column' => $column
        ];
    }
    
    
    private function joinCriteria($criteria, $table)
    {
        $sql = '' ;
        foreach ($criteria as $boolean => $column) {
            if(!is_array($column)) {
                $joinMapping = $this->mapping->getPropertieJoinColumn($column, $table);
            } else {
                $joinMapping = ['@'.$table.'.@'.key($column) => current($column)];
                var_dump($joinMapping);
            }
            foreach ($joinMapping as $key => $value) {
                if ($this->findOperator($key, $value)) {
                    $sql .= ' '.$boolean.' '.$this->findOperator($key, $value);
                } else {
                    $sql .= ' '.$boolean.' '.$this->mapping->valueMapping($key)." = ".$this->mapping->valueMapping($value);
                }
            }
        }
        return $sql;
    }
    
    
    private function multiFill($results) {
        $array = [];
        foreach($results as $key => $value) {
            $array[$key] = $this->fill($value, new $this->class());
        }
        return $array;
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
                $orderList .= $this->properties[$key].' '.strtoupper($value);
            }
            $orderQuery = ' ORDER BY '.trim($orderList);
        }
        return $orderQuery;
    }


    protected function query($query, $maxLine = false, $column = '*')
    {
        $limit = '' ;
        if($maxLine !== false && is_numeric($maxLine)){
            $limit = " LIMIT " . $maxLine ;
        }
        $sql = $this->queryPDO("SELECT ".$column." FROM ".$this->tableName." ".$query.$limit);

        if ($sql->rowCount() > 0) {
            if ($maxLine === 1) {
                $results = $sql->fetchAll(\PDO::FETCH_ASSOC);
                $this->fill($results[0], $this->entity);
                $sql->closeCursor();
                return $this->entity;
            } else {
                $results = $this->multiFill($sql->fetchAll(\PDO::FETCH_ASSOC));
                $sql->closeCursor();
                return $results;
            }
        } else {
            return [];
        }
    }


    protected function queryPDO($query) {
        $bdd = Pdo::getBdd();
        $sql = $bdd->prepare($query, [\PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL]);

        $fd = fopen( ROOT . "debug/sql.log", "a+");

        if (!$sql) {
            $error = $bdd->errorInfo();
            fwrite($fd, date('Y-m-d H:i:s').' - '.$query.' : '.$error[2]."\n");
        } else {
            $sql->execute();
            fwrite($fd, date('Y-m-d H:i:s').' - '.$query.' - '.$sql->rowCount().' ligne(s) affectée(s)'."\n");
            if (preg_match("/INSERT/i", $query)) {
                $sql->closeCursor();
                return $bdd->lastInsertId();
            } elseif (preg_match("/DELETE|UPDATE/i", $query)) {
                $count = $sql->rowCount();
                $sql->closeCursor();
                return $count;
            } else {
                return $sql;
            }
        }
        fclose($fd);
    }
}