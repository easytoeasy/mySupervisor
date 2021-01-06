<?php

class BaseObject
{

    public function __construct($config=[])
    {
        $this->setConfig($config);
    }

    public function setConfig($config)
    {
        foreach($config as $key => $value) {
            $this->$key = $value;
        }
        $this->init();
    }

    public function init()
    {
        
    }

    public function __set($name, $value)
    {
        $setter = 'set' . ucfirst($name);
        if (method_exists($this, $setter)) {
            $this->$setter($name, $value);
            return $this;
        }
        $this->$name = $value;
        return $this;
    }

    public function __get($name)
    {
        $getter = 'get' . ucfirst($name);
        if (method_exists($this, $getter)) {
            return $this->$getter($name);
        }
        return $this->$name;
    }

}