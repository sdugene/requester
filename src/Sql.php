<?php

namespace Requester;

abstract class Sql
{
    /**
     * @param $criteria
     * @param string $operator
     * @param string $sql
     * @return bool|string
     */
    protected function criteria($criteria, $operator = 'AND', $sql = false, $where = true, $name = false)
    {
        foreach ($criteria as $key => $value) {
            if ($sql !== false) {
                $sql .= ' '.$operator.' ';
            }
            
            if ($name !== false) {
                $key = $name;
            }
            
            if ($this->findOperator($key, $value)) {
                $sql .= $this->findOperator($key, $value, $operator);
            } elseif (is_array($value) && is_numeric(key($value))) {
            	$value = $this->criteria($value, $operator, false, false, $key);
            	$sql .= $value;
            } elseif (is_array($value)) {
            	$value = $this->criteria($value, $key, false, false);
            	$sql .= '(' . $value . ')';
            } elseif (is_numeric($value) && $key == 'id') {
                if (!array_key_exists($key, $this->properties)) {
                    $sql .= $key.' = '.$value;
                } else {
                    $sql .= '`'.$this->tableName.'`'.'.'.'`'.$this->properties[$key].'`'.' = '.$value;
                }
            } elseif ($value == 'NULL') {
                if (!array_key_exists($key, $this->properties)) {
                    $sql .= $key.' IS NULL';
                } else {
                    $sql .= '`'.$this->tableName.'`'.'.'.'`'.$this->properties[$key].'`'.' IS NULL';
                }
            } else {
                if (!array_key_exists($key, $this->properties)) {
                    $sql .= $key.' = \''.addslashes($value).'\'';
                } else {
                    $sql .= '`'.$this->tableName.'`'.'.'.'`'.$this->properties[$key].'`'.' = \''.addslashes($value).'\'';
                }
            }
        }
		
		return $where && $sql ? "WHERE " . $sql : $sql;
    }

    /**
     * @param array $result
     * @param bool|false $entity
     * @return bool
     */
    protected function fill($result = [], $entity = false)
    {
        if ($entity === false) {
            $entity = $this->entity;
        }
        
        $properties = $this->reflectionClass->getProperties();
        foreach ($properties as $property)
        {
            $key = $property->name;
        	if (array_key_exists($key, $this->properties)) {
        		$name = $this->properties[$key];
        	} elseif (array_key_exists($key, $this->joinedProperties)) {
        		$name = $this->joinedProperties[$key];
        	} else {
        		$name = $key;
        	}
        	
        	
        	if ($property->isPrivate()) {
                // DO NOTHING
            } elseif ($property->isProtected()) {
                $setter = 'set'.ucfirst($key);
                $entity->$setter($result[$name]);
            } elseif(array_key_exists($name, $result)) {
                $entity->$key = $result[$name];
            } else {
                $entity->$key = null;
           }
        }
        return $entity;
    }

    /**
     * @param $key
     * @param $value
     * @return bool|string
     */
    private function findOperator($key, $value, $operator = 'AND')
    {
        $operators = ['=', '>', '>=', '<', '<=', '!=', 'LIKE'];
        $arrayOperators = ['IN', 'NOT IN', 'IS'];
        
        if (is_array($value) && in_array($key, $operators) && is_array($value[0])) {
        	$sqlPart = [];
        	foreach ($value as $criteria) {
        		$sqlPart[] = '`'.$this->tableName.'`'.'.'.'`'.$this->properties[$criteria[0]].'`'. " ".$key. " '".addslashes($criteria[1])."'";
        	}
            return implode(' '.$operator.' ', $sqlPart);
        }
        elseif (is_array($value) && in_array($key, $operators)) {
            return '`'.$this->tableName.'`'.'.'.'`'.$this->properties[$value[0]].'`'. " ".$key. " '".addslashes($value[1])."'";
        }
        elseif (is_array($value) && in_array($key, $arrayOperators) && is_array($value[1])) {
        	return '`'.$this->tableName.'`'.'.'.'`'.$this->properties[$value[0]].'`'. " " .$key. " ".'(\''.implode('\',\'',$value[1]).'\')';
        }
        elseif (is_array($value) && in_array($key, $arrayOperators)) {
        	return '`'.$this->tableName.'`'.'.'.'`'.$this->properties[$value[0]].'`'. " " .$key. " ".$value[1];
        }
        
        return false;
    }

    /**
     * @param $tableName
     * @return string
     */
    private function getPrefixedColumn($tableName)
    {
    	$tableFields = array_values($this->mapping->getProperties($tableName));
    	$name = $this->mapping->getName($tableName);
        
        foreach ($tableFields as $key => $value) {
            $tableFields[$key] = '`'.$name.'`'.'.'.'`'.$value.'`'.' as '.'`'.$tableName.'_'.$value.'`';
        }
        
        return implode(', ', $tableFields);
    }

    /**
     * @return array
     */
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

    /**
     * @param $group
     * @return string
     */
    protected function group($group)
    {
        $groupQuery = '';
        if ($group !== false) {
            $groupList = '';
            foreach($group as $key => $value) {
                if ($groupList !== '') {
                    $groupList .= ', ';
                }
                if (!array_key_exists($key, $this->properties)) {
                    $groupList .= $key.' '.strtoupper($value);
                } else {
                    $groupList .= '`'.$this->tableName.'`'.'.'.'`'.$this->properties[$key].'`'.' '.strtoupper($value);
                }

            }
            $groupQuery = ' GROUP BY '.trim($groupList);
        }
        return $groupQuery;
    }

