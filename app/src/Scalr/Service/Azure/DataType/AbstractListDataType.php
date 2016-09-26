<?php

namespace Scalr\Service\Azure\DataType;

/**
 * AbstractListDataType
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.8.6
 */
abstract class AbstractListDataType extends \ArrayIterator
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
    public function __construct($array = [])
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
     * Transforms list to Array
     *
     * @return  Array Returns array
     */
    public function toArray()
    {
        $result = [];

        foreach ($this as $data) {
            $result[] = $data->toArray();
        }

        return $result;
    }

    /**
     * Transforms list to Json string
     *
     * @return  string  Returns JSON encoded string
     */
    public function toJson()
    {
        return json_encode($this->toArray());
    }

}