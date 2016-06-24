<?php
namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Model\Entity;
use Scalr\Tests\Functional\Api\V2\ApiTest;

/**
 * Class RoleCategory
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since 5.11 (29.01.2016)
 */
class Role extends ApiFixture
{
    /**
     * Role created for specifics request
     */
    const TEST_DATA_ROLE = 'Role';

    /**
     * Role categories created for specifics request
     */
    const TEST_DATA_ROLE_CATEGORY = 'RoleCategory';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'RoleData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'role';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (!empty($this->sets[static::TEST_DATA_ROLE])) {
            $this->prepareRole(static::TEST_DATA_ROLE);
        }

        if (!empty($this->sets[static::TEST_DATA_ROLE_CATEGORY])) {
            $this->prepareRoleCategory(static::TEST_DATA_ROLE_CATEGORY);
        }
        $this->prepareData(static::TEST_DATA);
    }
}