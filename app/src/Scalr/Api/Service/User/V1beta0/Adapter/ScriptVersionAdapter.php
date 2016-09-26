<?php

namespace Scalr\Api\Service\User\V1beta0\Adapter;

use InvalidArgumentException;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Model\Entity\Script;
use Scalr\Model\Entity\ScriptVersion;

/**
 * ScriptVersionAdapter V1beta0
 *
 * @author N.V.
 *
 * @method  \Scalr\Model\Entity\ScriptVersion toEntity($data) Converts data to entity
 */
class ScriptVersionAdapter extends ApiEntityAdapter {

    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        //Allows all entity properties to be converted from entity into data result object.
        //[entityProperty1 => resultProperty1, ... or  entityProperty1, entityProperty2, ...]
        self::RULE_TYPE_TO_DATA     => [
            '_script' => 'script', '_body' => 'body', 'version', 'dtCreated' => 'added'
        ],

        //The alterable properties
        self::RULE_TYPE_ALTERABLE   => [ 'body' ],

        self::RULE_TYPE_FILTERABLE  => [ 'script' ],
        self::RULE_TYPE_SORTING     => [self::RULE_TYPE_PROP_DEFAULT => ['dtCreated' => true]],
    ];

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\ScriptVersion';

    public function _script($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from ScriptVersion */
                $to->script = [
                    'id' => $from->scriptId
                ];
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to ScriptVersion */
                $to->scriptId = ApiController::getBareId($from, 'script');
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[ 'scriptId' => ApiController::getBareId($from, 'script') ]];
        }
    }

    public function _body($from, $to, $action)
    {
        switch ($action) {
            case static::ACT_CONVERT_TO_OBJECT:
                /* @var $from ScriptVersion */
                $to->body = $from->content;
                break;

            case static::ACT_CONVERT_TO_ENTITY:
                /* @var $to ScriptVersion */
                $to->content = $from->body;
                break;

            case static::ACT_GET_FILTER_CRITERIA:
                return [[]];
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Api\DataType\ApiEntityAdapter::validateEntity()
     */
    public function validateEntity($entity)
    {
        if (!$entity instanceof ScriptVersion) {
            throw new InvalidArgumentException(sprintf(
                "First argument must be instance of Scalr\\Model\\Entity\\ScriptVersion class"
            ));
        }

        if (!Script::findPk($entity->scriptId)) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Script {$entity->scriptId} not found");
        }

        if (substr($entity->content, 0, 2) !== '#!') {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, 'Property body is not valid. First line must contain shebang (#!/path/to/interpreter)');
        }

        if (!$this->controller->hasPermissions($entity, true)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }
    }
}