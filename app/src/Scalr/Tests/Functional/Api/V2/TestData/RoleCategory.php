<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Model\Entity;
use Scalr\Tests\Functional\Api\V2\ApiTest;

/**
 * Class RoleCategory
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11.8 (29.01.2016)
 */
class RoleCategory extends ApiFixture
{
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
    const TEST_DATA = 'RoleCategoryData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'roleCategory';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_ROLE_CATEGORY])) {
            $this->prepareRoleCategory(static::TEST_DATA_ROLE_CATEGORY, 1);
        }

        if (!empty($this->sets[static::TEST_DATA_ROLE])) {
            $this->prepareData(static::TEST_DATA_ROLE);
            $this->prepareRole(static::TEST_DATA_ROLE);
        }
        $this->prepareData(static::TEST_DATA);
    }
}