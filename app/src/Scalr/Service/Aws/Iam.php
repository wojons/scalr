<?php
namespace Scalr\Service\Aws;

use Scalr\Service\Aws;
/**
 * Amazon IAM interface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     13.11.2012
 *
 *
 * @property  \Scalr\Service\Aws\Iam\Handler\RoleHandler $role
 *            An role service interface handler.
 *
 * @property  \Scalr\Service\Aws\Iam\Handler\UserHandler $user
 *            An user service interface handler.
 *
 * @property  \Scalr\Service\Aws\Iam\Handler\InstanceProfileHandler $instanceProfile
 *            An instance profile service interface handler.
 *
 *
 * @method    \Scalr\Service\Aws\Iam\V20100508\IamApi getApiHandler()
 *            getApiHandler()
 *            Gets an IamApi handler.
 */
class Iam extends AbstractService implements ServiceInterface
{

    /**
     * API Version 20100508
     */
    const API_VERSION_20100508 = '20100508';

    /**
     * Current version of the API
     */
    const API_VERSION_CURRENT = self::API_VERSION_20100508;

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getAllowedEntities()
     */
    public function getAllowedEntities()
    {
        return array('user', 'role', 'instanceProfile');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getAvailableApiVersions()
     */
    public function getAvailableApiVersions()
    {
        return array(self::API_VERSION_20100508);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getCurrentApiVersion()
     */
    public function getCurrentApiVersion()
    {
        return self::API_VERSION_CURRENT;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getUrl()
     */
    public function getUrl()
    {
        $region = $this->getAws()->getRegion();
        if ($region == Aws::REGION_US_GOV_WEST_1) {
            return 'iam.us-gov.amazonaws.com';
        }
        return 'iam.amazonaws.com';
    }
}
