<?php

namespace Scalr\Tests\Acl;

use Scalr\Tests\TestCase;
use Scalr\Acl\Acl;
use Scalr\Acl\Role;
use Scalr\Acl\Resource;

/**
 * AclTest
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     30.17.2013
 */
class AclTest extends TestCase
{
    const ROLE_FULL_ACCESS = Acl::ROLE_ID_FULL_ACCESS;

    const ROLE_EVERYTHING_FORBIDDEN = Acl::ROLE_ID_EVERYTHING_FORBIDDEN;

    /**
     * @test
     */
    public function testResourceConstants()
    {
        $reflection = new \ReflectionClass('Scalr\\Acl\\Acl');
        $this->assertTrue($reflection->hasConstant('RESOURCE_FARMS'));
        $this->assertEquals(0x100, Acl::RESOURCE_FARMS);

        //All IDs of the resources must be unique
        $uniq = array();
        foreach ($reflection->getConstants() as $name => $value) {
            if (strpos($name, 'RESOURCE_') === 0) {
                $this->assertFalse(
                    isset($uniq[$value]),
                    sprintf('Not unique ID(0x%x) of the ACL resource has been found', $value)
                );
                $uniq[$value] = $name;
            }
        }
        unset($uniq);
    }

    /**
     * Data provider for testPredefinedRoles()
     */
    public function providerPredefinedRoles()
    {
        return array(
            array(self::ROLE_FULL_ACCESS, true),
            array(self::ROLE_EVERYTHING_FORBIDDEN, false),
        );
    }

    /**
     * Verifies that Full access role is defined properly.
     *
     * All existing resources must be defined and allowed for this role.
     * All existing resource unique permissions must be defined and allowed for this role.
     *
     * @test
     * @dataProvider providerPredefinedRoles
     */
    public function testPredefinedRoles($roleId, $allowed)
    {
        $acl = \Scalr::getContainer()->acl;

        $role = $acl->getRole($roleId);
        $this->assertInstanceOf('Scalr\\Acl\\Role\\RoleObject', $role);
        $this->assertNotEmpty($role->getName(), 'Role name must be defined');
        $this->assertEquals($roleId, $role->getRoleId());
        $roleResources = $role->getResources();
        $this->assertInstanceOf('ArrayObject', $roleResources);
        /* @var $resourceDefinition Resource\ResourceObject */
        foreach (Resource\Definition::getAll() as $resourceId => $resourceDefinition) {
            // Absence of the record is considered as forbidden
            if (!$allowed && !isset($roleResources[$resourceId])) continue;

            $this->assertTrue(isset($roleResources[$resourceId]), sprintf(
                'All resources must be defined for the %s role. '
              . 'You should add records to the acl_role_resources table with role_id(%d)',
                $role->getName(), self::ROLE_FULL_ACCESS
            ));

            /* @var $resource Role\RoleResourceObject */
            $resource = $roleResources[$resourceId];
            $this->assertTrue(($resource->isGranted() == $allowed), sprintf(
                '%s resource must be %s for the %s role',
                $resourceDefinition->getName(),
                ($allowed ? 'allowed' : 'forbidden'),
                $role->getName()
            ));

            $permissions = $resource->getPermissions();
            $this->assertInstanceOf('ArrayObject', $permissions);

            foreach ($resourceDefinition->getPermissions() as $permissionId => $description) {
                // Absence of the record is considered as forbidden
                if (!$allowed && !isset($permissions[$permissionId])) continue;

                $this->assertTrue(isset($permissions[$permissionId]), sprintf(
                    'Permission [%s - %s] must be defined for the %s role. '
                  . 'You should add record to the acl_role_resource_permission table with '
                  . 'key (role_id[%d], resource_id[0x%x], perm_id[%s]).',
                    $resourceDefinition->getName(), $permissionId, $role->getName(),
                    $role->getRoleId(), $resource->getResourceId(), $permissionId
                ));

                /* @var $permission Role\RoleResourcePermissionObject */
                $permission = $permissions[$permissionId];
                $this->assertInstanceOf('Scalr\\Acl\\Role\\RoleResourcePermissionObject', $permission);
                $this->assertTrue(($permission->isGranted() == $allowed), sprintf(
                    'Permission [%s - %s] must be %s for the %s role.',
                    $resourceDefinition->getName(), $permissionId,
                    ($allowed ? 'allowed' : 'forbidden'), $role->getName()
                ));
            }
        }
    }
}