    /**
     * @param $join
     * @return array
     */
    protected function join($join)
    {
        $sql = [];
        $column = [];
        foreach ($join as $method => $joinArray) {
        	$table = key($joinArray);
	    	$key = key($joinArray[$table]);
    		if(preg_match('/@([^.]*).@(.*)/', $key, $matches) && preg_match('/@([^.]*).@(.*)/', $joinArray[$table][$key], $matchesTarget)) {
    			$sql[] = strtoupper($method).' JOIN '.$matchesTarget[1].' ON ';
	    		$sql[] = $this->mapping->valueMapping($key)." = ".$this->mapping->valueMapping($joinArray[$table][$key]);
	    		$column[] = $this->getPrefixedColumn($matchesTarget[1]);
	    	} else {
        		$column[] = $this->getPrefixedColumn($table);
            	$sql[] = strtoupper($method).' JOIN '.'`'.$this->mapping->getName($table).'`';
            	$sql[] = $this->joinCriteria($joinArray[$table], $table);
	    	}
        }
        return [
            'sql' => implode(' ',$sql),
            'column' => implode(', ',$column)
        ];
    }

    /**
     * @param $criteria
     * @param $table
     * @return string
     */
    private function joinCriteria($criteria, $table)
    {
        $sql = [];
        foreach ($criteria as $boolean => $column) {
            if(!is_array($column)) {
                $joinMapping = $this->mapping->getPropertieJoinColumn($column, $table);
            } else {
                $joinMapping = ['@'.$table.'.@'.key($column) => current($column)];
            }
            foreach ($joinMapping as $key => $value) {
                if ($this->findOperator($key, $value)) {
                    $sql[] = $boolean.' '.$this->findOperator($key, $value);
                } else {
                    $sql[] = $boolean.' '.$this->mapping->valueMapping($key)." = ".$this->mapping->valueMapping($value);
                }
            }
        }
        return implode(' ',$sql);
    }

    /**
     * @param $results
     * @return array
     */
    private function multiFill($results) {
        $array = [];
        foreach($results as $key => $value) {
            $array[$key] = $this->fill($value, new $this->class());
        }
        return $array;
    }

    /**
     * @param $order
     * @return string
     */
    protected function order($order)
    {
        $orderQuery = '';
        if ($order !== false) {
            $orderList = '';
            foreach($order as $key => $value) {
                if ($orderList !== '') {
                    $orderList .= ', ';
                }
                if (!array_key_exists($key, $this->properties)) {
                    $orderList .= $key.' '.strtoupper($value);
                } else {
                    $orderList .= '`'.$this->tableName.'`'.'.'.'`'.$this->properties[$key].'`'.' '.strtoupper($value);
                }
            }
            $orderQuery = ' ORDER BY '.trim($orderList);
        }
        return $orderQuery;
    }

    /**
     * @param $order
     * @return string
     */
    protected function groupOrder($groupOrder)
    {
        $groupOrderQuery = '*';
        if ($groupOrder !== false) {
            foreach($groupOrder as $key => $value) {
                if ($groupOrderQuery !== '') {
                    $groupOrderQuery .= ', ';
                }
                if ($value == 'DESC') {
                	$groupOrderQuery .= 'max('.'`'.$this->tableName.'`'.'.'.'`'.$key.'`'.') as '.'`'.$key.'`';
                } else {
                	$groupOrderQuery .= 'min('.'`'.$this->tableName.'`'.'.'.'`'.$key.'`'.') as '.'`'.$key.'`';
                }
                
            }
        }
        return $groupOrderQuery;
    }

    /**
     * @param $query
     * @param bool|false $maxLine
     * @param string $column
     * @return array
     */
    protected function query($query, $maxLine = false, $column = '*')
    {
        $limit = '' ;
        if($maxLine !== false && is_numeric($maxLine)){
            $limit = " LIMIT " . $maxLine ;
        }
        $sql = $this->queryPDO("SELECT ".$column." FROM ".'`'.$this->tableName.'`'." ".$query.$limit);
		
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

    /**
     * @param $query
     * @return int|\PDOStatement|string
     */
    protected function queryPDO($query) {
    	if ($this->dbUser == null) {
        	$bdd = Pdo::getBdd();
    	} else {
    		$bdd = Pdo::getBddWithParams($this->dbUser, $this->dbPassword, $this->dbHost, $this->dbName);
    	}
    	
        try {
			$sql = $bdd->prepare($query, [\PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL]);
			$result = $sql->execute();
            if (DEBUG_MODE) {
            	$this->log($query.' - '.$sql->rowCount());
            }
            if (preg_match("/^INSERT/i", $query)) {
                $sql->closeCursor();
                if ($bdd->lastInsertId()) {
                    return $bdd->lastInsertId();
                } else {
                    return $result;
                }
            } elseif (preg_match("/^(DELETE|UPDATE)/i", $query)) {
                $count = $sql->rowCount();
                $sql->closeCursor();
                return $count;
            } else {
                return $sql;
            }
			
		} catch (Exception $e) {
		    $this->log($query.' Error : '.$e->getMessage());
		}
    }
}
