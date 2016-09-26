<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\Marker;

/**
 * ListRoutersFilter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    26.08.2013
 */
class ListRoutersFilter extends Marker
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
     * Filter by status
     *
     * @var array
     */
    private $status;

    //Additional filters can be added

    /**
     * Convenient constructor
     *
     * @param   string|array        $name         optional The one or more name of the router
     * @param   string|array        $id           optional The one or more ID of the router
     * @param   string|array        $status       optional The one or more status of the router
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     */
    public function __construct($name = null, $id = null, $status = null, $marker = null, $limit = null)
    {
        parent::__construct($marker, $limit);
        $this->setName($name);
        $this->setId($id);
        $this->setStatus($status);
    }

    /**
     * Initializes new object
     *
     * @param   string|array        $name         optional The one or more name of the router
     * @param   string|array        $id           optional The one or more ID of the router
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     * @return  ListRoutersFilter  Returns a new ListRoutersFilter object
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }

    /**
     * Gets the list of the router name.
     *
     * @return  array  Returns array of the name of the router to filter
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets one or more name of the router to filter
     *
     * @param   string $name The list of the name of the router to filter
     * @return  ListRoutersFilter
     */
    public function setName($name = null)
    {
        $this->name = array();
        return $name === null ? $this : $this->addName($name);
    }

    /**
     * Adds one or more name of the router to filter
     *
     * @param   string|array $name The name of the router to filter
     */
    public function addName($name)
    {
        return $this->_addPropertyValue('name', $name);
    }

    /**
     * Gets the list of the ID of the router
     *
     * @return  array Returns the list of the ID of the router
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Gets the list of the statuses of the router
     *
     * @return  array Returns the list of the statuses of the router
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Sets the list of ID of the router
     *
     * @param   array|string   $id  The one or more ID of the router
     * @return  ListRoutersFilter
     */
    public function setId($id = null)
    {
        $this->id = array();
        return $id === null ? $this : $this->addId($id);
    }

    /**
     * Adds one or more ID of the router to filter
     *
     * @param   string|array $id The one or more ID of the router to filter
     * @return  ListRoutersFilter
     */
    public function addId($id)
    {
        return $this->_addPropertyValue('id', $id);
    }

    /**
     * Sets the list of statuses of the router
     *
     * @param   array|string   $status  The one or more status of the router
     * @return  ListRoutersFilter
     */
    public function setStatus($status = null)
    {
        $this->status = array();
        return $status === null ? $this : $this->addStatus($status);
    }

    /**
     * Adds one or more status of the router to filter
     *
     * @param   string|array $status The one or more status of the router to filter
     * @return  ListRoutersFilter
     */
    public function addStatus($status)
    {
        return $this->_addPropertyValue('status', $status);
    }
}