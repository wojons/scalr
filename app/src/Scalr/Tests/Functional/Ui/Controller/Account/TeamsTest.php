<?php

namespace Scalr\Tests\Functional\Ui\Controller\Account;

use Scalr\Tests\WebTestCase;

/**
 * Functional test for the Scalr_UI_Controller_Account_Teams class.
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    03.09.2013
 */
class TeamsTest extends WebTestCase
{

    const NAME_TEAM = 'team';

    public static $testTeamId;

	/**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        if (!$this->getUser()->canManageAcl()) {
            $this->markTestSkipped(sprintf(
                'Specified test user is not allowed to manage Teams. ' .
                'Please provide account owner or account admin user for the scalr.phpunit.userid parameter.'
            ));
        }
    }

	/**
     * @test
     */
    public function testXListTeams()
    {
        $response = $this->internalRequest('/account/teams/xListTeams');
        $this->assertResponseDataHasKeys(array(
            'id'           => $this->isType('numeric'),
            'name'         => $this->isType('string'),
            'environments' => $this->isType('array'),
            'owner'        => $this->isType('array'),
            'ownerTeam'    => $this->isType('boolean'),
            'groups'       => $this->isType('array'),
            'users'        => $this->isType('array')
        ), $response, true);

        foreach ($response['data'] as $t) {
            if ($t['name'] === self::getTestName(self::NAME_TEAM)) {
                //We have to remove test team as it exists by some reason.
                $this->removeTeam($t['id']);
            }
        }
    }

    /**
     * @test
     * @depends testXListTeams
     */
    public function testXCreate()
    {
        if (!$this->getUser()->isAccountOwner()) {
            $this->markTestSkipped();
        }

        $response = $this->internalRequest('/account/teams/xCreate', array(
            'name'    => self::getTestName(self::NAME_TEAM),
            'ownerId' => $this->getUser()->getId(),
        ));
        $this->assertInternalType('array', $response);
        $this->assertNotEmpty($response);
        $this->assertArrayHasKey('teamId', $response);
        $this->assertNotEmpty($response['teamId']);

        self::$testTeamId = $response['teamId'];
    }

    /**
     * Removes team
     *
     * @param    int    $teamId The identifier of the Team
     */
    public function removeTeam($teamId)
    {
        $response = $this->internalRequest('/account/teams/xRemove', array(
            'teamId' => $teamId,
        ));
        $this->assertTrue($response['success']);
    }

    /**
     * @test
     * @depends testXCreate
     */
    public function testXRemove($teamId = null)
    {
        $this->assertNotEmpty(self::$testTeamId);
        $this->removeTeam(self::$testTeamId);
        self::$testTeamId = null;
    }

    /**
     * @test
     * @depends testXRemove
     */
    public function testTeamOwnerUserHasBeenAdjustedWithAccountAdminType()
    {
        $user = \Scalr_Account_User::init();
        $user->loadById($this->getUser()->getId());
        $this->assertTrue($user->isAccountAdmin() || $user->isAccountOwner());
    }

	/**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::tearDown()
     */
    protected function tearDown()
    {
        parent::tearDown();
    }
}