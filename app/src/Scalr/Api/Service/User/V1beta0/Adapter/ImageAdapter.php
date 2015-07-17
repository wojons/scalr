<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity;
use Scalr\Model\Entity\Image;
use DateTime;

/**
 * ImageAdapter V1
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (04.03.2015)
 */
class ImageAdapter extends ApiEntityAdapter
{

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            'hash' => 'id', 'name', 'id' => 'cloudImageId', 'cloudLocation',
            'platform' => 'cloudPlatform', '_added' => 'added', 'dtLastUsed' => 'lastUsed',
            'architecture', 'size', 'isDeprecated' => 'deprecated', 'source',
            'status', 'type', 'statusError',
            '_scope' => 'scope',
            '_os'    => 'os',
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => ['name'],

        self::RULE_TYPE_FILTERABLE  => [
            'id', 'name', 'scope', 'os',
            'cloudPlatform', 'cloudImageId', 'architecture',
            'cloudLocation', 'status', 'source', 'deprecated'
        ],

        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['dtAdded' => true]],
    ];

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass = 'Scalr\Model\Entity\Image';

    protected function _scope($from, $to, $action)
    {
        if ($action == self::ACT_CONVERT_TO_OBJECT) {
            $to->scope = $from->getScope();
        } else if ($action == self::ACT_CONVERT_TO_ENTITY) {
            if (empty($from->scope)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope value");
            }

            if ($from->scope === ScopeInterface::SCOPE_SCALR) {
                $to->envId = null;
            } else if ($from->scope === ScopeInterface::SCOPE_ENVIRONMENT) {
                $to->envId = $this->controller->getEnvironment()->id;
            } else {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected scope value");
            }
        } else if ($action == self::ACT_GET_FILTER_CRITERIA) {
            if (empty($from->scope)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid scope value");
            }

            if ($from->scope === ScopeInterface::SCOPE_SCALR) {
                return [['$or' => [['envId' => null], ['envId' => 0]]]];
            } else if ($from->scope === ScopeInterface::SCOPE_ENVIRONMENT) {
                return [['envId' => $this->controller->getEnvironment()->id]];
            } else {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Unexpected scope value");
            }
        }
    }

    protected function _os($from, $to, $action)
    {
        if ($action == self::ACT_CONVERT_TO_OBJECT) {
            $to->os = !empty($from->osId) ? ['id' => $from->osId] : null;
        } else if ($action == self::ACT_CONVERT_TO_ENTITY) {
            $osId = ApiController::getBareId($from, 'os');
            if (!empty($osId)) {
                if (!is_string($osId) || !preg_match('/^' . Entity\Os::ID_REGEXP . '$/', $osId)) {
                    throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the OS");
                }
                $to->osId = $osId;
            } else {
                $to->osId = null;
            }
        } else if ($action == self::ACT_GET_FILTER_CRITERIA) {
            if (empty($from->os) || !preg_match('/^' . Entity\Os::ID_REGEXP . '$/', $from->os)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the OS");
            }

            return [['osId' => $from->os]];
        }
    }

    protected function _added($from, $to, $action)
    {
        switch ($action) {
            case self::ACT_CONVERT_TO_OBJECT:
                $to->added = self::convertOutputValue('datetime', $from->getDtAdded());
                break;

            case self::ACT_CONVERT_TO_ENTITY:
                //nothing to do, as this property is not alterable
                break;

            case self::ACT_GET_FILTER_CRITERIA:
                if ($from->added === null) {
                    return [['$or' => [
                        ['dtAdded' => null],
                        ['$and' => [
                            ['dtAdded' => ['$gte' => DateTime::createFromFormat("Y", Image::NULL_YEAR)]],
                            ['dtAdded' => ['$lt'  => DateTime::createFromFormat("Y", Image::NULL_YEAR + 1)]]
                        ]]
                    ]]];
                } else {
                    return [['dtAdded' => self::convertInputValue('datetime', $from->added)]];
                }
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\DataType\ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!($entity instanceof Entity\Image)) {
            throw new \InvalidArgumentException(sprintf(
                "First argument must be instance of Scalr\\Model\\Entity\\Image class"
            ));
        }

        if ($entity->hash !== null) {
            //Checks if the image does exist
            if (!Entity\Image::findPk($entity->hash)) {
                throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, sprintf(
                    "Could not find out the Image with ID: %d", $entity->hash
                ));
            }
        } else {
            $image = Entity\Image::findOne([
                ['id' => $entity->id],
                ['$or' => [['envId' => $entity->envId], ['envId' => null]]],
                ['platform' => $entity->platform],
                ['cloudLocation' => $entity->cloudLocation]
            ]);

            if ($image) {
                throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, "This Image has already been registered in Scalr");
            }
        }

        //Is this a new Image
        if (!$entity->hash) {
            $entity->createdByEmail = $this->controller->getUser()->email;
            $entity->createdById = $this->controller->getUser()->id;
        }

        if (!Entity\Role::validateName($entity->name)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid name of the Image");
        }

        $entity->architecture = $entity->architecture ?: 'x86_64';
        if (! in_array($entity->architecture, ['i386','x86_64'])) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Invalid architecture of the Image.");
        }

        if (!$this->controller->hasPermissions($entity, true)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        //We only allow to either create or modify Environment Scope Roles
        if ($entity->getScope() !== ScopeInterface::SCOPE_ENVIRONMENT) {
            throw new ApiErrorException(403, ErrorMessage::ERR_SCOPE_VIOLATION, sprintf(
                "Only %s scope is allowed.", ScopeInterface::SCOPE_ENVIRONMENT
            ));
        }

        //Validates OS
        if (!empty($entity->osId)) {
            //Tries to find out the specified OS
            $os = Entity\Os::findPk($entity->osId);
            if (!($os instanceof Entity\Os)) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Specified OS does not exist");
            }
        } else {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "OS must be provided with the request.");
        }
    }
}