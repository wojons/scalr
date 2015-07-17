<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr_Environment;

/**
 * User/Version-1beta0/CostCenters API Controller
 *
 * @author N.V.
 */
class CostCenters extends ApiController
{

    /**
     * Gets cost center Id from environment platform config
     *
     * @return mixed
     */
    public function getEnvironmentCostCenterId()
    {
        return Scalr_Environment::init()->loadById($this->getEnvironment()->id)->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID);
    }

    /**
     * Gets default search criteria
     *
     * @return array Returns array of the search criteria
     */
    public function getDefaultCriteria()
    {
        return [['ccId' => $this->getEnvironmentCostCenterId()]];
    }

    /**
     * Gets specified Script taking into account both scope and authentication token
     *
     * @param   string  $ccId  Unique identifier of the Cost-Center
     *
     * @return CostCentreEntity Returns the Project Entity on success
     *
     * @throws ApiErrorException
     *
     */
    public function getCostCenter($ccId)
    {
        //TODO: correct ACL resource
        $this->checkPermissions(Acl::RESOURCE_ANALYTICS_PROJECTS);

        if ($ccId != $this->getEnvironmentCostCenterId()) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Cost Center either does not exist or is not owned by your environment.");
        }

        /* @var $cc CostCentreEntity */
        $cc = CostCentreEntity::findPk($ccId);

        if (!$cc) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Cost Center either does not exist or is not owned by your environment.");
        }

        if (!$this->hasPermissions($cc)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        return $cc;
    }

    /**
     * Fetches detailed info about one project
     *
     * @param    string $ccId  Unique identifier of the Cost-Center
     *
     * @return   ResultEnvelope
     *
     * @throws   ApiErrorException
     */
    public function fetchAction($ccId)
    {
        //TODO: correct ACL resource
        $this->checkPermissions(Acl::RESOURCE_ANALYTICS_PROJECTS);

        return $this->result($this->adapter('costCenter')->toData($this->getCostCenter($ccId)));
    }

    /**
     * Retrieves the list of the cost centers
     *
     * @return array Returns describe result
     *
     * @throws ApiErrorException
     */
    public function describeAction()
    {
        //TODO: correct ACL resource
        $this->checkPermissions(Acl::RESOURCE_ANALYTICS_PROJECTS);

        return $this->adapter('costCenter')->getDescribeResult($this->getDefaultCriteria());
    }
}