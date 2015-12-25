<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Model\Entity\FarmRole;
use Scalr\Model\Entity\FarmRoleSetting;
use Scalr\Model\Collections\SettingsCollection;
use Scalr\Tests\Functional\Api\V2\ApiV2Test;

/**
 * Class FarmRoles
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (07.12.2015)
 */
class FarmRoles extends TestDataFixtures
{
    const TEST_DATA_FARM_ROLES = 'FarmRoles';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'FarmRolesData';

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Model\Entity\FarmRole';

    /**
     * {@inheritdoc}
     * @see TestDataFixtures::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_FARM_ROLES])) {
            foreach ($this->sets[static::TEST_DATA_FARM_ROLES] as &$frData) {
                /* @var $farmRole FarmRole */
                $farmRole = ApiV2Test::createEntity(new FarmRole(), $frData);
                /* @var $settings SettingsCollection */
                $settings = $farmRole->settings;
                $settings->saveSettings([
                    FarmRoleSetting::AWS_INSTANCE_TYPE => 't1.micro',
                    FarmRoleSetting::AWS_AVAIL_ZONE => 'us-east-1d',
                    FarmRoleSetting::SCALING_ENABLED => true,
                    FarmRoleSetting::SCALING_MIN_INSTANCES => 1,
                    FarmRoleSetting::SCALING_MAX_INSTANCES => 2
                ]);
                $frData['farmRoleId'] = $farmRole->id;
            }
        }
    }
}