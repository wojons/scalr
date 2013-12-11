<?php

namespace Scalr\Tests\Functional\Ui\Controller\Admin;

use Scalr\Tests\WebTestCase;

/**
 * Functional test for the Scalr_UI_Controller_Admin_Accounts class.
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    19.09.2013
 */
class AccountsTest extends WebTestCase
{

    const NAME_ACCOUNT = 'acc';

    const OWNER_USERNAME = 'owner';

    protected static $_createdAccountId;

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::setUp()
     */
    protected function setUp()
    {
        parent::setUp();
        if (!$this->getUser()->isScalrAdmin()) {
            $this->markTestSkipped();
        }
    }

	/**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::isAdminUserTestClass()
     */
    protected function isAdminUserTestClass()
    {
        return true;
    }

    /**
     * Gets account name
     *
     * @return string
     */
    protected function getCreatedAccountName()
    {
        return self::getTestName(self::NAME_ACCOUNT);
    }

    /**
     * Gets account owner email to create
     *
     * @return string
     */
    protected function getCreatedOwnerEmail()
    {
        return self::getTestName(self::OWNER_USERNAME) . "@scalr.local";
    }

    /**
     * @test
     */
	public function testXListAccountsAction()
    {
        $res = $this->request('/admin/accounts/xListAccounts');
        $this->assertResponseDataHasKeys(array(
            'id'         => $this->logicalNot($this->isEmpty()),
            'name'       => $this->isType('string'),
            'dtadded',
            'status',
            'ownerEmail' => $this->matchesRegularExpression('/^.+@[\.\w\d]+$/'),
            'isTrial',
            'envs'       => $this->matchesRegularExpression('/^\d+$/'),
            'limitEnvs',
            'farms'      => $this->matchesRegularExpression('/^\d+$/'),
            'limitFarms',
            'users'      => $this->matchesRegularExpression('/^\d+$/'),
            'limitUsers',
            'servers'    => $this->matchesRegularExpression('/^\d+$/'),
            'limitServers',
            'dnsZones'   => $this->matchesRegularExpression('/^\d+$/'),
        ), $res, true);

        foreach ($res['data'] as $v) {
            if ($v['name'] == $this->getCreatedAccountName()) {
                try {
                    //Tries to remove previously created account
                    $acc = \Scalr_Account::init()->loadById($v['id']);
                    $acc->delete();
                } catch (\Exception $e) {
                }
            }
        }
    }

    /**
     * @test
     * @depends testXListAccountsAction
     */
    public function testXSaveAction()
    {
        $r = $this->request("/admin/accounts/xSave", array(
            'name'          => $this->getCreatedAccountName(),
            'comments'      => 'phpunt test account',
            'ownerEmail'    => $this->getCreatedOwnerEmail(),
            'ownerPassword' => self::getTestName(self::OWNER_USERNAME),
        ));

        $this->assertArrayHas(true, 'success', $r);
        $this->assertArrayHasKey('accountId', $r);
        $this->assertNotEmpty($r['accountId']);

        self::$_createdAccountId = $r['accountId'];
    }

    /**
     * @test
     * @depends testXSaveAction
     */
    public function testXGetInfoAction()
    {
        $r = $this->request('/admin/accounts/xGetInfo', array('accountId' => self::$_createdAccountId));
        $this->assertArrayHas(true, 'success', $r);
        $this->assertArrayHasKey('account', $r);
        $this->assertInternalType('array', $r['account']);
        $this->assertEquals(self::$_createdAccountId, $r['account']['id']);
        $this->assertEquals($this->getCreatedAccountName(), $r['account']['name']);
    }

    /**
     * @test
     * @depends testXGetInfoAction
     */
    public function testXRemoveAction()
    {
        $r = $this->request('/admin/accounts/xRemove', array('accounts' => array(self::$_createdAccountId)));
        $this->assertArrayHas(true, 'success', $r);
    }
}