<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\Marker;
use Scalr\Service\OpenStack\Type\BooleanType;

/**
 * ListLbHealthMonitorsFilter
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (16.01.2014)
 *
 * @method   array getId() getId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter setId()
 *           setId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addId()
 *           addId($value)
 *
 * @method   array getAdminStateUp() getAdminStateUp()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addAdminStateUp()
 *           addAdminStateUp($value)
 *
 * @method   array getStatus() getStatus()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter setStatus()
 *           setStatus($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addStatus()
 *           addStatus($value)
 *
 * @method   array getType() getType()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter setType()
 *           setType($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addType()
 *           addType($value)
 *
 * @method   array getDelay() getDelay()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter setDelay()
 *           setDelay($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addDelay()
 *           addDelay($value)
 *
 * @method   array getTenantId() getTenantId()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter setTenantId()
 *           setTenantId($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addTenantId()
 *           addTenantId($value)
 *
 * @method   array getTimeout() getTimeout()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter setTimeout()
 *           setTimeout($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addTimeout()
 *           addTimeout($value)
 *
 * @method   array getMaxRetries() getMaxRetries()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter setMaxRetries()
 *           setMaxRetries($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addMaxRetries()
 *           addMaxRetries($value)
 *
 * @method   array getHttpMethod() getHttpMethod()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter setHttpMethod()
 *           setHttpMethod($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addHttpMethod()
 *           addHttpMethod($value)
 *
 * @method   array getUrlPath() getUrlPath()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter setUrlPath()
 *           setUrlPath($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addUrlPath()
 *           addUrlPath($value)
 *
 * @method   array getExpectedCodes() getExpectedCodes()
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter setExpectedCodes()
 *           setExpectedCodes($value)
 *
 * @method   \Scalr\Service\OpenStack\Services\Network\Type\ListLbHealthMonitorsFilter addExpectedCodes()
 *           addExpectedCodes($value)
 */
class ListLbHealthMonitorsFilter extends Marker
{

    private $id;

    private $status;

    private $type;

    private $delay;

    /**
     * @var BooleanType
     */
    private $adminStateUp;

    private $tenantId;

    private $timeout;

    private $maxRetries;

    private $httpMethod;

    private $urlPath;

    private $expectedCodes;

    /**
     * Convenient constructor
     *
     * @param   string|array        $id           optional The one or more ID
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     */
    public function __construct($id = null, $marker = null, $limit = null)
    {
        parent::__construct($marker, $limit);
        $this->setId($id);
    }

    /**
     * Initializes new object
     *
     * @param   string|array        $id           optional The one or more ID
     * @param   string              $marker       optional A marker.
     * @param   int                 $limit        optional Limit.
     * @return  ListSubnetsFilter   Returns a new ListSubnetsFilter object
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
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
}