<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Model\Entity\GlobalVariable\AccountGlobalVariable;
use Scalr\Tests\Functional\Api\V2\ApiTest;


/**
 * GlobalVariable
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.20 (13.04.2016)
 */
class GlobalVariable extends ApiFixture
{

    /**
     * Farm Roles created for specifics request
     */
    const TEST_DATA_FARM_ROLE = 'FarmRole';

    /**
     * Farm created for specifics request
     */
    const TEST_DATA_FARM = 'Farm';

    /**
     * ClovalVariables created for specifics request
     */
    const TEST_DATA_ACCOUNT_GV = 'AccountGlobalVariable';

    /**
     * Role created for specifics request
     */
    const TEST_DATA_ROLE = 'Role';

    /**
     * Farm Role Image created for specifics request
     */
    const TEST_DATA_ROLE_IMAGE = 'RoleImage';

    /**
     * GV test params
     */
    const TEST_DATA_PARAMS = 'GlobalVariableParams';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'GlobalVariableData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'globalVariable';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_ROLE])) {
            $this->prepareData(static::TEST_DATA_ROLE);
            $this->prepareRole(static::TEST_DATA_ROLE);
        }

        if (!empty($this->sets[static::TEST_DATA_ROLE_IMAGE])) {
            $this->prepareData(static::TEST_DATA_ROLE_IMAGE);
            $this->prepareRoleImage(static::TEST_DATA_ROLE_IMAGE);
        }

        if (!empty($this->sets[static::TEST_DATA_FARM])) {
            $this->prepareFarm(static::TEST_DATA_FARM);
        }

        if (!empty($this->sets[static::TEST_DATA_FARM_ROLE])) {
            $this->prepareData(static::TEST_DATA_FARM_ROLE);
            $this->prepareFarmRole(static::TEST_DATA_FARM_ROLE);
        }

        if (!empty($this->sets[static::TEST_DATA_ACCOUNT_GV])) {
            foreach ($this->sets[static::TEST_DATA_ACCOUNT_GV] as &$gvData) {
                if (empty($gvData['accountId'])) {
                    $gvData['accountId'] = static::$user->getAccountId();
                }
                ApiTest::createEntity(new AccountGlobalVariable(), $gvData);
            }
        }
        $this->prepareData(static::TEST_DATA);
        $this->prepareData(static::TEST_DATA_PARAMS);
    }
}