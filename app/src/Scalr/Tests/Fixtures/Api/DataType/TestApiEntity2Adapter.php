<?php

namespace Scalr\Tests\Fixtures\Api\DataType;

use Scalr\Api\DataType\ApiEntityAdapter;

/**
 * TestApiEntity2Adapter
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.14  (16.03.2016)
 */
class TestApiEntity2Adapter extends ApiEntityAdapter
{
    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        self::RULE_TYPE_TO_DATA => ['id', 'data'],
        self::RULE_TYPE_SETTINGS_PROPERTY => 'settings',
        self::RULE_TYPE_SETTINGS    => [
            'queue_name' => 'queue'
        ]
    ];

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass = 'Scalr\Tests\Fixtures\Model\Entity\TestEntity2';
}