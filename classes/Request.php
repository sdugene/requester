<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Requester;

/**
 * Description of Request
 *
 * @author Sébastien Dugène
 */
class Request extends Sql
{	
    protected $tableName;
    protected $forbiden;

    public function __construct($input)
    {
        if (is_numeric($input)) {
            $result = $this->findById($input) ;
        } elseif (is_array($input)) {
            $result = $this->findByCriteria($input, 1) ;
        }
        $this->fill($result) ;
    }
    
    
    public function set($name, $value)
    {
        if (!in_array($name, $this->forbiden)){
            $this->$name = $value;
            if (isset($this->id)) {
                $input = [$name => $value];
                $criteria = ['id' => $this->id];
                $this->update($input, $criteria);
            }
        } else {
            trigger_error($this->tableName." - Set method forbiden on '".$name."' attribute", E_USER_ERROR);
        }
    }
    
    
    public function addRandId()
    {
        $this->randId = md5(uniqid());
    }


    public function extend($extenser = ['all'])
    {
        if ($extenser[0] === 'all') {
            $extenser = $this->extenser;
        }
        if (is_array($extenser) && $extenser[0] !== 'all') {
            foreach($extenser as $value) {
                $class = '\Engine\\'.$value;
                $child = new $class($this->id);
                $className = lcfirst($value);
                $this->$className = $child->getPublic();
            }
        } else {
            return false ;
        }
    }


    public function findById($id)
    {
        return $this->query("WHERE id = '".$id."'", 1);
    }


    public function findByCriteria($criteria = [], $maxLine = false, $order = false)
    {
        $sql = $this->criteria($criteria);
        $orderQuery = $this->order($order);
        return $this->query($sql.$orderQuery, $maxLine);
    }


    public function findWithJoin($criteria = [], $join = [], $maxLine = false, $order = false)
    {
        if (empty($join)) {
            return findByCriteria($criteria, $maxLine, $order);
        }
        
        $sql = $this->criteria($criteria);
        $sql .= $this->join($join);
        $orderQuery = $this->order($order);
        
        return $this->query($sql.$orderQuery, $maxLine);
    }


    public function hydrate($input = [], $maxLine = 1, $order = false)
    {
        if (is_numeric($input)) {
            $result = $this->findById($input) ;
        } elseif (is_array($input)) {
            $result = $this->findByCriteria($input, $maxLine, $order) ;
        }
        $this->fill($result) ;         
    }


    public function insert($inputs) {
        $columns = '';
        $values = '';
        foreach ($inputs as $key => $value) {
            if ($columns !== '') {
                $columns .= ', ';
            }
            if ($values !== '') {
                $values .= ', ';
            }
            $columns .= addslashes($key);

            $mysqlFunction = str_replace('mysql#','',$value);
            if ($mysqlFunction != $value) {
                $values .= $mysqlFunction;
            } else {
                $values .= "'".addslashes($value)."'";
            }
        }
        $query = "INSERT INTO ".$this->tableName." (".$columns.") VALUES (".$values.")" ;
        return $this->queryPDO($query);
    }


    public function update($inputs, $criteria) {
        $values = '';
        foreach ($inputs as $key => $value) {
            if ($values !== '') {
                $values .= ', ';
            }

            $mysqlFunction = str_replace('mysql#','',$value);
            if ($mysqlFunction != $value) {
                $values .= addslashes($key)." = ".$mysqlFunction;
            } else {
                $values .= addslashes($key)." = '".addslashes($value)."'";
            }
        }

        $where = $this->criteria($criteria);
        $query = "UPDATE ".$this->tableName." SET ".$values." ".$where ;
        $sql = $this->queryPDO($query);
        $sql->closeCursor();
    }


    public function delete($criteria) {
        $where = $this->criteria($criteria);
        $query = "DELETE FROM ".$this->tableName." ".$where." LIMIT 1"  ;
        $sql = $this->queryPDO($query);
        $sql->closeCursor();
    }


    public function count($input = 'id', $criteria = []) {
        $sqlPart = $this->criteria($criteria);
        $query = "SELECT count(".$input.") as count FROM ".$this->tableName.$sqlPart ;
        $sql = $this->queryPDO($query);

        $result = $sql->fetch(\PDO::FETCH_ASSOC);
        return $result['count'];
    }
}