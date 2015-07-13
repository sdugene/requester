<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Requester;

/**
 * Description of Mapping
 *
 * @author Sébastien Dugène
 */
class Mapping
{
    private $reader = null;
    private $propertiesMapping = null;
    private $reflectionClass = null;
    private $classMapping = [];
    private $className = null;
    
    private function __construct($className)
    {
        $this->reader = \Minime\Annotations\Reader::createFromDefaults();
        $this->reflectionClass = new \ReflectionClass($className);
        $this->className = $className;
    }
    
    
    public static function getReader($className)
    {
        return new Mapping($className);
    }
	
	
    public function getClassMapping($key)
    {
        if (!array_key_exists($key, $this->classMapping)) {
            $annotations = $this->reader->getClassAnnotations($this->className);
            $value = $annotations->get($key);
            $values = json_encode($this->mappingParse($value));
            $this->classMapping[$key] = json_decode($values);
        }
        return $this->classMapping[$key];
    }
    
    
    public function getName($string)
    {
        $matches = [];
        if (preg_match('/@([a-zA-Z\d_\-]+)/', $string, $matches)) {

            $table = '';
            if ($matches[1] == 'this') {
                $table = $this->classMapping['Table']->name;
            } else {
                $nameSpace = $this->reflectionClass->getNamespaceName();
                $mapping = Mapping::getReader($nameSpace.'\\'.ucfirst($matches[1]));
                $table = $mapping->getClassMapping('Table')->name;
            }
            return $table;
        } else {
            return $string;
        }
    }
	
	
    public function getPropertiesMapping()
    {
        if (is_null($this->propertiesMapping)) {
            $array = [];
            $properties = $this->reflectionClass->getProperties();
            foreach($properties as $property) {
                $column = $this->reader->getPropertyAnnotations($this->className, $property->name)->get('ORM\Column');
                if(!is_null($column)) {
                    $values = $this->mappingParse($column);
                    $array[$property->name] = $values['name'];
                }
            }
            $this->propertiesMapping = $array;
        }
        return $this->propertiesMapping;
    }
	
	
    public function getValue($string)
    {
        $matches = [];
        if (preg_match('/@([a-zA-Z\d_\-]+).@([a-zA-Z\d_\-]+)/', $string, $matches)) {
            $table = '';
            $column = '';
            if ($matches[1] == 'this') {
                $table = $this->classMapping['Table']->name;
                $column = $this->propertiesMapping[$matches[2]];
            } else {
                $nameSpace = $this->reflectionClass->getNamespaceName();
                $mapping = Mapping::getReader($nameSpace.'\\'.ucfirst($matches[1]));
                $table = $mapping->getClassMapping('Table')->name;
                $properties = $mapping->getPropertiesMapping();
                $column = $properties[$matches[2]];
            }
            return $table . '.' . $column;
        } else {
            return $string;
        }
    }
	
	
    private function mappingParse($string)
    {
        $array = [];
        $matches = [];
        preg_match_all('/([a-zA-Z\d]+)="([a-zA-Z\d_\-]+)"|([a-zA-Z\d]+)=({"[a-zA-Z\d_\-]+"})/', $string, $matches, PREG_SET_ORDER) ;
        foreach ($matches as $value) {
            if ($value[1] != '') {
                $array[$value[1]] = $value[2];
            } elseif ($value[3] != '') {
                $array[$value[3]] = json_decode(str_replace(array('{','}'),array('[',']'),$value[4]), true);
            }
        }
        return $array;
    }
}