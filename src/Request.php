<?php

namespace Requester;
use Atrapalo\Monolog\Handler\ElasticsearchHandler;
use Elasticsearch\ClientBuilder;
use Monolog\Logger;

/**
 * Description of Request
 *
 * @author Sébastien Dugène
 */
class Request extends Sql
{	
    protected $joinedProperties = null;
    protected $reflectionClass = null;
    protected $properties = null;
    protected $tableName = null;
    protected $forbidden = null;
    protected $mapping = null;
    protected $entity = null;
    protected $class = null;
    
    /* DATABASE */
    protected $dbUser = null;
    protected $dbPassword = null;
    protected $dhHost = null;
    protected $dbName = null;

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
    public function count($column = 'id', $criteria = [])
    {
        $sqlPart = $this->criteria($criteria);
        $query = "SELECT count(".$this->properties[$column].") as count FROM ".$this->tableName." ".$sqlPart;
        $sql = $this->queryPDO($query);

        $result = $sql->fetch(\PDO::FETCH_ASSOC);
        return $result['count'];
    }

    /**
     * @param int $id
     * @param array $criteria
     * @return mixed
     */
    public function copy($id, $inputs = [])
    {
		$object = $this->findById($id);
		$array = (array) $object;
		$finalsInput = array_merge($array, $inputs);
	    unset($finalsInput['id']);
	    foreach ($finalsInput as $key => $value) {
	    	if (!array_key_exists($key, $this->properties) || is_null($value)) {
	    		unset($finalsInput[$key]);
	    	}
	    }
		return $this->insert($finalsInput);
    }

    /**
     * @param array $criteria
     * @return int|\PDOStatement|string
     */
    public function delete($criteria, $limit = 1)
    {
        if (is_array($limit)) return $this->deleteWithJoin($criteria, $limit);
        
        $where = $this->criteria($criteria);
        if ($where != '') {
        	$query = "DELETE FROM ".$this->tableName." ".$where." LIMIT ".$limit;
            return $this->queryPDO($query);
        }
    }
    
