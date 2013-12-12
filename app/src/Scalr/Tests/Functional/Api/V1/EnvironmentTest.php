<?php

namespace Scalr\Tests\Functional\Api\V1;
use Scalr\Tests\Functional\Api\V1;

class EnvironmentTest extends ApiTestCase
{
    const ENV_NAME = 'env';

	public function testListEnvironments()
    {
        if (!$this->getUser()->canManageAcl() && !$this->getUser()->isTeamOwner()) {
            $this->markTestSkipped('Specified test user cannot view environments list.');
        }

        $content = $this->request('/environments/xListEnvironments');
        $this->assertResponseDataHasKeys(array('id', 'name', 'dtAdded', 'status'), $content);
    }

    public function testEnvironment()
    {
        if (!$this->getUser()->isAccountOwner()) {
            $this->markTestSkipped('Specified user cannot create new environments.');
        }

        $createdEnvId = 0;

        // remove previous test envs
        $env = new \Scalr_Environment();
        $result = $env->loadByFilter(array(
            'clientId' => $this->getEnvironment()->clientId,
            'name' => self::getTestName(self::ENV_NAME)
        ));

        if (count($result)) {
            foreach ($result as $e) {
                $obj = new \Scalr_Environment();
                $obj->loadById($e['id']);
                $obj->delete();
            }
        }

        // create new
        $content = $this->request('/environments/xCreate', array(
            'name' => self::getTestName(self::ENV_NAME)
        ));
        $this->assertTrue($content['success']);
        if ($content['env']) {
            $createdEnvId = $content['env']['id'];
        }


        // create failure
        $content = $this->request('/environments/xCreate', array('name' => ''));
        $this->assertFalse($content['success']);

        // get info about test env
        $content = $this->request('/environments/' . $createdEnvId . '/xGetInfo');
        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('environment', $content);
        $this->assertArrayHasKey('id', $content['environment']);
        $this->assertArrayHasKey('name', $content['environment']);
        $this->assertArrayHasKey('params', $content['environment']);
        $this->assertArrayHasKey('enabledPlatforms', $content['environment']);

        // get info about env failure
        $content = $this->request('/environments/' . '-3' . '/xGetInfo');
        $this->assertFalse($content['success']);

        // check new env in list
        $content = $this->request('/environments/xListEnvironments');
        $this->assertTrue($content['success']);
        $this->assertArrayHasKey('data', $content);

        $flag = false;
        foreach($content['data'] as $value) {
            if ($value['name'] == self::getTestName(self::ENV_NAME))
                $flag = true;
        }

        $this->assertTrue($flag, 'Created environment not found in list');

        // test platforms save
        $this->envTestEc2($createdEnvId);


        $content = $this->request('/environments/xRemove', array('envId' => $createdEnvId));
        $this->assertTrue($content['success']);
    }

    public function envTestEc2($envId)
    {
        if (!$this->getEnvironment()->isPlatformEnabled(\SERVER_PLATFORMS::EC2)) {
            $this->markTestSkipped('Skip EC2 platform test');
        }

        $env = $this->getEnvironment();
        // enable EC2
        $content = $this->request('/environments/' . $envId . '/platform/xSaveEc2', array(
            'ec2.is_enabled' => '1',
            'ec2.account_id' => $env->getPlatformConfigValue(\Modules_Platforms_Ec2::ACCOUNT_ID),
            'ec2.access_key' => $env->getPlatformConfigValue(\Modules_Platforms_Ec2::ACCESS_KEY),
            'ec2.secret_key' => $env->getPlatformConfigValue(\Modules_Platforms_Ec2::SECRET_KEY)
        ), 'POST', array(), array());

        $this->assertFalse($content['success']);

        // disable EC2
        $content = $this->request('/environments/' . $envId . '/platform/xSaveEc2', array(
            'ec2.is_enabled' => ''
        ));

        $this->assertTrue($content['success']);
        $this->assertFalse($content['enabled']);
    }
}
