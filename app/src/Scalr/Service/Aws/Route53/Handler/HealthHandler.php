<?php
namespace Scalr\Service\Aws\Route53\Handler;

use Scalr\Service\Aws\Route53Exception;
use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Route53\AbstractRoute53Handler;
use Scalr\Service\Aws\DataType\MarkerType;
use Scalr\Service\Aws\Route53\DataType\HealthList;
use Scalr\Service\Aws\Route53\DataType\HealthData;
use Scalr\Service\Aws\Route53\DataType\ChangeData;

/**
 * HealthHandler
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     4.5.2
 */
class HealthHandler extends AbstractRoute53Handler
{

    /**
     * GET HealthCheck List action
     *
     * @param   MarkerType       $marker optional The query parameters.
     * @return  HealthList       Returns the list of health checks.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function describe(MarkerType $marker = null)
    {
        return $this->getRoute53()->getApiHandler()->describeHealthChecks($marker);
    }

    /**
     * GET Health Check action
     *
     * @param   string           $healthId  ID of the health check.
     * @return  HealthData       Returns health check.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function fetch($healthId)
    {
        return $this->getRoute53()->getApiHandler()->getHealthCheck($healthId);
    }

    /**
     * DELETE Health check action
     *
     * @param   string                        $healthId ID of the health check.
     * @return  bool                          Returns TRUE on success.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function delete($healthId)
    {
        return $this->getRoute53()->getApiHandler()->deleteHealthCheck($healthId);
    }

    /**
     * POST Health Check action
     *
     * This action creates a new health check.
     *
     * @param   HealthData|string $config   Health check data object or xml document
     * @return  HealthData                  Returns created health check.
     * @throws  Route53Exception
     * @throws  ClientException
     */
    public function create($config)
    {
        return $this->getRoute53()->getApiHandler()->createHealthCheck($config);
    }
}