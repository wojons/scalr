<?php

namespace Scalr\Tests\Acl\Resource;

use Scalr\Acl\Resource\Definition;
use Scalr\Tests\TestCase;
use Scalr\Acl\Acl;

/**
 * DefinitionTest
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     30.17.2013
 */
class DefinitionTest extends TestCase
{

    /**
     * @test
     */
    public function testGetAll()
    {
        $definition = Definition::getAll();
        $this->assertInstanceOf('ArrayObject', $definition);
        $this->assertNotEmpty($definition);
    }

    /**
     * Provider method for testGet() test
     */
    public function providerGet()
    {
        $refl = new \ReflectionClass('Scalr\\Acl\\Acl');
        $arguments = array();

        //Fetches all resources which have been defined in the Acl class except excluded
        foreach (Acl::getResourcesMnemonic() as $resourceId => $mnemonicName) {
            $arguments[] = array($resourceId);
        }

        return $arguments;
    }

    /**
     * All defined constants for resources in Scalr\Acl\Acl class must be also
     * defined in the Scalr\Acl\Resource\Definition class
     *
     * @test
     * @dataProvider providerGet
     */
    public function testGet($resourceId)
    {
        $resourceDefinition = new Definition();
        $resource = $resourceDefinition->get($resourceId);
        $this->assertInstanceOf('Scalr\\Acl\\Resource\\ResourceObject', $resource, sprintf(
            "Resource (0x%x) must be defined in the Scalr\\Acl\\Resource\\Definition class", $resourceId));

        $this->assertEquals($resourceId, $resource->getResourceId());
        $this->assertNotEmpty($resource->getName(),
            sprintf("Name of the resource (0x%x) must be defined", $resourceId));

        $this->assertNotEmpty($resource->getDescription(),
            sprintf("Description of the resource (0x%x) must be defined", $resourceId));

        $resource->getPermissions();
    }

    /**
     * Outputs the stucture of the resources.
     * @ not a test
     */
    public function printDefinition()
    {
        $reflection = new \ReflectionClass('Scalr\\Acl\\Acl');
        foreach ($reflection->getConstants() as $name => $value) {
            if (strpos($name, 'GROUP_') === 0) {
                printf("\n%s:\n--\n", $value);
                $list = Definition::getByGroup($value);
                /* @var $resource \Scalr\Acl\Resource\ResourceObject */
                foreach ($list as $resource) {
                    printf("  %s - %s\n", $resource->getName(), $resource->getDescription());
                    foreach ($resource->getPermissions() as $permissionId => $description) {
                        printf("  * %s - %s\n", ucfirst($permissionId), $description);
                    }
                }
            }
        }
    }
}