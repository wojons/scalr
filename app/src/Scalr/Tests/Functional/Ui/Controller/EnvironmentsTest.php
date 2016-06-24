<?php

namespace Scalr\Tests\Functional\Ui\Controller;

use Scalr\Tests\WebTestCase;

/**
 * Functional test for the Scalr_UI_Controller_Environments class.
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    18.09.2013
 */
class EnvironmentsTest extends WebTestCase
{

    const ENV_NAME = 'senv';

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        if (!$this->getUser()->canManageAcl() && !$this->getUser()->isTeamOwner()) {
            $this->markTestSkipped('Specified test user is not allowed to manage environments.');
        }
    }

    /**
     * @test
     */
    public function testXListEnvironmentsAction()
    {
        $response = $this->internalRequest('/environments/xListEnvironments');
        $this->assertResponseDataHasKeys(array(
            'id'        => $this->logicalNot($this->isEmpty()),
            'name'      => $this->isType('string'),
            'dtAdded'   => $this->logicalOr($this->isType('null'), $this->matchesRegularExpression('/.+,\s[\d]{4}\s[\d]{2}:[\d]{2}:[\d]{2}$/')),
            'status'    => $this->logicalOr($this->equalTo('Active'), $this->equalTo('Inactive')),
            'platforms' => $this->isType('string')
        ), $response, true);

        $createdEnvironmentName = self::getTestName(self::ENV_NAME);

        foreach ($response['data'] as $v) {
            $this->_getInfoAction($v['id']);
            if ($this->getUser()->isAccountOwner()) {
                if ($v['name'] == $createdEnvironmentName) {
                    $this->_removeTestEnv($v['id']);
                    break;
                }
            }
        }
    }

    protected function _getInfoAction($envId)
    {
        $res = $this->internalRequest('/environments/xGetInfo?envId=' . intval($envId));
        $this->assertTrue(isset($res['success']) && $res['success']);
        $this->arrayHasKey('environment', $res);
        $this->assertInternalType('array', $res['environment']);
        $v = $res['environment'];
        $this->assertNotEmpty($v['id']);
        $this->assertNotEmpty($v['name']);
        $this->assertArrayHasKey('params', $v);
        $this->assertArrayHasKey('enabledPlatforms', $v);
    }

    /**
     * Removes created in this test environment
     *
     * @param   int    $envId  The identifier of the created environment
     */
    protected function _removeTestEnv($envId)
    {
        $response = $this->internalRequest('/environments/xRemove?envId=' . $envId);
        $this->assertTrue(isset($response['success']) && $response['success']);
    }

    /**
     * @test
     * @depends testXListEnvironmentsAction
     */
    public function testXCreateAction()
    {
        if (!$this->getUser()->isAccountOwner()) {
            $this->markTestSkipped("Specified test user is not allowed to create environments. It should be account owner.");
        }

        $createdEnvironmentName = self::getTestName(self::ENV_NAME);

        $res = $this->internalRequest('/environments/xCreate', array(
            'name' => $createdEnvironmentName,
        ));

        $this->assertArrayHasKey('success', $res);
        $this->assertArrayHasKey('env', $res);
        $this->assertInternalType('array', $res['env']);
        $this->assertArrayHasKey('name', $res['env']);
        $this->assertArrayHasKey('id', $res['env']);
        $this->assertEquals(true, $res['success']);
        $this->assertEquals($createdEnvironmentName, $res['env']['name']);
        $createdEnvId = $res['env']['id'];
        $this->assertNotEmpty($createdEnvId);

        //Test saving
        $par = \Scalr_Environment::SETTING_TIMEZONE;
        $res2 = $this->internalRequest('/environments/xSave', array(
            'envId' => $createdEnvId,
            $par    => 'Europe/Simferopol',
        ));
        $this->assertTrue(isset($res2['success']) && $res2['success']);

        //Test removal
        $this->_removeTestEnv($createdEnvId);
    }
}