<?php
namespace Scalr\DataType;

use Scalr\DataType\Iterator\AggregationRecursiveIterator;
use IteratorAggregate, Countable, ArrayAccess, ArrayObject;

/**
 * AggregationCollection
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0 (25.03.2014)
 */
class AggregationCollection implements AggregationCollectionInterface, IteratorAggregate, Countable, ArrayAccess
{

    /**
     * The data array
     *
     * @var \ArrayObject
     */
    protected $array;

    /**
     * Internal iterator
     *
     * @var \AggregationRecursiveIterator
     */
    protected $iterator;

    /**
     * @var array
     */
    protected $subtotals;

    /**
     * The fields which should be provided in the result and
     * which have one to one relationship to subtotal's identifier
     *
     * @var array
     */
    protected $associatedFields;

    /**
     * @var array
     */
    protected $aggregateFields;

    /**
     * Constructor
     *
     * @param array $subtotals       The subtotal fields in exact order
     * @param array $aggregateFields The fields which is used for aggregation
     */
    public function __construct(array $subtotals, array $aggregateFields)
    {
        $this->array = new \ArrayObject();
        $this->subtotals = [];
        $i = 0;
        foreach ($subtotals as $k => $v) {
            if (is_numeric($k)) {
                $this->subtotals[$i] = $v;
            } else {
                $this->subtotals[$i] = $k;
                $this->associatedFields[$i] = $v;
            }
            $i++;
        }
        $this->aggregateFields = $aggregateFields;
    }

    /**
     * Sets raw array to copy data from another Aggregation Collection
     *
     * @param   array    $array  Array to set
     * @return  AggregationCollection
     */
    public function setArray($array)
    {
        $this->array = $array instanceof ArrayObject ? $array : new ArrayObject($array);
        $this->iterator = null;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        if (!$this->iterator) {
            $this->iterator = new AggregationRecursiveIterator($this->array->getArrayCopy());
        }
        return $this->iterator;
    }

    /**
     * Appends item to collection
     *
     * @param mixed $item An array or object
     */
    public function append($item)
    {
        $ptr = & $this->array;

        $aggregateValues = [];

        $fn = function (&$ptr, $field, $function) use(&$aggregateValues)
        {
            if (!isset($ptr[$field])) {
                $ptr[$field] = $aggregateValues[$field];
            } else {
                switch ($function) {
                    case 'SUM':
                    case 'sum':
                        $ptr[$field] += $aggregateValues[$field];
                        break;

                    case 'MAX':
                    case 'max':
                        $ptr[$field] = max($ptr[$field], $aggregateValues[$field]);
                        break;

                    case 'MIN':
                    case 'min':
                        $ptr[$field] = min($ptr[$field], $aggregateValues[$field]);
                        break;
                }
            }
        };

        foreach ($this->aggregateFields as $field => $function) {
            $aggregateValues[$field] = (is_array($item) ? (isset($item[$field]) ? $item[$field] : null) :
                                       (isset($item->$field) ? $item->$field : null));
            $fn($ptr, $field, $function);
        }

        if (!empty($this->subtotals)) {
            $cnt = count($this->subtotals);

            foreach ($this->subtotals as $pos => $field) {
                if (! isset($ptr['data'])) {
                    $ptr['data'] = [];
                }

                $ptr = & $ptr['data'];

                $idv = (string) (is_array($item) ? (isset($item[$field]) ? $item[$field] : '') :
                       (isset($item->$field) ? $item->$field : ''));

                if (!isset($ptr[$idv])) {
                    $ptr[$idv] = ['id' => $idv];

                    if ($pos < $cnt - 1) {
                        $ptr[$idv]['subtotals'] = array_slice($this->subtotals, $pos + 1);
                    }
                }

                foreach ($this->aggregateFields as $field => $function) {
                    $fn($ptr[$idv], $field, $function);
                }

                if (!empty($this->associatedFields[$pos])) {
                    foreach ($this->associatedFields[$pos] as $fieldName) {
                        $ptr[$idv][$fieldName] = (is_array($item) ? (isset($item[$fieldName]) ? $item[$fieldName] : null) :
                                           (isset($item->$fieldName) ? $item->$fieldName : null));
                    }
                }

                $ptr = & $ptr[$idv];
            }
        }
    }

    /**
     * Loads whole collection
     *
     * @param   array|\Traversable $rawData  The raw collection
     * @return  AggregationCollection
     */
    public function load($rawData)
    {
        foreach ($rawData as $item) {
            $this->append($item);
        }

        return $this;
    }

    /**
     * Calculates percentage
     *
     * @param   number    $decimals  Decimal points
     * @return  AggregationCollection
     */
    public function calculatePercentage($decimals = 0)
    {
        $perFields = [];

        foreach ($this->aggregateFields as $field => $function) {
            if ($function == 'sum' || $function == 'SUM') {
                $perFields[] = $field;
                $this->array[$field . '_percentage'] = $decimals ? number_format(100, $decimals) : 100;
            }
        }

        if (empty($perFields)) return;

        $fnIterator = function (&$ptr) use(&$fnIterator, $perFields, $decimals)
        {
            $format = $decimals ? sprintf("%%0.%df", $decimals) : "%d";
            if (isset($ptr['data'])) {
                foreach ($ptr['data'] as $k => $v) {
                    foreach ($perFields as $field) {
                        $ptr['data'][$k][$field . '_percentage'] = empty($ptr[$field]) ? 0 :
                            sprintf($format, round(($v[$field] / $ptr[$field]), 2) * 100);
                    }
                    if (!empty($v['data'])) {
                        $fnIterator($ptr['data'][$k]);
                    }
                }
            }
        };

        $fnIterator($this->array);

        return $this;
    }

    /**
     * Gets copy of the data array
     *
     * @return array Returns the copy of the data array
     */
    public function getArrayCopy()
    {
        return $this->array->getArrayCopy();
    }

    /**
     * Sets the identifier that is associated with the data
     *
     * @param   string    $id
     * @return  AggregationCollection
     */
    public function setId($id)
    {
        $this->array['id'] = $id;

        return $this;
    }

    /**
     * Get totals
     *
     * @return  array
     */
    public function getTotals()
    {
        $ret = [];

        foreach ($this->array as $field => $v) {
            if ($field == 'data') continue;
            $ret[$field] = $v;
        }

        //Initializes fields if they miss
        foreach ($this->aggregateFields as $field => $function) {
            if (!array_key_exists($field, $ret)) {
                $ret[$field] = $function == 'sum' || $function == 'SUM' ? 0 : null;
            }
        }

        return $ret;
    }

    /**
     * {@inheritdoc}
     * @see Countable::count()
     */
    public function count()
    {
        return count($this->array);
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetExists()
     */
    public function offsetExists($offset)
    {
        return $this->array->offsetExists($offset);
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetGet()
     */
    public function offsetGet($offset)
    {
        return $this->array->offsetGet($offset);
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetSet()
     */
    public function offsetSet($offset, $value)
    {
        $this->array->offsetSet($offset, $value);
    }

    /**
     * {@inheritdoc}
     * @see ArrayAccess::offsetUnset()
     */
    public function offsetUnset($offset)
    {
        $this->array->offsetUnset($offset);
    }
}