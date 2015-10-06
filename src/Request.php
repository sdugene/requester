<?php

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
    protected $forbidden = null;
    protected $mapping = null;
    protected $entity = null;
    protected $class = null;

    /**
     * @param $entity
     */
    public function __construct($entity)
    {
        $this->entity = $entity;
        $this->class = get_class($this->entity);
        $this->getClassAnnotations();
        $this->reflectionClass = new \ReflectionClass($this->class);
    }

    /**
     * @param string $column
     * @param array $criteria
     * @return mixed
     */
    public function count($column = 'id', $criteria = []) {
        $sqlPart = $this->criteria($criteria);
        $query = "SELECT count(".$this->properties[$column].") as count FROM ".$this->tableName." ".$sqlPart;
        $sql = $this->queryPDO($query);

        $result = $sql->fetch(\PDO::FETCH_ASSOC);
        return $result['count'];
    }

    /**
     * @param array $criteria
     * @return int|\PDOStatement|string
     */
    public function delete($criteria) {
        $where = $this->criteria($criteria);
        $query = "DELETE FROM ".$this->tableName." ".$where." LIMIT 1";
        return $this->queryPDO($query);
    }

    /**
     * @param int|array $id|$criteria
     * @param array|int $join|$limit
     * @param int|array $limit|$order
     * @param array $order|$group
     * @param array $group
     * @return array
     */
    public function find()
    {
        /**
         *  $args[0] : $criteria
         *  $args[1] : $join
         *  $args[2] : $limit
         *  $args[3] : $order
         *  $args[4] : $group
         */
        $args = $this->getArgs(func_get_args());
        
        if (is_numeric($args[0])) {
            return $this->findById($args[0]);
        }
        
        if (is_array($args[1]) && !empty($args[1])) {
            return $this->findWithJoin($args[0], $args[1], $args[2], $args[3], $args[4]);
        }
        
        if (is_array($args[0])) {
            return $this->findByCriteria($args[0], $args[1], $args[2], $args[3]);
        }
    }

    /**
     * @param $id
     * @return array
     */
    private function findById($id)
    {
        $criteria = [
            'id' => $id
        ];
        
        return $this->findByCriteria($criteria, 1);
    }

    /**
     * @param array $criteria
     * @param bool|false $maxLine
     * @param bool|false $order
     * @param bool|false $group
     * @return array
     */
    private function findByCriteria($criteria = [], $maxLine = false, $order = false, $group = false)
    {
        $sql = $this->criteria($criteria);
        $orderQuery = $this->order($order);
        $groupQuery = $this->group($group);
        
        return $this->query($sql.$groupQuery.$orderQuery, $maxLine);
    }

    /**
     * @param array $criteria
     * @param array $join
     * @param bool|false $maxLine
     * @param bool|false $order
     * @param bool|false $group
     * @return array
     */
    private function findWithJoin($criteria = [], $join = [], $maxLine = false, $order = false, $group = false)
    {
        $jointer = $this->join($join);
        $sql = $jointer['sql'];
        $sql .= ' '.$this->criteria($criteria);
        $orderQuery = $this->order($order);
        $groupQuery = $this->group($group);
        
        return $this->query($sql.$groupQuery.$orderQuery, $maxLine, $this->tableName.'.*, '.$jointer['column']);
    }

    /**
     * @param $args
     * @param int $max
     * @return mixed
     */
    private function getArgs($args, $max = 5)
    {
        for($j = 0 ; $j < $max ; $j++) {
            if (!array_key_exists($j, $args) && $j < 2) {
                $args[$j] = [];
            } elseif (!array_key_exists($j, $args)) {
                $args[$j] = false;
            }
        }
        return $args;
    }

    /**
     * @return void
     */
    private function getClassAnnotations()
    {
        $this->mapping = Mapping::getReader($this->class);
        $this->tableName = $this->mapping->getClassMapping('Table')->name;
        $this->forbidden = $this->mapping->getClassMapping('Forbidden')->columns;
        $this->properties = $this->mapping->getPropertiesMapping();
    }

    /**
     * @return string
     */
    public function getClassName()
    {
        return $this->reflectionClass->getShortName();
    }

    /**
     * @return array
     */
    public function getMapping()
    {
        return $this->mapping;
    }
    
    /**
     * @param string $name
     * @return object ReflectionProperty
     */
    public function getProperty($name)
    {
    	return $this->reflectionClass->getProperty($name);
    }

    /**
     * @param $input
     * @return int
     */
    public function insert($input)
    {
        $columns = '';
        $values = '';
        foreach ($input as $key => $value) {
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

    /**
     * @param $column
     * @param $value
     * @return int
     */
    public function set($column, $value)
    {
        if (!in_array($column, $this->forbidden)){
            $this->entity->$column = $value;
            if (isset($this->entity->id)) {
                $input = [$column => $value];
                $criteria = ['id' => $this->entity->id];
                return $this->update($input, $criteria);
            }
        } else {
            trigger_error($this->tableName." - Set method forbidden on '".$column."' attribute", E_USER_ERROR);
        }
    }

    /**
     * @param $input
     * @param $criteria
     * @return int
     */
    public function update($input, $criteria)
    {
        $values = '';
        foreach ($input as $key => $value) {
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
        return $this->queryPDO($query);
    }
}