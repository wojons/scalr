<?php

namespace Scalr\Tests\Functional\Api\V1;
use Scalr\Tests\Functional\Api\V1;

class UsersTest extends ApiTestCase
{
    const TEAM_NAME = 'team';
    const USER_NAME = 'user';

    public function testListUsers()
    {
        $content = $this->request("/account/users/xListUsers");
        $this->assertResponseDataHasKeys(array('id', 'email', 'fullname', 'status', 'dtcreated', 'dtlastlogin', 'type', 'comments'), $content);
    }

    public function testUsers()
    {
        if (!$this->getUser()->canManageAcl()) {
            $this->markTestSkipped('Specified test user cannot manage users.');
        }

        // remove previous test data
        $user = new \Scalr_Account_User();
        $user = $user->loadByEmail(self::getTestName(self::USER_NAME) . '@scalr.com', $this->getEnvironment()->clientId);
        if ($user)
            $user->delete();

        $team = new \Scalr_Account_Team();
        $result = $team->loadByFilter(array(
            'name' => self::getTestName(self::TEAM_NAME),
            'accountId' => $this->getEnvironment()->clientId
        ));
        if (count($result)) {
            foreach ($result as $e) {
                $obj = new \Scalr_Account_Team();
                $obj->loadById($e['id']);
                $obj->delete();
            }
        }

        // create
        $content = $this->request('/account/users/xSave', array(
            'email' => self::getTestName(self::USER_NAME) . '@scalr.com',
            'password' => '123',
            'status'   => 'Active',
            'fullname' => 'phpunit test user',
            'comments' => 'For testing'
        ));

        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('user', $content);
        $this->assertArrayHasKey('id', $content['user']);
        $this->assertArrayHasKey('email', $content['user']);
        $this->assertArrayHasKey('fullname', $content['user']);

        $createUserId = $content['user']['id'];

        $content = $this->request('/account/users/xGetInfo', array('userId' => $createUserId));
        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('user', $content);
        $this->assertArrayHasKey('id', $content['user']);
        $this->assertArrayHasKey('email', $content['user']);
        $this->assertArrayHasKey('fullname', $content['user']);
        $this->assertArrayHasKey('status', $content['user']);
        $this->assertArrayHasKey('comments', $content['user']);

        // modify some settings
        $content = $this->request('/account/users/xSave', array(
            'id' => $createUserId,
            'email' => self::getTestName(self::USER_NAME) . '@scalr.com',
            'status' => 'Inactive',
            'fullname' => 'phpunit test user',
            'comments' => 'For testing'
        ));

        $this->assertTrue($content['success']);

        $content = $this->request('/account/users/xGetInfo', array('userId' => $createUserId));
        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('user', $content);
        $this->assertEquals($content['user']['status'], 'Inactive');

        // get api keys
        $content = $this->request('/account/users/xGetApiKeys', array('userId' => $createUserId));
        $this->assertFalse($content['success']);

        // remove user
        $content = $this->request("/account/users/xRemove", array('userId' => $createUserId));
        $this->assertTrue($content['success']);

        // create with api enabled
        $content = $this->request('/account/users/xSave', array(
            'email' => self::getTestName(self::USER_NAME) . '@scalr.com',
            'password' => '123',
            'status' => 'Active',
            'fullname' => 'phpunit test user',
            'comments' => 'For testing',
            'enableApi' => true
        ));

        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('user', $content);
        $createUserId = $content['user']['id'];

        // get api keys
        $content = $this->request('/account/users/xGetApiKeys', array('userId' => $createUserId));
        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('accessKey', $content);
        $this->assertArrayHasKey('secretKey', $content);

        if ($this->getUser()->isAccountOwner()) {
            //create team
            $content = $this->request("/account/teams/xCreate", array(
                'name' => self::getTestName(self::TEAM_NAME),
                'ownerId' => $createUserId,
                'envId' => $this->getEnvironment()->id
            ));

            $this->assertTrue($content['success']);
            $this->assertArrayHasKey('teamId', $content);
            $createTeamId = $content['teamId'];

            // remove team
            $content = $this->request('/account/teams/xRemove', array('teamId' => $createTeamId));
            $this->assertTrue($content['success']);
        }

        // remove user
        $content = $this->request('/account/users/xRemove', array('userId' => $createUserId));
        $this->assertTrue($content['success']);
    }
}
