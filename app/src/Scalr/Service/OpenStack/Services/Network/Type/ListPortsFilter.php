<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\BooleanType;
use Scalr\Service\OpenStack\Type\Marker;
use \DateTime;

/**
 * ListPortsFilter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    08.05.2013
 */
class ListPortsFilter extends Marker
{

    /**
     * Filters the list of ports by name
     *
     * @var array
     */
    private $name;

    /**
     * Filter by id
     *
     * @var array
     */
    private $id;


    /**
     * Filter by ID of the network
     *
     * @var array
     */
    private $networkId;

    //TODO Additional filters can be added

    /**
     * Convenient constructor
     *
     * @param   string|array        $name         optional The one or more name of the port
     * @param   string|array        $id           optional The one or more ID of the port
     * @param   string|array        $networkid    optional The one or more ID of the network
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     */
    public function __construct($name = null, $id = null, $networkId = null, $marker = null, $limit = null)
    {
        parent::__construct($marker, $limit);
        $this->setName($name);
        $this->setId($id);
        $this->setNetworkId($networkId);
    }

    /**
     * Initializes new object
     *
     * @param   string|array        $name         optional The one or more name of the subnet
     * @param   string|array        $id           optional The one or more ID of the subnet
     * @param   string|array        $networkid    optional The one or more ID of the network
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     * @return  ListPortsFilter  Returns a new ListPortsFilter object
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }

    /**
     * Gets the list of the subnet name.
     *
     * @return  array  Returns array of the name of the subnet to filter
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets one or more name of the subnet to filter
     *
     * @param   string $name The list of the name of the subnet to filter
     * @return  ListPortsFilter
     */
    public function setName($name = null)
    {
        $this->name = array();
        return $name == null ? $this : $this->addName($name);
    }

    /**
     * Adds one or more name of the subnet to filter
     *
     * @param   string|array $name The name of the subnet to filter
     */
    public function addName($name)
    {
        return $this->_addPropertyValue('name', $name);
    }

    /**
     * Gets the list of the ID of the subnet
     *
     * @return  array Returns the list of the ID of the subnet
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the list of ID of the subnet
     *
     * @param   array|string   $id  The one or more ID of the subnet
     * @return  ListPortsFilter
     */
    public function setId($id = null)
    {
        $this->id = array();
        return $id === null ? $this : $this->addId($id);
    }

    /**
     * Adds one or more ID of the subnet to filter
     *
     * @param   string|array $id The one or more ID of the subnet to filter
     * @return  ListPortsFilter
     */
    public function addId($id)
    {
        return $this->_addPropertyValue('id', $id);
    }

    /**
     * Gets the list of the name of the networks.
     *
     * @return  array  Returns array of the name of the networks to filter
     */
    public function getNetworkId()
    {
        return $this->networkId;
    }

    /**
     * Sets one or more identifiers of the networks to filter
     *
     * @param   string|array $networkId The list of the identifiers of the networks to filter
     * @return  ListPortsFilter
     */
    public function setNetworkId($newtorkId = null)
    {
        $this->networkId = array();
        return $newtorkId == null ? $this : $this->addNetworkId($newtorkId);
    }

    /**
     * Adds one or more identifiers of the networks to filter
     *
     * @param   string|array $networkId The identifier of the network to filter
     */
    public function addNetworkId($networkId)
    {
        return $this->_addPropertyValue('networkId', $networkId);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Type.Marker::getQueryData()
     */
    public function getQueryData()
    {
        $options = parent::getQueryData();

        if (!empty($this->name)) {
            $options['name'] = $this->getName();
        }
        if (!empty($this->id)) {
            $options['id'] = $this->getId();
        }
        if (!empty($this->networkId)) {
            $options['network_id'] = $this->getNetworkId();
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\OpenStack\Type.Marker::getQueryString()
     */
    public function getQueryString()
    {
        $str = parent::getQueryString();

        $str .= $this->_getQueryStringForFields(array('name', 'id', 'networkId' => 'network_id'), __CLASS__);

        return ltrim($str, '&');
    }
}