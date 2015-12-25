<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
use Scalr\Tests\Functional\Api\V2\ApiV2Test;
use Scalr_Environment;

/**
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.6.14 (08.12.2015)
 */
class Projects extends TestDataFixtures
{
    /**
     *
     */
    const TEST_DATA_PROJECTS = 'Projects';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'ProjectsData';

    /**
     * {@inheritdoc}
     */
    protected $entityClass = 'Scalr\Stats\CostAnalytics\Entity\ProjectEntity';

    /**
     * {@inheritdoc}
     * @see TestDataFixtures::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_PROJECTS])) {
            $ccId = Scalr_Environment::init()->loadById(static::$env->id)->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID);
            foreach ($this->sets[static::TEST_DATA_PROJECTS] as &$projectData) {
                $projectData['envId'] = self::$env->id;
                $projectData['accountId'] = self::$user->getAccountId();
                $projectData['createdById'] = self::$user->id;
                $projectData['createdByEmail'] = self::$user->email;
                $projectData['ccId'] = $ccId;

                /* @var $project ProjectEntity */
                $project = ApiV2Test::createEntity(new ProjectEntity(), $projectData);
                $project->setCostCenter(\Scalr::getContainer()->analytics->ccs->get($projectData['ccId']));
                $project->saveProperty(ProjectPropertyEntity::NAME_BILLING_CODE, $projectData['name']);
                $project->save();
                $projectData['projectId'] = $project->projectId;

                // to Delete project Properties
                ApiV2Test::toDelete(
                    ProjectPropertyEntity::class,
                    [$project->projectId, $project->getProperty(ProjectPropertyEntity::NAME_BILLING_CODE)]
                );
            }
        }
    }
}