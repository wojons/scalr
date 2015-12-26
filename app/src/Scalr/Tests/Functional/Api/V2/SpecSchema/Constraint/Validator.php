<?php

namespace Scalr\Tests\Functional\Api\V2\SpecSchema\Constraint;

use Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes\ApiEntity;
use Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes\Property;
use Scalr\Tests\Functional\Api\V2\SpecSchema\DataTypes\AbstractSpecObject;
use stdClass;

/**
 * Class Validator
 *
 * Validate SpecObject created form swagger specification with api response
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (03.12.2015)
 */
class Validator
{

    protected  static $ignoreEnumVal = [
        'nebula', 'contrail', 'unknown'
    ];

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @return bool
     */
    public function isValid()
    {
        return empty($this->errors);
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Add errors to this validator
     *
     * @param string $property error property
     * @param string $message  error message
     */
    public function appendError($property, $message)
    {
        $this->errors[] = [
            'property' => $property,
            'message' => $message
        ];
    }

    /**
     * Invokes the validation of an element
     *
     * @param mixed              $value  value what we should check
     * @param AbstractSpecObject $schema schema value generated of api specification
     */
    public function check($value, $schema)
    {
        if (is_object($schema)) {
            // check object properties
            if (is_object($value)) {
                $this->checkObject($value, isset($schema->entity) ? $schema->entity : $schema);
            }

            // check items
            if (is_array($value)) {
                $this->checkItems($value, $schema);
            }

            //check property types
            if (is_scalar($value)) {
                $this->checkProperty($value, $schema);
            }

            // check required field
            if (isset($schema->required)) {
                $this->checkRequired($value, $schema->required);
            }
        } else {
            $value = is_array($value) ? get_object_vars(array_shift($value)) : get_object_vars($value);
            $this->appendError(implode(' ,', array_keys($value)), ' properties doesn\'t exist in api definitions');
        }
    }

    /**
     * Validates an object
     *
     * @param stdClass           $element object properties
     * @param AbstractSpecObject $schema  schema this object generated of api specification
     */
    protected function checkObject($element, $schema)
    {
        if (isset($schema->sample)) {
            $this->checkSample($element, $schema->sample);
            return;
        }

        if (isset($schema->discriminator)) {
            $discriminator = $schema->discriminator;
            $type = $element->$discriminator;

            if (empty($schema->concreteTypes[$type])) {
                $this->appendError($type, " unexpected discriminator value");
                return;
            }

            $schema = $schema->concreteTypes[$type];
            unset($schema->discriminator);
            $this->check($element, $schema);
            return;
        }

        $properties = $schema instanceof ApiEntity ? $schema->getProperties() : (array)$schema;

        foreach ($element as $p => $value) {
            if (isset($properties[$p])) {
                $this->check($value, $properties[$p]);
            } else {
                $this->appendError($p, "this property don't exist in Api specifications");
            }
        }
        return;
    }

    /**
     * Check required element
     *
     * @param array $element  object with required element
     * @param array $required list of required element in api specifications
     */
    protected function checkRequired($element, $required)
    {
        foreach (array_diff_key(array_flip($required), (array) $element) as $key => $value) {
            $this->appendError($key, 'this element is required.');
        }
    }

    /**
     * Check each items in element
     *
     * @param array              $element the list of items
     * @param AbstractSpecObject $schema  items schema generated of api specification
     */
    protected function checkItems($element, $schema)
    {
        if(isset($schema->items)) {
            foreach ($element as $value) {
                $this->check($value, $schema->items);
            }
        } else {
            $this->checkRequired($element, $schema->entity->required);
            $this->checkObject($element, $schema->entity);
        }
        $this->checkProperty($element, $schema);
    }

    /**
     * Check property form spec object
     *
     * @param mixed    $element property value
     * @param Property $schema  property schema generated of api specification
     */
    protected function checkProperty($element, Property $schema)
    {
        //TODO:ape: check element don't empty because pagination type is string AND default is NULL
        if (!empty($element)) {
            switch ($schema->type) {
                case 'string':
                    $error = !is_string($element);
                    break;
                case 'integer':
                    $error = !is_numeric($element);
                    break;
                case 'boolean':
                    $error = !(is_bool($element) || 1 == $element || 0 == $element);
                    break;
                case 'array':
                    $error = !is_array($element);
                    break;
                default:
                    $error = false;
            }
            if ($error) {
                $this->appendError($schema->getObjectName(),
                    sprintf('This type is\'t not consistency with Api. Type should be %s.', $schema->type)
                );
            }
        }

        // check enum properties
        if (isset($schema->enum) && !in_array($element, $schema->enum) && !in_array($element, static::$ignoreEnumVal) ) {
            $this->appendError($schema->getObjectName(),
                sprintf('[%s] is not valid allowed value %s %s.', $element,
                    ...(count($schema->enum) === 1 ? ['is', array_shift($schema->enum)] : ['are', implode(', ', $schema->enum)])
                )
            );
        }
    }

    /**
     * Check each sample element
     *
     * @param stdClass $element sample element not described in properties
     * @param Property $schema  schema each sample element generated of api specification
     */
    protected function checkSample($element, $schema)
    {
        foreach ($element as $value) {
            $this->checkProperty($value, $schema);
        }
    }
}