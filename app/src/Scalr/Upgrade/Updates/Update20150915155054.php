<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Model\Entity\Account\EnvironmentProperty;
use Scalr\Modules\PlatformFactory;
use Scalr\Modules\Platforms\Ec2\Ec2PlatformModule;
use Scalr\Service\Aws;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity;

class Update20150915155054 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = 'fc1b9c60-c91a-4905-86e8-dbff523fd81e';

    protected $depends = [];

    protected $description = "Initialize ec2.detailed_billing.region for environments with detailed billing";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return false;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('client_environment_properties');
    }

    protected function run1($stage)
    {
        if (\Scalr::getContainer()->analytics->enabled) {
            $properties = EnvironmentProperty::find([['name' => Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_BUCKET]]);

            foreach ($properties as $property) {
                /* @var $property EnvironmentProperty */
                $environment = \Scalr_Environment::init()->loadById($property->envId);
                $accountType = $environment->getPlatformConfigValue(Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE);

                if ($accountType == Entity\CloudCredentialsProperty::AWS_ACCOUNT_TYPE_REGULAR) {
                    $region = Aws::REGION_US_EAST_1;
                } else {
                    $platformModule = PlatformFactory::NewPlatform(\SERVER_PLATFORMS::EC2);
                    /* @var $platformModule Ec2PlatformModule */
                    $locations = array_keys($platformModule->getLocationsByAccountType($accountType));
                    $region = reset($locations);
                }

                $environment->setPlatformConfig([Entity\CloudCredentialsProperty::AWS_DETAILED_BILLING_REGION => $region]);
            }
        }
    }
}