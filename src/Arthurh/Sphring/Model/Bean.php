<?php
/**
 * Copyright (C) 2014 Orange
 *
 * This software is distributed under the terms and conditions of the 'MIT'
 * license which can be found in the file 'LICENSE' in this package distribution
 * or at 'http://opensource.org/licenses/MIT'.
 *
 * Author: Arthur Halet
 * Date: 14/10/2014
 */


namespace Arthurh\Sphring\Model;

use Arthurh\Sphring\Enum\BeanTypeEnum;
use Arthurh\Sphring\Enum\SphringEventEnum;
use Arthurh\Sphring\EventDispatcher\AnnotationsDispatcher;
use Arthurh\Sphring\EventDispatcher\EventBeanProperty;
use Arthurh\Sphring\EventDispatcher\SphringEventDispatcher;
use Arthurh\Sphring\Exception\BeanException;
use Arthurh\Sphring\Logger\LoggerSphring;
use Arthurh\Sphring\Model\BeanProperty\AbstractBeanProperty;


/**
 * Class Bean
 * @package arthurh\sphring\model
 */
class Bean
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var string
     */
    private $class;

    /**
     * @var BeanTypeEnum
     */
    private $type;

    /**
     * @var AbstractBeanProperty[]
     */
    private $properties = array();

    /**
     * @var Bean
     */
    private $extend;
    /**
     * @var object
     */
    private $object;
    /**
     * @var SphringEventDispatcher
     */
    private $sphringEventDispatcher;

    /**
     * @var array
     */
    private $interfaces = [];
    /**
     * @var string
     */
    private $parent;

    /**
     * @param $id
     * @param \Arthurh\Sphring\Enum\BeanTypeEnum $type
     */
    function __construct($id, BeanTypeEnum $type = null)
    {
        if ($type === null) {
            $type = BeanTypeEnum::NORMAL_TYPE;
        }
        $this->id = $id;
        $this->type = $type;
    }

    /**
     * @param $key
     */
    public function removeProperty($key)
    {
        if (empty($this->properties[$key])) {
            return;
        }
        unset($this->properties[$key]);
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param string $class
     */
    public function setClass($class)
    {
        $this->class = $class;
        try {
            $reflector = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            return;
        }

        $this->interfaces = $reflector->getInterfaceNames();
        if (!empty($reflector->getParentClass())) {
            $this->parent = $reflector->getParentClass()->getName();
        }

    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }


    /**
     * @param $key
     * @param array $value
     * @throws \Arthurh\Sphring\Exception\BeanException
     */
    public function addProperty($key, array $value)
    {
        $propertyKey = null;
        $propertyValue = null;
        // key() and current() are break on hhvm do it in other way the same thing
        foreach ($value as $propertyKey => $propertyValue) {
            break;
        }
        try {
            $propertyClass = $this->getPropertyFromEvent($propertyKey, $propertyValue);
        } catch (BeanException $e) {
            throw new BeanException($this, "Error when declaring property name '%s': '%s'.", $key, $e->getMessage());
        }
        if (empty($propertyClass)) {
            throw new BeanException($this, "Error when declaring property name '%s', property '%s' doesn't exist", $key, $propertyKey);
        }
        $this->properties[$key] = $propertyClass;
    }

    /**
     * @param string $propertyKey
     * @param mixed $propertyValue
     * @throws \Arthurh\Sphring\Exception\BeanException
     * @return AbstractBeanProperty
     */
    private function getPropertyFromEvent($propertyKey, $propertyValue)
    {
        if (empty($propertyKey) || empty($propertyValue)) {
            throw new BeanException($this, "property not valid");
        }
        $event = new EventBeanProperty();
        $event->setData($propertyValue);
        $eventName = SphringEventEnum::PROPERTY_INJECTION . $propertyKey;
        $event->setName($eventName);

        $event = $this->sphringEventDispatcher->dispatch($eventName, $event);
        if (!($event instanceof EventBeanProperty)) {
            throw new BeanException($this, "event '%s' is not a '%s' event", get_class($event), EventBeanProperty::class);
        }
        return $event->getBeanProperty();
    }

    /**
     * @param string $key
     * @return null|AbstractBeanProperty
     */
    public function getProperty($key)
    {
        if (empty($this->properties[$key])) {
            return null;
        }
        return $this->properties[$key];
    }

    /**
     * @return BeanTypeEnum
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string|BeanTypeEnum $type
     * @throws \Arthurh\Sphring\Exception\BeanException
     */
    public function setType($type)
    {
        if (empty($type)) {
            return;
        }
        if ($type instanceof BeanTypeEnum) {
            $this->type = $type;
            return;
        }
        $this->type = BeanTypeEnum::fromValue($type);
        if (empty($this->type)) {
            throw new BeanException($this, "Bean type '%s' doesn't exist", $type);
        }
    }

    /**
     * @return Bean
     */
    public function getExtend()
    {
        return $this->extend;
    }

    /**
     * @param Bean $extend
     */
    public function setExtend(Bean $extend)
    {
        $this->extend = $extend;
    }

    public function inject()
    {
        if ($this->type == BeanTypeEnum::ABSTRACT_TYPE) {
            return;
        }
        $this->getLogger()->info(sprintf("Injecting in bean '%s'", $this->id));
        $this->instanciate();
        $properties = $this->properties;
        if (!empty($this->extend)) {
            $properties = array_merge($this->extend->getProperties(), $properties);
        }
        foreach ($properties as $propertyName => $propertyInjector) {
            $setter = "set" . ucfirst($propertyName);
            $this->object->$setter($propertyInjector->getInjection());
        }
        $this->dispatchAnnotations();
    }

    /**
     * @return LoggerSphring
     */
    protected function getLogger()
    {
        return LoggerSphring::getInstance();
    }

    private function instanciate()
    {
        $classReflector = new \ReflectionClass($this->class);
        $this->object = $classReflector->newInstance();
    }

    /**
     * @return AbstractBeanProperty[]
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param array $properties
     */
    public function setProperties(array $properties)
    {
        foreach ($properties as $propertyKey => $propertyValue) {
            $this->addProperty($propertyKey, $propertyValue);
        }
    }

    private function dispatchAnnotations()
    {
        $annotationDispatcher = new AnnotationsDispatcher($this, $this->class, $this->sphringEventDispatcher);
        $annotationDispatcher->dispatchAnnotations();
    }


    /**
     * @return object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @param \Arthurh\Sphring\Runner\SphringRunner $object
     */
    public function setObject($object)
    {
        $this->object = $object;
    }

    /**
     * @return SphringEventDispatcher
     */
    public function getSphringEventDispatcher()
    {
        return $this->sphringEventDispatcher;
    }

    /**
     * @param SphringEventDispatcher $sphringEventDispatcher
     */
    public function setSphringEventDispatcher($sphringEventDispatcher)
    {
        $this->sphringEventDispatcher = $sphringEventDispatcher;
    }

    /**
     * @return array
     */
    public function getInterfaces()
    {
        return $this->interfaces;
    }

    /**
     * @return string
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param $className
     * @return bool
     */
    public function containClassName($className)
    {
        return $this->hasInterface($className) || $this->hasParent($className) || $this->class == $className;
    }

    public function hasInterface($interfaceName)
    {
        return in_array($interfaceName, $this->interfaces);
    }

    public function hasParent($className)
    {
        return $this->getParent() == $className;
    }
}
