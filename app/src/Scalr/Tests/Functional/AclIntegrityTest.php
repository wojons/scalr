<?php

namespace Scalr\Tests\Functional;

use Scalr\System\Config\Yaml;
use Scalr\Acl\Acl;
use Scalr\Acl\Role;
use Scalr\Tests\Exception;
use Scalr\Tests\WebTestCase;

/**
 * ACL Integrity test
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    16.08.2013
 */
class AclIntegrityTest extends WebTestCase
{

    const TEST_DATA_FILE = 'AclIntegrity.yml';


    /**
     * User's Roles
     * @var Role\AccountRoleSuperposition
     */
    private static $roles;

    /**
     * Misc. static cache
     *
     * @var array
     */
    private static $cache;

    /**
     * Catch only permission in the mock request
     *
     * @var array
     */
    private $catch = null;

    /**
     * Test data
     * @var array
     */
    protected $data;


    /**
     * Gets all access permission for current test user and test environment
     * which are provided in the scalr.phpunit section of the config.
     *
     * @param  bool     $ignoreCache  Should it ignore cache or not.
     * @return \Scalr\Acl\Role\AccountRoleSuperposition Returns role superposition object
     */
    public function getRoles($ignoreCache = false)
    {
        if (!isset(self::$roles) || $ignoreCache) {
            //this method is called from data provider so that $this->user does not determined
            self::$roles = $this->getUser()->getAclRolesByEnvironment($this->getEnvironment()->id);
        }
        return self::$roles;
    }

