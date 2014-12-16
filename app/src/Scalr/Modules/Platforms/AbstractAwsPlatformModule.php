<?php
namespace Scalr\Modules\Platforms;

use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Service\Aws;
use Scalr\Modules\AbstractPlatformModule;

abstract class AbstractAwsPlatformModule extends AbstractPlatformModule
{

    /**
     * Gets the list of available locations
     *
     * @return  array Returns the list of available locations looks like array(location => description)
     */
    public function getLocations(\Scalr_Environment $environment = null)
    {
        $retval = array(
            Aws::REGION_US_EAST_1      => 'AWS / us-east-1 (N. Virginia)',
            Aws::REGION_US_WEST_1      => 'AWS / us-west-1 (N. California)',
            Aws::REGION_US_WEST_2      => 'AWS / us-west-2 (Oregon)',
            Aws::REGION_EU_WEST_1      => 'AWS / eu-west-1 (Ireland)',
            Aws::REGION_EU_CENTRAL_1   => 'AWS / eu-central-1 (Frankfurt)',
            Aws::REGION_SA_EAST_1      => 'AWS / sa-east-1 (Sao Paulo)',
            Aws::REGION_AP_SOUTHEAST_1 => 'AWS / ap-southeast-1 (Singapore)',
            Aws::REGION_AP_SOUTHEAST_2 => 'AWS / ap-southeast-2 (Sydney)',
            Aws::REGION_AP_NORTHEAST_1 => 'AWS / ap-northeast-1 (Tokyo)'
        );

        if ($environment instanceof \Scalr_Environment && $this instanceof Ec2PlatformModule) {
            if ($environment->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_TYPE) == Ec2PlatformModule::ACCOUNT_TYPE_GOV_CLOUD) {
                return [Aws::REGION_US_GOV_WEST_1  => 'AWS / us-gov-west-1 (GovCloud US)'];
            }

            if ($environment->getPlatformConfigValue(Ec2PlatformModule::ACCOUNT_TYPE) == Ec2PlatformModule::ACCOUNT_TYPE_CN_CLOUD) {
                return [Aws::REGION_CN_NORTH_1  => 'AWS / cn-north-1 (China)'];
            }
        } else {
            // For admin (when no environment defined) we need to show govcloud and chinacloud locations to be able to manage images.
            $retval = array_merge($retval, [
                Aws::REGION_CN_NORTH_1     => 'AWS / cn-north-1 (China)',
                Aws::REGION_US_GOV_WEST_1  => 'AWS / us-gov-west-1 (GovCloud US)',
            ]);
        }

        return $retval;
    }
}
