<?php

namespace Scalr\Api\Service\Account\V1beta0\Controller;

use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ListResultEnvelope;
use Scalr\Api\Rest\Exception\ApiInsufficientPermissionsException;

/**
 * Account/AclRoles API Controller
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.18 (5.03.2016)
 */
class AclRoles extends ApiController
{
    /**
     * Gets default ACL search criteria
     *
     * @return  array Returns array of the search criteria
     */
    public function getDefaultCriteria()
    {
        return [['accountId' => $this->getUser()->getAccountId()]];
    }

    /**
     * Retrieves the list of the ACL Roles
     *
     * @return ListResultEnvelope
     *
     * @throws ApiErrorException
     */
    public function describeAction()
    {
        if (!$this->getUser()->canManageAcl()) {
            throw new ApiInsufficientPermissionsException();
        }

        return $this->adapter('aclRole')->getDescribeResult($this->getDefaultCriteria());
    }
}