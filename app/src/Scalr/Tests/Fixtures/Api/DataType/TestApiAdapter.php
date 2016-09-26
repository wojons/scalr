<?php

namespace Scalr\Tests\Fixtures\Api\DataType;

use Scalr\Api\DataType\ApiEntityAdapter;

/**
 * TestApiAdapter Adapter for test
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.6.14  (12.11.2015)
 */
class TestApiAdapter extends ApiEntityAdapter
{
    /**
     * Converter rules
     *
     * @var array
     */
    protected $rules = [
        self::RULE_TYPE_TO_DATA => [
            'id', '_dtField' => 'dtField'
        ]
    ];

    /**
     * Entity class name
     *
     * @var string
     */
    protected $entityClass = 'Scalr\Tests\Fixtures\Model\Entity\TestEntity';

    /**
     * Create DateTime object from text property value
     *
     * @param object $from
     * @param object $to
     * @param int    $action
     */
    protected function _dtField($from, $to, $action)
    {
        $to->dtField = new \DateTime($from->dtField);
    }

}