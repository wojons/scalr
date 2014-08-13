<?php
namespace Scalr\Service\OpenStack\Type;

/**
 * AbstractList
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    11.12.2012
 */
abstract class AbstractList extends \ArrayIterator
{

    /**
     * This method must return class name of the values for the list.
     * This list will restrict.
     *
     * @return  string Return class name.
     */
    abstract public function getClass();

    /**
     * Constructor
     *
     * @param   array   $array   Array of the objects.
     * @throws  \InvalidArgumentException
     */
    public function __construct($array = array())
    {
        $strictClass = $this->getClass();
        if ($strictClass !== null) {
            foreach ($array as $v) {
                if (!(is_a($v, $strictClass))) {
                    throw new \InvalidArgumentException(sprintf('Only Array of ' . $strictClass . ' objects is allowed!'));
                }
            }
        }
        parent::__construct($array);
    }

    /**
     * {@inheritdoc}
     * @see ArrayIterator::append()
     */
    public function append($value)
    {
        $strictClass = $this->getClass();
        if ($strictClass !== null && !(is_a($value, $strictClass))) {
            throw new \InvalidArgumentException(sprintf('Only %s object is allowed to append!', $strictClass));
        }
        parent::append($value);
    }

    /**
     * Transforms list to Json string
     *
     * @return  string  Returns JSON ecoded string
     */
    public function toJson()
    {
        return json_encode((array)$this);
    }

    /**
     * Transforms list to Array
     *
     * @return  Array Returns array
     */
    public function toArray()
    {
        return (array)$this;
    }
}