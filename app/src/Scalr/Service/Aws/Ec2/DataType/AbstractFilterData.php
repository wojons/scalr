<?php
namespace Scalr\Service\Aws\Ec2\DataType;

use Scalr\Service\Aws\Ec2\AbstractEc2DataType;

/**
 * AbstractFilterData
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    23.09.2013
 *
 * @property \Scalr\Service\Aws\Ec2\DataType\StringType $name
 *           A filter key name
 *
 * @property array $value
 *           An array of values
 *
 * @method   \Scalr\Service\Aws\Ec2\DataType\StringType getName()
 *           getName()
 *           Gets filter key name.
 *
 * @method   array getValue()
 *           getValue()
 *           Gets list of values.
 */
abstract class AbstractFilterData extends AbstractEc2DataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('name', 'value');

    /**
     * Gets the class name of the FilterNameType object
     *
     * @return   string Returns the class name of the FilterNameType object
     */
    abstract public function getFilterNameTypeClass();

    /**
     * Convenient constuctor for the filter
     *
     * @param \Scalr\Service\Aws\Ec2\DataType\StringType $name    Filter name
     * @param array|string                               $value   Filter value
     */
    public function __construct($name = null, $value = null)
    {
        parent::__construct();
        $this->setValue($value);
        $this->setName($name);
    }

    /**
     * Sets a filter key name.
     *
     * @param   \Scalr\Service\Aws\Ec2\DataType\StringType $name   Filter key name
     * @return  AbstractFilterData
     */
    public function setName($name = null)
    {
        $class = $this->getFilterNameTypeClass();
        if (is_object($name)) {
            if (!is_a($name, $class)) {
                throw new \InvalidArgumentException("First argument should be instance of the %s class.", $class);
            }
        } elseif ($name !== null) {
            $name = new $class ((string)$name);
        }

        return $this->__call(__FUNCTION__, array($name));
    }

    /**
     * Sets a filter values.
     *
     * @param   string|array $value Value of list of the values for the filter
     * @return  AbstractFilterData
     */
    public function setValue($value = null)
    {
        if ($value !== null && !is_array($value)) {
            $value = array((string)$value);
        }

        return $this->__call(__FUNCTION__, array($value));
    }
}