<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

/**
 * Class FarmRole
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11.10 (16.02.2016)
 */
class FarmRole extends ApiFixture
{
    /**
     * Farm Role Image created for specifics request
     */
    const TEST_DATA_ROLE_IMAGE = 'RoleImage';

    /**
     * Farm Roles created for specifics request
     */
    const TEST_DATA_FARM_ROLE = 'FarmRole';

    /**
     * Farm created for specifics request
     */
    const TEST_DATA_FARM = 'Farm';

    /**
     * Role categories created for specifics request
     */
    const TEST_DATA_ROLE_CATEGORY = 'RoleCategory';

    /**
     * Role created for specifics request
     */
    const TEST_DATA_ROLE = 'Role';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'FarmRolesData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'farmRole';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_ROLE_CATEGORY])) {
            $this->prepareRoleCategory(static::TEST_DATA_ROLE_CATEGORY);
        }

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

        $this->prepareData(static::TEST_DATA);
    }
}