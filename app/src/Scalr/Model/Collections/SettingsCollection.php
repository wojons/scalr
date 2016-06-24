<?php

namespace Scalr\Model\Collections;

use Exception;
use Scalr\Model\Entity\Setting;
use Scalr\Model\EntityStatement;
use Scalr\Model\Loader\Field;
use Scalr\Model\Type\EncryptedType;
use Scalr\Util\ObjectAccess;

/**
 * Settings collection
 *
 * @author N.V.
 */
class SettingsCollection extends ObjectAccess
{

    /**
     * Setting entity class with namespace
     *
     * @var string
     */
    private $entityClass;

    /**
     * Search criteria
     *
     * @var array
     */
    private $criteria;

    /**
     * Default values for new entities
     *
     * @var array
     */
    private $defaultProperties;

    /**
     * Internal entities collection
     *
     * @var Setting[]
     */
    private $entities = [];

    /**
     * An array of settings that have new values
     *
     * @var array
     */
    private $modified = [];

    /**
     * Flag indicates whether settings is loaded from DB
     *
     * @var bool
     */
    private $loaded = false;

    /**
     * SettingsCollection
     *
     * @param   string $entityClass                  Entity class name with namespace
     * @param   array  $criteria                     Search criteria
     * @param   array  $defaultProperties   optional Default values for new entities
     * @param   array  $settings            optional Initial settings
     */
    public function __construct($entityClass, array $criteria, array $defaultProperties = [], array $settings = [])
    {
        $this->entityClass = $entityClass;
        $this->criteria = $criteria;
        $this->defaultProperties = $defaultProperties;

        parent::__construct();

        foreach ($settings as $setting) {
            $this->offsetSet($setting['name'], $setting);
        }
    }

    /**
     * Reset referenced fields
     */
    public function __clone()
    {
        if (!$this->loaded) {
            $this->load();
        }

        $entities = $this->entities;
        $this->entities = [];
        $this->modified = [];
        $this->data = [];

        foreach ($entities as $name => $entity) {
            $newEntity = clone $entity;
            $this->entities[$name] = $newEntity;
            $this->modified[$name] = $newEntity;
            $this->data[$name] = &$newEntity->value;
        }
    }

    /**
     * Sets search criteria
     *
     * @param   array   $criteria   Search criteria
     */
    public function setCriteria(array $criteria)
    {
        $this->criteria = $criteria;
    }

    /**
     * Sets default values for new entities
     *
     * @param   array   $defaultProperties  Default values for new entities
     */
    public function setDefaultProperties(array $defaultProperties)
    {
        $this->defaultProperties = $defaultProperties;

        foreach ($this->entities as $entity) {
            foreach ($this->defaultProperties as $property => &$value) {
                $entity->{$property} = &$value;
            }
        }
    }

    /**
     * Gets setting value
     *
     * @param   string  $name   Setting name
     *
     * @return string|null
     *
     * @see ObjectAccess::offsetGet()
     */
    public function &offsetGet($name)
    {
        if (!(parent::offsetExists($name) || $this->loaded)) {
            $this->load();
        }

        return parent::offsetGet($name);
    }

    /**
     * Gets setting entity
     *
     * @param   string  $name   Setting name
     *
     * @return Setting
     */
    public function getEntity($name)
    {
        return isset($this->entities[$name]) ? $this->entities[$name] : null;
    }