    /**
     * Gets the first farm which is owned by the test user
     *
     * @param   bool         $owned  optional True for farm which is owned by the user,
     *                               false - for farms from user's account which is not
     *                               owned by the user.
     * @return  \DBFarm|null Returns the farm object or null
     */
    public function getFarm($owned = true)
    {
        $key = $owned ? 'owned' : 'not-owned';
        if (!isset(self::$cache['farms'][$key])) {
            self::$cache['farms'][$key] = false;
            $db = \Scalr::getDb();
            $user = $this->getUser();
            if ($user instanceof \Scalr_Account_User) {
                $id = $db->GetOne("
                    SELECT id FROM farms
                    WHERE created_by_id " . ($owned ? '=' : '<>') . " ? AND clientid = ?
                    LIMIT 1
                ", array($user->getId(), $user->getAccountId()));
                if ($id) {
                    self::$cache['farms'][$key] = \DBFarm::LoadByID($id);
                }
            }
        }
        return self::$cache['farms'][$key] instanceof \DBFarm ?
               self::$cache['farms'][$key] : null;
    }

    /**
     * Gets the first farm role which relates to farm that is owned by the test user.
     *
     * @param    bool $owned optional True for item that is owned by the user
     * @return   \DBFarmRole|null
     */
    public function getFarmRole($owned = true)
    {
        $key = $owned ? 'owned' : 'not-owned';
        if (!isset(self::$cache['farmrole'][$key])) {
            self::$cache['farmrole'][$key] = false;
            $farm = $this->getFarm($owned);
            if ($farm && isset($farm->ID)) {
                $db = \Scalr::getDb();
                $farmRoleId = $db->GetOne("SELECT id FROM farm_roles WHERE farmid = ? LIMIT 1", array($farm->ID));
                if ($farmRoleId)
                    self::$cache['farmrole'][$key] = \DBFarmRole::LoadByID($farmRoleId);
            }
        }
        return self::$cache['farmrole'][$key] instanceof \DBFarmRole ?
               self::$cache['farmrole'][$key] : null;
    }

    /**
     * Asserts that uri request is allowed for test user.
     *
     * It sends a request and checks if the specified action in the
     * controller is covered by the verification of the Access permission
     * and returns the same value as expected.
     *
     * @param   bool       $granted    Expected value. TRUE if access granted.
     * @param   string     $uri        The Request URI
     * @param   array      $parameters optional Array of the request parameters
     * @param   string     $message    The message which is used in assertion
     */
    public function assertThatPermission($granted, $uri, array $parameters = null, $message = '')
    {
        if (!isset($parameters)) {
            $parameters = array();
        }

        try {
            $content = $this->request($uri, $parameters);
        } catch (Exception\AclAccessDeniedException $e) {
            return $this->assertFalse((bool)$granted, $message);
        } catch (Exception\AclAccessGrantedException $e) {
            return $this->assertTrue((bool)$granted, $message);
        }

        $error = isset($content['errorMessage']) ? $content['errorMessage'] : '';

        if ($error) {
            if ($granted) {
                return $this->assertContains(Exception\AclAccessGrantedException::MESSAGE, $error, $message);
            } else {
                return $this->assertTrue(
                           strpos($error, Exception\AclAccessDeniedException::MESSAGE) === 0 ||
                           \Scalr_Exception_InsufficientPermissions::MESSAGE == $error,
                           sprintf('%s. Message:"%s"', $message, $error)
                       );
            }
        }

        return $this->assertTrue(false, sprintf(
            'Access permission verification is not provided for request uri:"%s". '
          . 'Please correct the code of the appropriated controller. %s', $uri, $message
        ));
    }

    protected function setCatchOnlyPermission($resourceId, $permissionId = null)
    {
        $this->catch = array($resourceId, $permissionId);
    }

    protected function resetCatchOnlyPermission()
    {
        $this->catch = null;
    }

    public function getCatchOnlyPermission()
    {
        return $this->catch;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Tests.WebTestCase::getRequest()
     */
    protected function getRequest($requestType, $requestClass, $uri, array $parameters = array(), $method = 'GET', array $server = array(), array $files = array())
    {
        $mock = $this->getMock('Scalr_UI_Request', array('restrictAccess', 'isAllowed'), array(), '', false);
        $me = $this;
        $user = $this->getUser();
        //Sets an ID of the environment.
        $user->getPermissions()->setEnvironmentId($this->getEnvironment()->id);

        $mock = parent::getRequest($requestType, $mock, $uri, $parameters, $method, $server, $files);

        $lfGetMessage = function($prefix, $resourceId, $permissionId = null) {
            $rm = Acl::getResourcesMnemonic();
            return sprintf($prefix . '("%s", %s)',
                (isset($rm[$resourceId]) ? $rm[$resourceId] : $resourceId),
                ($permissionId ? '"' . $permissionId . '"' : 'null'));
        };

        $mock
            ->expects($this->any())
            ->method('restrictAccess')
            ->will($this->returnCallback(function($resourceId, $permissionId = null) use ($mock, $me, $lfGetMessage) {
                if (is_string($resourceId)) {
                    $sName = 'Scalr\\Acl\\Acl::RESOURCE_' . strtoupper($resourceId);
                    if (defined($sName)) {
                        $resourceId = constant($sName);
                    } else {
                        throw new \InvalidArgumentException(sprintf(
                            'Cannot find ACL resource %s by specified symbolic name %s.',
                            $sName, $resourceId
                        ));
                    }
                }

                if (!$mock->isAllowed($resourceId, $permissionId)) {
                   throw new Exception\AclAccessDeniedException($lfGetMessage('request->restrictAccess', $resourceId, $permissionId));
                }

                if (($catch = $me->getCatchOnlyPermission()) === null ||
                    $catch[0] == $resourceId && (isset($catch[1]) ? $catch[1] : null) == $permissionId) {
                    throw new Exception\AclAccessGrantedException($lfGetMessage('request->restrictAccess', $resourceId, $permissionId));
                }
            }))
        ;

        $mock
            ->expects($this->any())
            ->method('isAllowed')
            ->will($this->returnCallback(function($resourceId, $permissionId = null) use ($mock, $me, $lfGetMessage) {
                //Owner is allowed for everything
                if ($mock->getUser()->getType() == \Scalr_Account_User::TYPE_ACCOUNT_OWNER) {
                    $ret = true;
                } else {
                    if (is_string($resourceId)) {
                        $sName = 'Scalr\\Acl\\Acl::RESOURCE_' . strtoupper($resourceId);
                        if (defined($sName)) {
                            $resourceId = constant($sName);
                        } else {
                            throw new \InvalidArgumentException(sprintf(
                                'Cannot find ACL resource %s by specified symbolic name %s.',
                                $sName, $resourceId
                            ));
                        }
                    }
                    $ret = (bool) $mock->getAclRoles()->isAllowed($resourceId, $permissionId);
                }

                if (($catch = $me->getCatchOnlyPermission()) === null ||
                    $catch[0] == $resourceId && (isset($catch[1]) ? $catch[1] : null) == $permissionId) {
                    if ($ret) {
                        throw new Exception\AclAccessGrantedException($lfGetMessage('request->isAllowed', $resourceId, $permissionId));
                    } else {
                        throw new Exception\AclAccessDeniedException($lfGetMessage('request->isAllowed', $resourceId, $permissionId));
                    }
                }

                return $ret;
            }))
        ;

        if (($catch = $this->getCatchOnlyPermission()) !== null && count($catch) > 1 &&
            $catch[0] == Acl::RESOURCE_FARMS &&
            $catch[1] == Acl::PERM_FARMS_NOT_OWNED_FARMS) {
            //Mocking the user object of the request to catch hasAccessFarm calls
            /* @var $usermock \Scalr_Account_User */
            $usermock = $this->getMock('Scalr_Account_User', null);
            $usermock->loadById($this->getUser()->id);
            //Creates permissions mock
            $permissions = $this->getMock('Scalr_Permissions', array('hasAccessFarm'), array($usermock));
            //Injects permissions object to user mock.
            $refPerm = new \ReflectionProperty('Scalr_Account_User', 'permissions');
            $refPerm->setAccessible(true);
            $refPerm->setValue($usermock, $permissions);
            //Preparing stub for the method
            $permissions->expects($this->any())
                        ->method('hasAccessFarm')
                        ->will($this->returnCallback(function ($dbFarm) use ($user) {
                            $ret = $user->getPermissions()->hasAccessFarm($dbFarm);
                            if ($ret) {
                                throw new Exception\AclAccessGrantedException(sprintf('user->permissions->hasAccessFarm(%d)', $dbFarm->ID));
                            } else {
                                throw new Exception\AclAccessDeniedException(sprintf('user->permissions->hasAccessFarm(%d)', $dbFarm->ID));
                            }
                        }))
            ;
            //Injecting user mock to request mock.
            $refUser = new \ReflectionProperty('Scalr_UI_Request', 'user');
            $refUser->setAccessible(true);
            $refUser->setValue($mock, $usermock);
        }

        return $mock;
    }


    /**
     * Gets all controllers actions as URI
     *
     * @param    sring       $uri   The uri that represents the class name
     * @return   array       Returns array of the al
     * @throws   \Exception
     */
    public function getAllControllerActions($uri)
    {
        $ret = array();
        $uri = trim($uri, '/');
        $class = 'Scalr_UI_Controller_' . join('_', array_map('ucfirst', preg_split('/\//', $uri)));
        if (!class_exists($class)) {
            throw new \Exception('Class ' . $class . " does not exist.");
        }
        $refl = new \ReflectionClass($class);
        foreach ($refl->getMethods(\ReflectionMethod::IS_PUBLIC) as $refMethod) {
            /* @var $refMethod \ReflectionMethod */
            if (substr($refMethod->getName(), -6) == 'Action') {
                $ret[] = '/' . $uri . '/' . substr($refMethod->getName(), 0, -6);
            }
        }

        return $ret;
    }

    /**
     * Callback for the array_walk function call of providerIsImposedRestriction method
     *
     * @param   array|string   $aUri
     * @param   string         $index
     * @param   array          $options
     */
    public function cb ($aUri, $index, $options)
    {
        if (is_array($aUri)) {
            $uri = $aUri[0];
            $parameters = !empty($aUri[1]) ? $aUri[1] : array();
            $allActions = isset($aUri[2]) ? true : false;
            if ($allActions) {
                //Resolve all actions
                foreach ($this->getAllControllerActions($uri) as $actionUri) {
                    $this->cb(array($actionUri, $parameters), $index, $options);
                }
            }
        } else {
            $uri = $aUri;
            $parameters = array();
            $allActions = false;
        }

        $testUserId = $this->getUser()->getId();
        $testEnvironmentId = $this->getEnvironment()->id;

        $match = function($key) use ($uri, $parameters) {
            if (!empty($parameters) && is_array($parameters)) {
                foreach ($parameters as $v) {
                    if ('%' . $key . '%' == $v) {
                        return true;
                    }
                }
            }
            return preg_match('~%' . preg_quote($key, '~') . '%~', $uri);
        };

        $replace = function($key, $value) use (&$uri, &$parameters) {
            if (!empty($parameters) && is_array($parameters)) {
                foreach ($parameters as $i => $v) {
                    if ('%' . $key . '%' == $v) {
                        $parameters[$i] = $value;
                    }
                }
            }
            $uri = preg_replace('~%' . preg_quote($key, '~') . '%~', $value, $uri);
        };

        //Translate URI parameters
        if (strpos($uri, '%') !== false || !empty($parameters)) {
            if ($match('ENV_ID')) {
                $replace('ENV_ID', $this->getEnvironment()->id);
            }
            if ($match('OWNED_FARM_ID')) {
                $ownedFarm = $this->getFarm(true);
                $this->assertNotNull($ownedFarm, sprintf(
                    "User with identifier %s should be owner at least one Farm for this test.",
                    $testUserId
                ));
                $replace('OWNED_FARM_ID', ($ownedFarm ? $ownedFarm->ID : 0));
            } elseif ($match('NOT_OWNED_FARM_ID')) {
                $notOwnedFarm = $this->getFarm(false);
                $this->assertNotNull($notOwnedFarm, sprintf(
                    "Environment with identifier %s should have at least one Farm "
                  . "which is not owned by the User with identifier %s for this test.",
                    $testEnvironmentId, $testUserId
                ));
                $replace('NOT_OWNED_FARM_ID', ($notOwnedFarm ? $notOwnedFarm->ID : 0));
            } elseif ($match('OWNED_FARM_ROLE_ID')) {
                $ownedFarmRole = $this->getFarmRole(true);
                $this->assertNotNull($ownedFarmRole, sprintf(
                    "Environment with identifier %s should have at least one server "
                  . "which has relation to the Farm which is owned by the User with identifier %s for this test.",
                    $testEnvironmentId, $testUserId
                ));
                $replace('OWNED_FARM_ROLE_ID', ($ownedFarmRole ? $ownedFarmRole->ID : 0));
            } elseif ($match('NOT_OWNED_FARM_ROLE_ID')) {
                $notOwnedFarmRole = $this->getFarmRole(false);
                $this->assertNotNull($notOwnedFarmRole, sprintf(
                    "Environment with identifier %s should have at least one server "
                  . "which has relation to the Farm which is not owned by the User with identifier %s for this test.",
                    $testEnvironmentId, $testUserId
                ));
                $replace('NOT_OWNED_FARM_ROLE_ID', ($notOwnedFarmRole ? $notOwnedFarmRole->ID : 0));
            }
        }

        $resourceId = constant('Scalr\\Acl\\Acl::RESOURCE_' . $options[0]);
        //Ignores checking disabled resources
        if (in_array($resourceId, Acl::getDisabledResources())) return;

        $permissionId = isset($options[1]) ? constant('Scalr\\Acl\\Acl::PERM_' . $options[1]) : null;
        $this->data[] = array($uri, $this->getRoles()->isAllowed($resourceId, $permissionId), $resourceId, $permissionId, $parameters);
    }

    /**
     * Data provider for testIsImposedRestriction()
     *
     * @return array
     */
    protected function providerIsImposedRestriction()
    {
        $me = $this;
        $this->data = array();
        $list = Yaml::load($this->getFixturesDirectory() . '/' . self::TEST_DATA_FILE);

        $testUserId = $this->getUser()->getId();
        $testEnvironmentId = $this->getEnvironment()->id;

        foreach ($list->get(null) as $resource => $d) {
            if (!$d) continue;
            if (array_key_exists('self', $d)) {
                foreach ($d as $perm => $arrayUri) {
                    if (!$arrayUri || $perm == 'self') continue;
                    array_walk($arrayUri, array($this, 'cb'), array($resource, $perm));
                }
                $d = $d['self'];
            }
            if (is_array($d)) {
                array_walk($d, array($this, 'cb'), array($resource, null));
            }
        }

        return $this->data;
    }

    /**
     * This test is used mapping from Fixtures/{self::TEST_DATA_FILE} yaml file
     *
     * @test
     */
    public function testIsImposedRestriction()
    {
        $rm = Acl::getResourcesMnemonic();
        //We have to use provider in this way because of we need to skip test and throw assertion from it
        $providerData = $this->providerIsImposedRestriction();
        foreach ($providerData as $opt) {
            $uri          = $opt[0];
            $granted      = $opt[1];
            $resourceId   = $opt[2];
            $permissionId = isset($opt[3]) ? $opt[3] : null;
            $options      = isset($opt[4]) ? $opt[4] : array();

            $this->setCatchOnlyPermission($resourceId, $permissionId);
            $this->assertThatPermission(
                $granted, $uri, $options,
                sprintf(
                    "Resource:%s, Permission:%s, URI:%s",
                    (isset($rm[$resourceId]) ? $rm[$resourceId] : $resourceId),
                    (isset($permissionId) ? $permissionId : 'null'),
                     $uri
                )
            );
        }
    }
}