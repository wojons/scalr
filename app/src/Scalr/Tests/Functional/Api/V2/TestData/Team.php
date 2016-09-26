<?php

namespace Scalr\Tests\Functional\Api\V2\TestData;

use Scalr\Model\Entity\AclRole;
use Scalr\Tests\Functional\Api\V2\ApiTest;

/**
 * Team
 *
 * @author Andrii Penchuk <a.penchuk@scalr.com>
 * @since  5.11.19 (11.04.2016)
 */
class Team extends ApiFixture
{
    /**
     * Api account namespace
     */
    const API_NAMESPACE = '\Scalr\Api\Service\Account';

    /**
     * ACL Roles created for test Teams
     */
    const TEST_DATA_ACL_ROLE = 'AclRole';

    /**
     * Account Teams created for specifics request
     */
    const TEST_DATA_ACCOUNT_TEAM = 'Team';

    /**
     * {@inheritdoc}
     */
    const TEST_DATA = 'TeamData';

    /**
     * {@inheritdoc}
     */
    protected $adapterName = 'team';

    /**
     * {@inheritdoc}
     * @see ApiFixture::prepareTestData()
     */
    public function prepareTestData()
    {
        if (isset($this->sets[static::TEST_DATA_ACL_ROLE])) {
            foreach ($this->sets[static::TEST_DATA_ACL_ROLE] as &$aclRoleData) {
                $aclRoleData['accountId'] = static::$user->getAccountId();
                /* @var $aclRole AclRole */
                $aclRole = ApiTest::createEntity(new AclRole(), $aclRoleData);
                $aclRoleData['accountRoleId'] = $aclRole->accountRoleId;
            }
        }

        if (isset($this->sets[static::TEST_DATA_ACCOUNT_TEAM])) {
            $this->prepareData(static::TEST_DATA_ACCOUNT_TEAM);
            $this->prepareAccountTeam(static::TEST_DATA_ACCOUNT_TEAM);
        }
        $this->prepareData(static::TEST_DATA);
    }
}