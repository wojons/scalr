<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\BooleanType;
use Scalr\Service\OpenStack\Type\Marker;

/**
 * ListNetworksFilter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    08.05.2013
 */
class ListNetworksFilter extends Marker
{

    /**
     * Filters the list of networks by name
     *
     * @var array
     */
    private $name;

    /**
     * Filters the list of networks by status
     *
     * @var array
     */
    private $status;

    /**
     * Filter by admin state flag
     *
     * @var BooleanType
     */
    private $adminStateUp;

    /**
     * Filter by shared
     *
     * @var BooleanType
     */
    private $shared;

    /**
     * Filter by id
     *
     * @var array
     */
    private $id;

    /**
     * Convenient constructor
     *
     * @param   string|array            $name         optional The one or more name of the network
     * @param   NetworkStatusType|array $status       optional The one or more status of the Network
     * @param   bool                    $adminStateUp optional The admin state
     * @param   bool                    $shared       optional The shared flag
     * @param   string|array            $id           optional The one or more ID of the network
     * @param   string                  $marker       optional A marker.
     * @param   int                     $limit        optional Limit.
     */
    public function __construct($name = null, $status = null, $adminStateUp = null,
                                $shared = null, $id = null, $marker = null, $limit = null)
    {
        parent::__construct($marker, $limit);
        $this->setName($name);
        $this->setStatus($status);
        $this->setId($id);
        $this->setShared($shared);
        $this->setAdminStateUp($adminStateUp);
    }

    /**
     * Initializes new object
     *
     * @param   string|array            $name         optional The one or more name of the network
     * @param   NetworkStatusType|array $status       optional The one or more status of the Network
     * @param   bool                    $adminStateUp optional The admin state
     * @param   bool                    $shared       optional The shared flag
     * @param   string|array            $id           optional The one or more ID of the network
     * @param   string                  $marker       optional A marker.
     * @param   int                     $limit        optional Limit.
     * @return  ListNetworksFilter      Returns a new ListNetworksFilter object
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }

    /**
     * Gets the list of the network name.
     *
     * @return  array  Returns array of the name of the network to filter
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Gets the list of the network status.
     *
     * @return  array Returns array of the statuses to filter
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Sets one or more name of the network to filter
     *
     * @param   string $name The list of the name of the network to filter
     * @return  ListNetworksFilter
     */
    public function setName($name = null)
    {
        $this->name = array();
        return $name == null ? $this : $this->addName($name);
    }

    /**
     * Adds one or more name of the network to filter
     *
     * @param   string|array $name The name of the network to filter
     */
    public function addName($name)
    {
        return $this->_addPropertyValue('name', $name);
    }

    /**
     * Sets the list of the statuses of the network to filter
     *
     * @param   NetworkStatusType|array $status The list of the statuses to filter
     * @return  ListNetworksFilter
     */
    public function setStatus($status = null)
    {
        $this->status = array();
        return $status == null ? $this : $this->addStatus($status);
    }

    /**
     * Adds one or more status of the network to filter
     *
     * @param   NetworkStatusType|array $status The list of the statuses to filter
     * @return  ListNetworksFilter
     */
    public function addStatus($status = null)
    {
        return $this->_addPropertyValue('status', $status, function($v) {
            if (!($v instanceof NetworkStatusType)) {
                $v = new NetworkStatusType((string)$v);
            }
            return $v;
        });
    }


    /**
     * Gets admin state
     *
     * @return  boolean Returns admin state
     */
    public function getAdminStateUp()
    {
        return $this->adminStateUp;
    }

    /**
     * Gets shared flag
     *
     * @return  boolean Returns the shared flag
     */
    public function getShared()
    {
        return $this->shared;
    }

    /**
     * Gets the list of the ID of the network
     *
     * @return  array Returns the list of the ID of the network
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the admin state flag
     *
     * @param   boolean $adminStateUp The admin state flag
     * @return  ListNetworksFilter
     */
    public function setAdminStateUp($adminStateUp = null)
    {
        $this->adminStateUp = $adminStateUp !== null ? BooleanType::init($adminStateUp) : null;
        return $this;
    }

    /**
     * Sets the shared flag
     *
     * @param   boolean $shared The shared flag
     * @return  ListNetworksFilter
     */
    public function setShared($shared = null)
    {
        $this->shared = $shared !== null ? BooleanType::init($shared) : null;
        return $this;
    }

    /**
     * Sets the list of ID of the network
     *
     * @param   array|string   $id  The one or more ID of the network
     * @return  ListNetworksFilter
     */
    public function setId($id = null)
    {
        $this->id = $id;
        return $id === null ? $this : $this->addId($id);
    }

    /**
     * Adds one or more ID of the network to filter
     *
     * @param   string|array $id The one or more ID of the network to filter
     * @return  ListNetworksFilter
     */
    public function addId($id)
    {
        return $this->_addPropertyValue('id', $id);
    }
}