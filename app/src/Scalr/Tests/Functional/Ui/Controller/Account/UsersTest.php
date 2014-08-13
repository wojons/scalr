<?php

namespace Scalr\Tests\Functional\Ui\Controller\Account;

use Scalr\Tests\WebTestCase;

/**
 * Functional test for the Scalr_UI_Controller_Account_Users class.
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    18.09.2013
 */
class UsersTest extends WebTestCase
{

    const USER_FULLNAME = 'spuser';

    const USER_EMAIL_DOMAIN = 'test.local';

    protected static $_createdUserId;

    protected function _chk()
    {
        return array(
            'id'           => $this->isType('numeric'),
            'status'       => $this->logicalOr($this->equalTo(null), $this->logicalAnd($this->isType('string'), $this->logicalOr($this->equalTo('Active'), $this->equalTo('Inactive')))),
            'email'        => $this->isType('string'),
            'fullname',
            'dtcreated'    => $this->matchesRegularExpression('/.+\s\d{4}\s\d{2}:\d{2}:\d{2}/'),
            'dtlastlogin'  => $this->logicalOr($this->equalTo('Never'), $this->matchesRegularExpression('/.+\s\d{4}\s\d{2}:\d{2}:\d{2}/')),
            'type'         => $this->logicalOr(
                                  $this->equalTo('Team Owner'),
                                  $this->equalTo('Account Owner'),
                                  $this->equalTo('Team User')
                              ),
            'teams'        => $this->isType('array'),
        );
    }

    /**
     * Gets test user's email
     *
     * @return   string
     */
    protected static function _getCreatedUserEmail()
    {
        $username  = self::getTestName(self::USER_FULLNAME);
        return $username . '@' . self::USER_EMAIL_DOMAIN;
    }

    /**
     * @test
     */
    public function testXGetListAction()
    {
        $chk = $this->_chk();

        $ret = $this->getUser()->isTeamOwnerInEnvironment($this->getEnvironment()->id);
        $this->assertInternalType('boolean', $ret);

        $response = $this->internalRequest('/account/users/xGetList');
        $this->assertResponseDataHasKeys($chk, $response, true, 'usersList');

        $userEmail = self::_getCreatedUserEmail();

        if ($this->getUser()->canManageAcl()) {
            foreach ($response['usersList'] as $v) {
                if ($v['email'] == $userEmail) {
                    //Removes previously created user
                    $this->removeUser($v['id']);
                    break;
                }
            }
        }
    }

    /**
     * Removes user by specified identifier
     * @param   int     $userId  The identifier of the user
     */
    public function removeUser($userId)
    {
        $r = $this->internalRequest('/account/users/xRemove?userId=' . $userId);
        $this->assertArrayHas(true, 'success', $r);
    }

    /**
     * @test
     * @depends testXGetListAction
     */
    public function testXSaveAcion()
    {
        if (!$this->getUser()->canManageAcl()) {
            $this->markTestSkipped('Specified test user is not allowed to manage users.');
        }

        $username  = self::getTestName(self::USER_FULLNAME);
        $userEmail = self::_getCreatedUserEmail();

        $response = $this->internalRequest('/account/users/xSave', array(
            'email'    => $userEmail,
            'fullname' => $username,
        ));

        $this->assertArrayHas(true, 'success', $response, 'Cannot create user');
        $this->assertTrue(isset($response['user']['id']));
        $this->assertEquals($userEmail, $response['user']['email']);

        self::$_createdUserId = $response['user']['id'];

        $this->removeUser(self::$_createdUserId);

        self::$_createdUserId = null;
    }

    /**
     * @test
     */
    public function testXGetApiKeysAction()
    {
        if (!$this->getUser()->canManageAcl()) {
            $this->markTestSkipped('Specified test user is not allowed to get api keys.');
        }
        $r = $this->internalRequest('/account/users/xGetApiKeys?userId=' . $this->getUser()->getId());
        if (!empty($r['success'])) {
            $this->assertArrayHasKey('accessKey', $r);
            $this->assertArrayHasKey('secretKey', $r);
        }
    }

    /**
     * @test
     */
    public function testXListUsersAction()
    {
        $acl = \Scalr::getContainer()->acl;

        $chk = $this->_chk();

        //Without any filter
        $response = $this->internalRequest('/account/users/xListUsers');
        $this->assertResponseDataHasKeys($chk, $response, true);

        //Filter by team
        $teams = $this->getUser()->getTeams();
        if (!empty($teams)) {
            $sr = $this->internalRequest('/account/users/xListUsers?teamId=' . $teams[0]['id']);
            $this->assertResponseDataHasKeys($chk, $sr, true);
        }

        //Filter by role
        $roles = $acl->getAccountRoles($this->getUser()->getAccountId());

        if (!empty($roles)) {
            list ($accountRoleId) = each($roles);
        } else {
            $accountRoleId = 'anything';
        }

        $sr2 = $this->internalRequest('/account/users/xListUsers?groupPermissionId=' . urlencode($accountRoleId));
        $this->assertResponseDataHasKeys($chk, $sr2, true);
    }
}