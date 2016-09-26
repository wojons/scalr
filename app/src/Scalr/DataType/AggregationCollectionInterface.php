<?php
namespace Scalr\DataType;

/**
 * AggregationCollectionInterface
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0 (20.06.2014)
 */
interface AggregationCollectionInterface
{
    /**
     * Calculates percentage
     *
     * @param   number    $decimals  Decimal points
     * @return  AggregationCollectionInterface
     */
    public function calculatePercentage($decimals = 0);

    /**
     * Loads whole collection
     *
     * @param   array|\Traversable $rawData  The raw collection
     * @return  AggregationCollectionInterface
     */
    public function load($rawData);

    /**
     * Appends item to collection
     *
     * @param mixed $item An array or object
     */
    public function append($item);
}