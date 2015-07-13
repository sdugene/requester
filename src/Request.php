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
    protected $reflectionClass = null;
    protected $properties = null;
    protected $tableName = null;
    protected $forbiden = null;
    protected $mapping = null;
    protected $entity = null;
    protected $class = null;

    
    public function __construct($entity)
    {
        $this->entity = $entity;
        $this->class = get_class($this->entity);
        $this->getClassAnnotations();
        $this->reflectionClass = new \ReflectionClass($this->class);
    }
    
    /*public function __construct($input = '')
    {
        $this->getClassAnnotations();
        $this->reflectionClass = new \ReflectionClass(get_called_class());
        if ($input !== '') {
            if (is_numeric($input)) {
                $result = $this->findById($input) ;
            } elseif (is_array($input)) {
                $result = $this->findByCriteria($input, 1) ;
            }

            if ($result) {
                $this->fill($result) ;
            }
        }
    }*/


    public function count($input = 'id', $criteria = []) {
        $sqlPart = $this->criteria($criteria);
        $query = "SELECT count(".$input.") as count FROM ".$this->tableName.$sqlPart ;
        $sql = $this->queryPDO($query);

        $result = $sql->fetch(\PDO::FETCH_ASSOC);
        return $result['count'];
    }


    public function delete($criteria) {
        $where = $this->criteria($criteria);
        $query = "DELETE FROM ".$this->tableName." ".$where." LIMIT 1"  ;
        $sql = $this->queryPDO($query);
        $sql->closeCursor();
    }


    public function findById($id)
    {
        $criteria = [
            'id' => $id
        ];
        
        return $this->findByCriteria($criteria, 1);
    }


    public function findByCriteria($criteria = [], $maxLine = false, $order = false, $group = false)
    {
        $sql = $this->criteria($criteria);
        $orderQuery = $this->order($order);
        $groupQuery = $this->group($group);
        
        return $this->query($sql.$groupQuery.$orderQuery, $maxLine);
    }


    public function findWithJoin($criteria = [], $join = [], $maxLine = false, $order = false, $group = false)
    {
        if (empty($join)) {
            return findByCriteria($criteria, $maxLine, $order, $group);
        }
        $jointer = $this->join($join);
        $sql = $jointer['sql'];
        $sql .= ' '.$this->criteria($criteria);
        $orderQuery = $this->order($order);
        $groupQuery = $this->group($group);
        
        return $this->query($sql.$groupQuery.$orderQuery, $maxLine, $this->tableName.'.*, '.$jointer['column']);
    }
	
	
    private function getClassAnnotations()
    {
        $this->mapping = Mapping::getReader($this->class);
        $this->tableName = $this->mapping->getClassMapping('Table')->name;
        $this->forbiden = $this->mapping->getClassMapping('Forbiden')->columns;
        $this->properties = $this->mapping->getPropertiesMapping();
    }
    
    
    public function getClassName()
    {
        return $this->reflectionClass->getShortName();
    }
    
    
    public function getMapping()
    {
        return $this->mapping;
    }


    public function hydrate($criteria = [], $maxLine = 1, $order = false, $group = false)
    {
        if (is_numeric($criteria)) {
            $this->findById($criteria) ;
        } elseif (is_array($criteria)) {
            $this->findByCriteria($criteria, $maxLine, $order, $group) ;
        }
        return $this->entity;
    }


    public function insert($inputs)
    {
        $columns = '';
        $values = '';
        foreach ($inputs as $key => $value) {
            if ($columns !== '') {
                $columns .= ', ';
            }
            if ($values !== '') {
                $values .= ', ';
            }
            $columns .= addslashes($this->properties[$key]);

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
    
    
    public function set($name, $value)
    {
        if (!in_array($name, $this->forbiden)){
            $this->entity->$name = $value;
            if (isset($this->entity->id)) {
                $input = [$this->properties[$name] => $value];
                $criteria = ['id' => $this->entity->id];
                $this->update($input, $criteria);
            }
        } else {
            trigger_error($this->tableName." - Set method forbiden on '".$name."' attribute", E_USER_ERROR);
        }
    }


    public function update($inputs, $criteria)
    {
        $values = '';
        foreach ($inputs as $key => $value) {
            if ($values !== '') {
                $values .= ', ';
            }

            $mysqlFunction = str_replace('mysql#','',$value);
            if ($mysqlFunction != $value) {
                $values .= addslashes($this->properties[$key])." = ".$mysqlFunction;
            } else {
                $values .= addslashes($this->properties[$key])." = '".addslashes($value)."'";
            }
        }

        $where = $this->criteria($criteria);
        $query = "UPDATE ".$this->tableName." SET ".$values." ".$where ;
        $sql = $this->queryPDO($query);
        $sql->closeCursor();
    }
}