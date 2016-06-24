<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Model\Entity\Account;
use Scalr\Tests\Functional\Api\V2\ApiTest;

/**
 * Environment
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.12 (02.02.2016)
 */
class Environment extends ApiFixture
{
    /**
     * Api account namespace
     */
    const API_NAMESPACE = '\Scalr\Api\Service\Account';

    /**
     * CostCenters created for specifics request
     */
    const TEST_DATA_COST_CENTER = 'CostCenter';

    /**
     * Account Cost Center created for specifics request
     */
    const TEST_DATA_ACCOUNT_COST_CENTER = 'AccountCostCenter';

    /**
     * Environment created for specifics request
     */
    const TEST_DATA_ENVIRONMENT = 'Environment';

    /**
     * Environment property created for specifics request
     */
    const TEST_DATA_ENVIRONMENT_PROPERTY = 'EnvironmentProperty';

    /**
     * Team which corresponds to Account
     */
    const TEST_DATA_ACCOUNT_TEAM = 'AccountTeam';

    /**
     * Account team which corresponds to environments
     */
    const TEST_DATA_ACCOUNT_TEAM_ENV = 'AccountTeamEnv';

    /**
     * Users which corresponds to team
     */
    const TEST_DATA_ACCOUNT_TEAM_USERS = 'AccountTeamUsers';

    /**
     * Farm created for specifics request
     */
    const TEST_DATA_FARM = 'Farm';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'EnvironmentData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'environment';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (isset($this->sets[static::TEST_DATA_COST_CENTER])) {
            $this->prepareCostCenter(static::TEST_DATA_COST_CENTER);
        }

        if (isset($this->sets[static::TEST_DATA_ACCOUNT_COST_CENTER])) {
            $this->prepareData(static::TEST_DATA_ACCOUNT_COST_CENTER);
            $this->prepareAccountCostCenter(static::TEST_DATA_ACCOUNT_COST_CENTER);
        }

        if (isset($this->sets[static::TEST_DATA_ENVIRONMENT])) {
            $this->prepareData(static::TEST_DATA_ENVIRONMENT_PROPERTY);
            $this->prepareData(static::TEST_DATA_ENVIRONMENT);
            foreach ($this->sets[static::TEST_DATA_ENVIRONMENT] as &$envData) {
                $envData['accountId'] = static::$user->getAccountId();
                /* @var $environment Account\Environment() */
                $environment = new Account\Environment();
                if (isset($envData['properties'])) {
                    foreach ($envData['properties'] as $property => $value) {
                        $environment->setProperty($property, $value);
                    }
                    unset($envData['properties']);
                }
                $environment = ApiTest::createEntity($environment, $envData, ['accountId']);
                $envData['id'] = $environment->id;
            }
        }

        if (isset($this->sets[static::TEST_DATA_ACCOUNT_TEAM])) {
            $this->prepareAccountTeam(static::TEST_DATA_ACCOUNT_TEAM);
        }

        if (isset($this->sets[static::TEST_DATA_ACCOUNT_TEAM_ENV])) {
            $this->prepareData(static::TEST_DATA_ACCOUNT_TEAM_ENV);
            foreach ($this->sets[static::TEST_DATA_ACCOUNT_TEAM_ENV] as $teamEnvData) {
                ApiTest::createEntity(new Account\TeamEnvs(), $teamEnvData);
            }
        }

        if (isset($this->sets[static::TEST_DATA_ACCOUNT_TEAM_USERS])) {
            $this->prepareData(static::TEST_DATA_ACCOUNT_TEAM_USERS);
            foreach ($this->sets[static::TEST_DATA_ACCOUNT_TEAM_USERS] as &$teamUserData) {
                $teamUserData['userId'] = static::$testUserId;
                ApiTest::createEntity(new Account\TeamUser(), $teamUserData);
            }
        }

        if (isset($this->sets[static::TEST_DATA_FARM])) {
            $this->prepareFarm(static::TEST_DATA_FARM);
        }

        $this->prepareData(static::TEST_DATA);
    }
}