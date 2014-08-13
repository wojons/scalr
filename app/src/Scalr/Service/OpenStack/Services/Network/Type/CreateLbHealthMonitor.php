<?php
namespace Scalr\Service\OpenStack\Services\Network\Type;

use Scalr\Service\OpenStack\Type\AbstractInitType;

/**
 * CreateLbHealthMonitor
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (16.01.2014)
 */
class CreateLbHealthMonitor extends AbstractInitType
{

    /**
     * Owner of the health monitor. Only admin users can specify a tenant identifier other than its own
     *
     * @var string
     */
    public $tenant_id;

    /**
     * The type of probe send by load balancer to verify member state
     *
     * Possible values: {"PING" | "TCP" | "HTTP" | "HTTPS"}
     *
     * @var string
     */
    public $type;

    /**
     * The time in seconds between sending probes to members.
     *
     * @var int
     */
    public $delay;

    /**
     * The maximum number of seconds for a monitor to wait
     * for a connection to be established before it times out.
     * The value must be less than the delay value.
     *
     * @var string
     */
    public $timeout;

    /**
     * Number of allowed connection failures before changing the member's status to INACTIVE
     *
     * Possible values: 1 - 10
     *
     * @var int
     */
    public $max_retries;

    /**
     * The HTTP method used for requests by the monitor.
     *
     * @var string
     */
    public $http_method;

    /**
     * The HTTP path of the request sent by the monitor to test a member's health.
     * This must be a string beginning with a / (forward slash).
     *
     * @var string
     */
    public $url_path;

    /**
     * The list of HTTP status codes expected in response from the member to declare it healthy.
     *
     * @var string
     */
    public $expected_codes;

    /**
     * Administrative state of the health monitor
     *
     * @var boolean
     */
    public $admin_state_up;

    /**
     * Constructor
     *
     * @param   string $type
     *          The type of probe send by load balancer to verify member state
     *
     * @param   string $delay
     *          The time in seconds between sending probes to members
     *
     * @param   string $timeout
     *          The maximum number of seconds for a monitor to wait
     *          for a connection to be established before it times out.
     *          The value must be less than the delay value.
     *
     * @param   string $maxRetries
     *          Number of allowed connection failures before changing the member's status to INACTIVE
     *
     * @param   string $httpMethod    optional
     *          The HTTP method used for requests by the monitor
     *          (Required if type is HTTP or HTTPS)
     *
     * @param   string $urlPath       optional
     *          The HTTP path of the request
     *          (Required if type is HTTP or HTTPS)
     *
     * @param   string $expectedCodes optional
     *          The list of HTTP status codes expected in response
     *          from the member to declare it healthy.
     *          (Required if type is HTTP or HTTPS)
     *
     * @param   string $tenantId      optional
     *          The owner of the health monitor
     */
    public function __construct($type = null, $delay = null, $timeout = null, $maxRetries = null,
                                $httpMethod = null, $urlPath = null, $expectedCodes = null, $tenantId = null)
    {
        $this->tenant_id = $tenantId;
        $this->type = $type;
        $this->delay = $delay;
        $this->timeout = $timeout;
        $this->max_retries = $maxRetries;
        $this->http_method = $httpMethod;
        $this->url_path = $urlPath;
        $this->expected_codes = $expectedCodes;
    }

    /**
     * Initializes a new CreateLbHealthMonitor object
     *
     * @param   string $type
     *          The type of probe send by load balancer to verify member state
     *
     * @param   string $delay
     *          The time in seconds between sending probes to members
     *
     * @param   string $timeout
     *          The maximum number of seconds for a monitor to wait
     *          for a connection to be established before it times out.
     *          The value must be less than the delay value.
     *
     * @param   string $maxRetries
     *          Number of allowed connection failures before changing the member's status to INACTIVE
     *
     * @param   string $httpMethod    optional
     *          The HTTP method used for requests by the monitor
     *          (Required if type is HTTP or HTTPS)
     *
     * @param   string $urlPath       optional
     *          The HTTP path of the request
     *          (Required if type is HTTP or HTTPS)
     *
     * @param   string $expectedCodes optional
     *          The list of HTTP status codes expected in response
     *          from the member to declare it healthy.
     *          (Required if type is HTTP or HTTPS)
     *
     * @param   string $tenantId      optional
     *          The owner of the health monitor
     *
     * @return  CreateLbHealthMonitor
     */
    public static function init()
    {
        return call_user_func_array('parent::init', func_get_args());
    }
}