<?php
namespace Scalr\Tests\Model\Entity\Account;

use Scalr\Tests\TestCase;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity\Account\User\UserSetting;
use Scalr\Model\Entity\Account\Environment;

/**
 * UserTest
 *
 * @author   Vitaliy Demidov <vitaliy@scalr.com>
 * @since    5.4.0 (23.02.2015)
 */
class UserTest extends TestCase
{
    /**
     * @functional
     */
    public function testUser()
    {
        $userId = \Scalr::config('scalr.phpunit.userid');
        $envId = \Scalr::config('scalr.phpunit.envid');

        if (empty($userId) || empty($envId)) {
            $this->markTestSkipped("This test requires valid database connection so it's considered to be functional.");
        }

        /* @var $user User */
        $user = User::findOne([['id' => $userId]]);

        $this->assertInstanceOf('Scalr\Model\Entity\Account\User', $user);

        $email = $user->getSetting(UserSetting::NAME_GRAVATAR_EMAIL);
        $this->assertNotEmpty($email);

        $environment = Environment::findPk($envId);

        $this->assertInstanceOf('Scalr\Model\Entity\Account\Environment', $environment);

        $entityIterator = User::result(User::RESULT_ENTITY_ITERATOR)->find(null, null, 10);
        $this->assertInstanceOf('Scalr\Model\Collections\EntityIterator', $entityIterator);
        $this->assertNotEmpty($entityIterator->count());
        $this->assertNotEmpty($entityIterator->getArrayCopy());

        foreach ($entityIterator->filterByType(User::TYPE_ACCOUNT_OWNER) as $item) {
            $this->assertEquals(User::TYPE_ACCOUNT_OWNER, $item->type);
        }

        foreach (User::result(User::RESULT_ENTITY_ITERATOR)->findByType(User::TYPE_SCALR_ADMIN) as $item) {
            $this->assertEquals(User::TYPE_SCALR_ADMIN, $item->type);
        }

        $arrayCollection = User::result(User::RESULT_ENTITY_COLLECTION)->find(null, null, 10);
        $this->assertInstanceOf('Scalr\Model\Collections\ArrayCollection', $arrayCollection);
        $this->assertNotEmpty($arrayCollection->count());
        $this->assertNotEmpty($arrayCollection->getArrayCopy());

        $rs = User::result(User::RESULT_RAW)->find(null, null, 10);
        $this->assertInstanceOf('ADORecordSet', $rs);
        foreach ($rs as $item) {
            $this->assertNotEmpty($item);
            $this->assertInternalType('array', $item);
        }


    }
}