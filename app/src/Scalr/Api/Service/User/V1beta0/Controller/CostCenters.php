<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Exception\ApiNotImplementedErrorException;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Account\EnvironmentProperty;
use Scalr\Stats\CostAnalytics\Entity\AccountCostCenterEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;

/**
 * User/CostCenters API Controller
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
        return $this->getEnvironment()->getProperty(EnvironmentProperty::SETTING_CC_ID);
    }

    /**
     * Gets default search criteria
     *
     * @return array Returns array of the search criteria
     *
     * @throws ApiNotImplementedErrorException
     */
    public function getDefaultCriteria()
    {
        switch ($this->getScope()) {
            case ScopeInterface::SCOPE_ENVIRONMENT:
                return [[ '$and' => [
                    ['ccId' => $this->getEnvironmentCostCenterId()],
                ]]];

            case ScopeInterface::SCOPE_ACCOUNT:
                $cc = new CostCentreEntity();
                $accs = new AccountCostCenterEntity();

                return [
                    AbstractEntity::STMT_FROM => "{$cc->table()} LEFT JOIN {$accs->table()} AS `accs` ON {$accs->columnCcId('accs')} = {$cc->columnCcId()}",
                    AbstractEntity::STMT_WHERE => "{$accs->columnAccountId('accs')} = " . $accs->qstr('accountId', $this->getUser()->accountId)
                ];

            case ScopeInterface::SCOPE_SCALR:
                throw new ApiNotImplementedErrorException();
        }
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
        $this->checkPermissions(Acl::RESOURCE_ANALYTICS_ACCOUNT);

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
        $this->checkPermissions(Acl::RESOURCE_ANALYTICS_ACCOUNT);

        $criteria = $this->getDefaultCriteria();

        if (empty($this->params('name')) && empty($this->params('billingCode'))) {
            $criteria[] = ['archived' => CostCentreEntity::NOT_ARCHIVED];
        }
        
        return $this->adapter('costCenter')->getDescribeResult($criteria);
    }

    /**
     * Permissions won't check if object has environment scope and role Acl::RESOURCE_ANALYTICS_ACCOUNT
     * {@inheritdoc}
     * @see ApiController::checkPermissions()
     */
    public function checkPermissions(...$args)
    {
        if ($this->getScope() === ScopeInterface::SCOPE_ENVIRONMENT &&
            isset($args[0]) && $args[0] == Acl::RESOURCE_ANALYTICS_ACCOUNT && empty($args[1])) {
            return;
        }
        parent::checkPermissions(...$args);
    }
}