    private function deleteWithJoin($criteria, $join)
    {
        $jointer = $this->join($join);
        $sql = $jointer['sql'];
        $sql .= ' '.$this->criteria($criteria);
        if ($sql != '') {
        	$query = "DELETE ".$this->tableName." FROM ".$this->tableName." ".$sql;
            return $this->queryPDO($query);
        }
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
         *  $args[5] : $groupOrder
         */
        $args = $this->getArgs(func_get_args());
        
        if (is_numeric($args[0])) {
            return $this->findById($args[0]);
        }
        
        if ($args[0] == '*') {
        	return $this->findByCriteria();
        }
        
        if (is_array($args[1]) && !empty($args[1])) {
            return $this->findWithJoin($args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
        }
        
        if (is_array($args[0])) {
            return $this->findByCriteria($args[0], $args[1], $args[2], $args[3], $args[4]);
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
    private function findByCriteria($criteria = [], $maxLine = false, $order = false, $group = false, $groupOrder = false)
    {
        $sql = $this->criteria($criteria);
        $orderQuery = $this->order($order);
        $groupQuery = $this->group($group);
        $groupOrder = $this->groupOrder($groupOrder);
        
        return $this->query($sql.$groupQuery.$orderQuery, $maxLine, $groupOrder);
    }

    /**
     * @param array $criteria
     * @param array $join
     * @param bool|false $maxLine
     * @param bool|false $order
     * @param bool|false $group
     * @return array
     */
    private function findWithJoin($criteria = [], $join = [], $maxLine = false, $order = false, $group = false, $groupOrder = false)
    {
    	$jointer = $this->join($join);
        $sql = $jointer['sql'];
        $sql .= ' '.$this->criteria($criteria);
        $orderQuery = $this->order($order);
        $groupQuery = $this->group($group);
        $groupOrder = $this->groupOrder($groupOrder);
        
        return $this->query($sql.$groupQuery.$orderQuery, $maxLine, '`'.$this->tableName.'`'.'.'.$groupOrder.', '.$jointer['column']);
    }
    
    /**
     * @return void
     */
    protected function log($expression, $title = 'SQL Debug', $debug = [])
    {
    	if (!is_array($debug) || empty($debug)) {
        	$debug = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 0);
    	}
    	
    	if (class_exists('Monolog\Logger')) {
	    	$clientBuilder = ClientBuilder::create();
	    	$clientBuilder->setHosts([LSTCSRCH_USER.':'.LSTCSRCH_PWD.'@'.LSTCSRCH_HOST.':'.LSTCSRCH_PORT]);
			$client = $clientBuilder->build();
	    	
	    	$logger = new Logger(SITE);
			$logger->pushHandler(
			    new ElasticsearchHandler($client, ['index' => 'logs', 'type' => 'log'])
			);
			
			$logger->debug($title, ['message' => $expression, 'debug' => $debug]);
    	
    	} else {
    		$fd = fopen( ROOT . "debug/sql.log", "a+");
    		fwrite($fd, $title . " ". date('Y-m-d H:i:s').' - '.$expression.' : '.$debug."\n");
    		fclose($fd);
    		
		    ini_set('log_errors', 1);
		    ini_set('error_log', ROOT . '/debug/php.log');
    	}
    }

    /**
     * @param $args
     * @param int $max
     * @return mixed
     */
    private function getArgs($args, $max = 6)
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
        $this->joinedProperties = $this->mapping->getPropertiesMapping('Joined');
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
     * @return array
     */
    public function getMappingProperties()
    {
        return $this->properties;
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
        
        if (array_key_exists('created', $this->properties)) {
        	$input['created'] = date('Y-m-d H:i:s');
        }
        
        if (array_key_exists('updated', $this->properties)) {
        	$input['updated'] = date('Y-m-d H:i:s');
        }
        
        foreach ($input as $key => $value) {
            if ($value == 'NULL') $value = 'mysql#NULL';
            
            if ($columns !== '') {
                $columns .= ', ';
            }
            if ($values !== '') {
                $values .= ', ';
            }
            $columns .= '`'.addslashes($this->properties[$key]).'`';

            $mysqlFunction = str_replace('mysql#','',$value);
            if ($mysqlFunction != $value) {
                $values .= $mysqlFunction;
            } else {
                $values .= "'".addslashes($value)."'";
            }
        }
        $query = "INSERT INTO ".'`'.$this->tableName.'`'." (".$columns.") VALUES (".$values.")" ;
        return $this->queryPDO($query);
    }
    
    private function mysqlFunction($value)
    {
    	$mysqlFunction = str_replace('mysql#','',$value);
    	if ($mysqlFunction == 'NULL') {
    		return null;
    	} elseif ($mysqlFunction != $value) {
    		return $mysqlFunction;
    	}
    	return $value;
    }

    /**
     * @param $column
     * @param $value
     * @return int
     */
    public function set($column, $value)
    {
        if (!in_array($column, $this->forbidden)){
        	$this->entity->$column = $this->mysqlFunction($value);
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
     * @param $user
     * @param $password
     * @param $host
     * @param $database
     * @return object Request
     */
    public function setDatabase($user = MYSQL_USER, $password = MYSQL_PWD, $host = MYSQL_HOST, $database = MYSQL_DB) {
    	$this->dbUser = $user;
		$this->dbPassword = $password;
		$this->dbHost = $host;
		$this->dbName = $database;
		return $this;
    }

    /**
     * @param $input
     * @param $criteria
     * @return int
     */
    public function update($input, $criteria)
    {
        $values = '';
        if (array_key_exists('updated', $this->properties)) {
        	$input['updated'] = date('Y-m-d H:i:s');
        }
        
        foreach ($input as $key => $value) {
            if ($values !== '') {
                $values .= ', ';
            }

            $mysqlFunction = str_replace('mysql#','',$value);
            if ($mysqlFunction != $value) {
                $values .= '`'.addslashes($this->properties[$key]).'`'." = ".$mysqlFunction;
            }
            
            elseif ($value == 'NULL' || $value == null) {
            	$values .= '`'.addslashes($this->properties[$key]).'`'." = NULL";
            }
            
            else {
                $values .= '`'.addslashes($this->properties[$key]).'`'." = '".addslashes($value)."'";
            }
        }

        $where = $this->criteria($criteria);
        $query = "UPDATE ".'`'.$this->tableName.'`'." SET ".$values." ".$where ;
        return $this->queryPDO($query);
    }
}
