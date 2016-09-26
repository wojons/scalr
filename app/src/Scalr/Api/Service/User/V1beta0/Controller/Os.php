<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Model\Entity;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\DataType\ErrorMessage;

/**
 * User/Os API Controller
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    5.4.0  (24.02.2015)
 */
class Os extends ApiController
{
    /**
     * Gets OSes
     */
    public function getAction()
    {
        return $this->adapter('os')->getDescribeResult();
    }

    /**
     * Fetches detailed info about the Os
     *
     * @param    string $osId Unique identifier of the Os
     * @return \Scalr\Api\DataType\ResultEnvelope
     * @throws ApiErrorException
     */
    public function fetchAction($osId)
    {
        $os = Entity\Os::findPk($osId);

        if (!$os) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Unable to find requested OS");
        }

        return $this->result($this->adapter('os')->toData($os));
    }
}