    /**
     * Sets setting value, creates internal entity
     *
     * @param   string  $name       Setting name
     * @param   mixed   $setting    If $setting is array or object - interprets its fields as entity properties, otherwise - as value
     *
     * @see ObjectAccess::offsetSet()
     */
    public function offsetSet($name, $setting)
    {
        if (isset($this->entities[$name])) {
            $entity = &$this->entities[$name];
        } else {
            $entity = new $this->entityClass();

            $entity->name = $name;

            foreach ($this->defaultProperties as $property => &$value) {
                $entity->{$property} = &$value;
            }

            $this->entities[$entity->name] = &$entity;
            $this->data[$entity->name] = &$entity->value;

            unset($value);
        }

        if ($setting instanceof $this->entityClass) {
            $entity = $setting;
            $entity->name = $name;

            foreach ($this->defaultProperties as $property => &$value) {
                if (empty($entity->{$property})) {
                    $entity->{$property} = &$value;
                }
            }

            $this->data[$entity->name] = &$entity->value;
        } else if (is_object($setting) || is_array($setting)) {
            foreach ($setting as $property => $value) {
                $entity->{$property} = $value;
            }
        } else {
            $entity->value = $setting;
        }

        $this->modified[$entity->name] = $entity;
    }

    /**
     * Loads settings from DB, if some settings already changed - keeps new values
     */
    public function load()
    {
        /* @var $entity Setting */
        foreach (call_user_func_array([$this->entityClass, 'find'], [$this->criteria]) as $entity) {
            $name = $entity->name;

            if (!isset($this->entities[$name])) {
                $this->entities[$name] = $entity;

                $this->data[$entity->name] = &$entity->value;
            }
        }

        $this->loaded = true;
    }

    public function offsetExists($offset)
    {
        if (!parent::offsetExists($offset) && !$this->loaded) {
            $this->load();
        }

        return parent::offsetExists($offset);
    }

    /**
     * Saves settings entities to DB
     *
     * @throws \Scalr\Exception\ModelException
     */
    public function save()
    {
        foreach ($this->modified as $entity) {
            if ($entity->value === false) {
                $entity->delete();
            } else {
                $entity->save();
            }
        }

        $this->modified = [];
    }

    /**
     * Saves multiply settings
     *
     * @param   array $settings Name-value settings array
     *
     * @throws Exception
     * @throws \Scalr\Exception\ModelException
     */
    public function saveSettings(array $settings)
    {
        /* @var $stmt EntityStatement */
        $stmt = call_user_func([$this->entityClass, 'prepareSaveStatement']);

        $encryptedFields = [];
        if (method_exists($this->entityClass, 'listEncryptedFields')) {
            $encryptedFields = call_user_func("{$this->entityClass}::listEncryptedFields");
        }

        try {
            $stmt->start();

            foreach ($settings as $index => $data) {
                /* @var $setting Setting */
                $setting = new $this->entityClass();

                if (is_array($data)) {
                    foreach ($setting->getIterator() as $name => $value) {
                        if (isset($data[$name])) {
                            $setting->{$name} = $data[$name];
                        }
                    }
                } else {
                    $setting->name = $index;
                    $setting->value = $data;
                }

                $this->offsetSet($index, $setting);

                /* @var $field Field */
                if (in_array($setting->name, $encryptedFields)) {
                    $field = $setting->getIterator()->fields()['value'];
                    $prevType = $field->getType();
                    $field->setType(new EncryptedType($field));
                }

                $stmt->execute($setting, $data === false ? EntityStatement::TYPE_DELETE : null);

                if (isset($prevType)) {
                    $field->setType($prevType);
                    $prevType = null;
                }

                if ($data === false) {
                    $this->offsetUnset($index);
                }
            }

            $stmt->commit();
        } catch (Exception $e) {
            $stmt->rollback();

            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     * @see ObjectAccess::getIterator()
     */
    public function getIterator()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return parent::getIterator();
    }

    /**
     * Gets settings class name
     *
     * @return  string  Returns the name of settings class
     */
    public function getSettingsClass()
    {
        return $this->entityClass;
    }

    /**
     * Get array copy
     *
     * @link http://php.net/manual/en/arrayiterator.getarraycopy.php
     *
     * @return  array   A copy of the array, or array of public properties if ArrayIterator refers to an object.
     */
    public function getArrayCopy()
    {
        if (!$this->loaded) {
            $this->load();
        }

        return $this->data;
    }
}