<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\DataType\ScopeInterface;
use Scalr\Exception\ValidationErrorException;
use Scalr\Model\Entity\GlobalVariable;
use Scalr_Scripting_GlobalVariables;

/**
 * Trait for API controllers that provide Global Variable management
 *
 * @author N.V.
 */
trait GlobalVariableTrait
{
    /**
     * Gets a specific global variable data
     *
     * @param   string                          $name                Variable name
     * @param   Scalr_Scripting_GlobalVariables $globalVar           Instance of Global variable handler
     * @param   int                             $roleId     optional The ID of GV Role
     * @param   int                             $farmId     optional The ID of GV Farm
     * @param   int                             $farmRoleId optional The ID of GV Farm Role
     * @param   string                          $serverId   optional The ID of GV Server
     *
     * @return  mixed
     * @throws  ApiErrorException
     */
    public function getGlobalVariable(
        $name,
        Scalr_Scripting_GlobalVariables $globalVar,
        $roleId = 0,
        $farmId = 0,
        $farmRoleId = 0,
        $serverId = ''
    ) {
        $list = $globalVar->getValues($roleId, $farmId, $farmRoleId, $serverId);
        $fetch = [];

        foreach ($list as $var) {
            if ((!empty($var['current']['name']) && $var['current']['name'] == $name)
                || (!empty($var['default']['name']) && $var['default']['name'] == $name)) {

                $fetch = $var;
                break;
            }
        }

        return $fetch;
    }

    /**
     * Makes GlobalVariable entity
     *
     * @param   array   $variable   The array representation of global variable
     *
     * @return  GlobalVariable
     */
    public function makeGlobalVariableEntity(array $variable)
    {
        $scope = $this->getActualVariableScope($variable);

        switch ($scope) {
            case ScopeInterface::SCOPE_SCALR:
                $class = GlobalVariable::class;
                break;

            case ScopeInterface::SCOPE_FARMROLE:
                $class = GlobalVariable\FarmRoleGlobalVariable::class;
                break;

            default:
                $class = GlobalVariable::class . '\\' . ucfirst($scope) . 'GlobalVariable';
        }

        $entity = new $class();

        $entity->name = $variable['name'];
        $entity->value = $this->getActualValue('value', $variable);
        $entity->category = $this->getActualValue('category', $variable);
        $entity->validator = $this->getActualValue('validator', $variable);
        $entity->format = $this->getActualValue('format', $variable);
        $entity->final = $this->getActualValue('flagFinal', $variable);
        $entity->required = $this->getActualValue('flagRequired', $variable);
        $entity->hidden = $this->getActualValue('flagHidden', $variable);
        $entity->description = $this->getActualValue('description', $variable);

        return $entity;
    }

    /**
     * Updates Global Variable
     *
     * @param   Scalr_Scripting_GlobalVariables $gvInstance Instance of Global variable handler
     * @param   array                           $variable   Array representation of Global Variable that presents current state
     * @param   object                          $object     JSON representation of Global Variable containing a new values
     * @param   string                          $name       The name of the Global Variable
     * @param   string                          $scope      Scope of the Global Variable
     * @param   int                             $roleId     optional The ID of GV Role
     * @param   int                             $farmId     optional The ID of GV Farm
     * @param   int                             $farmRoleId optional The ID of GV Farm Role
     * @param   string                          $serverId   optional The ID of GV Server
     *
     * @throws  ApiErrorException
     */
    public function updateGlobalVariable(
        Scalr_Scripting_GlobalVariables $gvInstance,
        array $variable,
        $object,
        $name,
        $scope,
        $roleId = 0,
        $farmId = 0,
        $farmRoleId = 0,
        $serverId = ''
    ) {
        $variable['flagDelete'] = '';

        if ($scope != $this->getActualVariableScope($variable)) {
            $variable['current']['name'] = $name;
            $variable['current']['value'] = $this->getActualValue('value', $variable, $object);
            $variable['current']['scope'] = $scope;
        } else {
            $variable['current'] = [
                'name'          => $name,
                'value'         => $this->getActualValue('value', $variable, $object),
                'category'      => strtolower($this->getActualValue('category', $variable, $object)),
                'flagFinal'     => $this->getActualValue('flagFinal', $variable, $object, 'locked'),
                'flagRequired'  => $this->getActualValue('flagRequired', $variable, $object, 'requiredIn'),
                'flagHidden'    => $this->getActualValue('flagHidden', $variable, $object, 'hidden'),
                'format'        => $this->getActualValue('format', $variable, $object, 'outputFormat'),
                'validator'     => $this->getActualValue('validator', $variable, $object, 'validationPattern'),
                'description'   => $this->getActualValue('description', $variable, $object),
                'scope'         => $scope,
            ];
        }

        try {
            $gvInstance->setValues([$variable], $roleId, $farmId, $farmRoleId, $serverId);
        } catch (ValidationErrorException $e) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, $e->getMessage());
        }
    }

    /**
     * Extracts most relevant property of the Global Variable with given the possible new values
     *
     * @param   string  $property                The name of extracting property
     * @param   array   $variable                Array representation of Global Variable that presents current state
     * @param   object  $object         optional JSON representation of Global Variable containing a new values
     * @param   string  $objectProperty optional The name of extracting property in JSON representation
     * @param   mixed   $defaultValue   optional Default return value if it's not presented in any scope
     *
     * @return  mixed   Returns the value of the property
     */
    protected function getActualValue($property, array $variable, $object = null, $objectProperty = null, $defaultValue = null)
    {
        if (empty($objectProperty)) {
            $objectProperty = $property;
        }

        return isset($object->{$objectProperty})
               ? $object->{$objectProperty}
               : (isset($variable['locked'][$property])
                 ? $variable['locked'][$property]
                 : (isset($variable['current'][$property]) ? $variable['current'][$property] : $defaultValue));
    }

    /**
     * Returns cleaned definition of a Global Variable without empty fields
     *
     * @param   array   $variable   Array representation of Global Variable
     *
     * @return  array
     */
    protected function getCleanVarDefinition(array $variable)
    {
        $cleanVariable = array_filter($variable);

        if (isset($variable['current'])) {
            $cleanVariable['current'] = array_filter($variable['current']);
        }

        if (isset($variable['locked'])) {
            $cleanVariable['locked'] = array_filter($variable['locked']);
        }

        return $cleanVariable;
    }

    /**
     * Extracts actual variable scope
     *
     * @param   array   $variable   Array representation of Global Variable
     *
     * @return  string
     */
    protected function getActualVariableScope(array $variable)
    {
        return empty($variable['locked']['scope'])
            ? (empty($variable['scopes']) ? ScopeInterface::SCOPE_SCALR : reset($variable['scopes']))
            : $variable['locked']['scope'];
    }
}