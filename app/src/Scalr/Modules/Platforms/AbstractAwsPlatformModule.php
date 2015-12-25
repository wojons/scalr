<?php
namespace Scalr\Modules\Platforms;

use Scalr\Model\Entity;
use Scalr\Service\Aws;
use Scalr\Modules\AbstractPlatformModule;
use SERVER_PLATFORMS;

abstract class AbstractAwsPlatformModule extends AbstractPlatformModule
{
    /**
     * Gets the list of available locations
     *
     * @param \Scalr_Environment $environment
     * @return \Scalr_Environment Returns the list of available locations looks like array(location => description)
     */
    public function getLocations(\Scalr_Environment $environment = null)
    {
        $accountType = null;

        if ($environment instanceof \Scalr_Environment) {
            $accountType = $environment->cloudCredentials(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE];
        }

        return $this->getLocationsByAccountType($accountType);
    }

    /**
     * Gets list of locations by account type or all locations if account type is not set
     *
     * @param string $accountType optional  Aws Account type
     * @return array
     */
    public function getLocationsByAccountType($accountType = null)
    {
        $retval = [
            Aws::REGION_US_EAST_1      => 'us-east-1 (N. Virginia)',
            Aws::REGION_US_WEST_1      => 'us-west-1 (N. California)',
            Aws::REGION_US_WEST_2      => 'us-west-2 (Oregon)',
            Aws::REGION_EU_WEST_1      => 'eu-west-1 (Ireland)',
            Aws::REGION_EU_CENTRAL_1   => 'eu-central-1 (Frankfurt)',
            Aws::REGION_SA_EAST_1      => 'sa-east-1 (Sao Paulo)',
            Aws::REGION_AP_SOUTHEAST_1 => 'ap-southeast-1 (Singapore)',
            Aws::REGION_AP_SOUTHEAST_2 => 'ap-southeast-2 (Sydney)',
            Aws::REGION_AP_NORTHEAST_1 => 'ap-northeast-1 (Tokyo)'
        ];

        if (isset($accountType)) {
            if ($accountType == Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_GOV_CLOUD) {
                return [Aws::REGION_US_GOV_WEST_1  => 'us-gov-west-1 (GovCloud US)'];
            }

            if ($accountType == Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_CN_CLOUD) {
                return [Aws::REGION_CN_NORTH_1  => 'cn-north-1 (China)'];
            }
        } else {
            // For admin (when no environment defined) we need to show govcloud and chinacloud locations to be able to manage images.
            $retval = array_merge($retval, [
                Aws::REGION_CN_NORTH_1     => 'cn-north-1 (China)',
                Aws::REGION_US_GOV_WEST_1  => 'us-gov-west-1 (GovCloud US)',
            ]);
        }

        return $retval;
    }

}
