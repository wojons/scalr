<?php
namespace Scalr\DataType;

use ArrayObject;
use InvalidArgumentException;

/**
 * AggregationCollectionSet
 *
 * The set of the AggrecationCollection objects. It is used to optimize
 * load of the same raw data to the multiple number of the collections using the only one pass.
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0 (20.06.2014)
 */
class AggregationCollectionSet extends ArrayObject implements AggregationCollectionInterface
{
    /**
     * Constructor
     *
     * @param array|\Traversable $collection Set of the AggregationCollection objects
     */
    public function __construct($collection)
    {
        parent::__construct([]);

        foreach ($collection as $k => $item) {
            if (!($item instanceof AggregationCollection)) {
                throw new InvalidArgumentException(sprintf(
                    "Consructor of the %s does accept only collection of the %s objects. %s given",
                    get_class($this),
                    __NAMESPACE__ . '/AggregationCollection',
                    gettype($item)
                ));
            }
            $this[$k] = $item;
        }
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\AggregationCollectionInterface::calculatePercentage()
     */
    public function calculatePercentage($decimals = 0)
    {
        foreach ($this->getIterator() as $k => $aggregationCollection) {
            /* @var $aggregationCollection \Scalr\DataType\AggregationCollection */
            $aggregationCollection->calculatePercentage($decimals);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\AggregationCollectionInterface::load()
     */
    public function load($rawData)
    {
        foreach ($rawData as $item) {
            foreach ($this->getIterator() as $k => $aggregationCollection) {
                $aggregationCollection->append($item);
            }
        }

        return $this;
    }
}