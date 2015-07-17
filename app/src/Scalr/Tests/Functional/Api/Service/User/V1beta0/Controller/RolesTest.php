<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Http\Request;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity\Os;
use Scalr\Model\Entity\Role;
use Scalr\Model\Entity\RoleCategory;
use Scalr\Service\Aws;
use Scalr\Tests\Functional\Api\ApiTestCase;

/**
 * RolesTest
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (12.03.2015)
 */
class RolesTest extends ApiTestCase
{
    /**
     * @test
     */
    public function testRolesFunctional()
    {
        $db = \Scalr::getDb();
        $testName = str_replace('-', '', static::getTestName());

        $roles = null;
        $uri = self::getUserApiUrl('/roles');

        // test describe pagination
        $describe = $this->request(static::getUserApiUrl('/roles', 'invalidEnv'), Request::METHOD_GET);
        $this->assertErrorMessageContains($describe, 400, ErrorMessage::ERR_INVALID_VALUE, 'Environment has not been provided with the request');

        do {
            $query = [];

            if (isset($roles->pagination->next)) {
                $parts = parse_url($roles->pagination->next);
                parse_str($parts['query'], $query);
            }

            $describe = $this->request($uri, Request::METHOD_GET, $query);

            $this->assertDescribeResponseNotEmpty($describe);

            $this->assertNotEmpty($describe->getBody());

            $roles = $describe->getBody();

            foreach ($roles->data as $role) {
                $this->assertRolesObjectNotEmpty($role);

                if ($role->name == $testName) {
                    $delete = $this->request($uri . '/' . $role->id, Request::METHOD_DELETE);
                    $this->assertEquals(200, $delete->status);
                }
            }
        } while (!empty($roles->pagination->next));

        // test create action
        $create = $this->request($uri, Request::METHOD_POST, [], ['scope' => 'invalid']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid scope');

        $create = $this->request($uri, Request::METHOD_POST);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Invalid body');

        $create = $this->request($uri, Request::METHOD_POST, [], ['invalid' => 'value']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set');

        $create = $this->request($uri, Request::METHOD_POST, [], ['id' => 'value']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid name');

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope' => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'  => 'invalidName^$&&'
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid name of the Role');

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope' => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'  => $testName,
            'description' => 'invalidDesc<br/>'
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid description');

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope' => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'  => $testName
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Role category should be provided');

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'     => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'      => $testName,
            'category'  => ['id' => 'not int']
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid identifier of the category');

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'     => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'      => $testName,
            'category'  => ['id' => -1]
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'The Role category does not exist');

        $rolesCat = RoleCategory::findOne();
        /* @var $rolesCat RoleCategory */
        $this->assertNotEmpty($rolesCat);

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'     => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'      => $testName,
            'category'  => ['id' => $rolesCat->id]
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'OS must be provided');

        $os = Os::findOne([['status' => Os::STATUS_ACTIVE], ['family' => 'ubuntu'], ['generation' => '12.04']]);
        /* @var $os Os */
        $this->assertNotEmpty($os);

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'     => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'      => $testName,
            'category'  => ['id' => $rolesCat->id],
            'os'        => ['id' => -1]

        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid identifier of the OS');

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'     => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'      => $testName,
            'category'  => ['id' => $rolesCat->id],
            'os'        => ['id' => 'invalid']

        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Specified OS does not exist');

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'          => $testName,
            'description'   => $testName,
            'category'      => $rolesCat->id,
            'os'            => $os->id
        ]);

        $body = $create->getBody();
        $this->assertEquals(201, $create->response->getStatus());
        $this->assertFetchResponseNotEmpty($create);
        $this->assertRolesObjectNotEmpty($body->data);

        $this->assertNotEmpty($body->data->id);
        $this->assertEquals($testName, $body->data->name);
        $this->assertEquals($testName, $body->data->description);
        $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $body->data->scope);
        $this->assertEquals($rolesCat->id, $body->data->category->id);
        $this->assertEquals($os->id, $body->data->os->id);

        // test images actions
        $roleId = $body->data->id;
        $imagesUri = $uri . '/' . $roleId . '/images';
        $images = null;

        do {
            $query = [];

            if (isset($images->pagination->next)) {
                $parts = parse_url($images->pagination->next);
                parse_str($parts['query'], $query);
            }

            $describeImages = $this->request($imagesUri, Request::METHOD_GET, $query);
            $this->assertDescribeResponseNotEmpty($describeImages);

            $images = $describeImages->getBody();

            foreach ($images->data as $imageRole) {
                $this->assertRoleImageObjectNotEmpty($imageRole);
                $this->assertEquals($roleId, $imageRole->role->id);

                $image = Image::findPk($imageRole->image->id);
                /* @var $image Image */
                if ($image->name == $testName) {
                    $delete = $this->request($imagesUri . '/' . $imageRole->image->id, Request::METHOD_DELETE);
                    $this->assertEquals(200, $delete->status);
                }
            }
        } while (!empty($images->pagination->next));

        $env = \Scalr_Environment::init()->loadById(static::$testEnvId);

        $platform = \SERVER_PLATFORMS::EC2;

        if (!$env->isPlatformEnabled($platform)) {
            $env->setPlatformConfig([$platform . '.is_enabled' => 1]);
        }

        $region = null;
        $cloudImageId = null;

        foreach (Aws::getCloudLocations() as $cloudLocation) {
            $cloudImageId = $this->getNewImageId($env, $cloudLocation);

            if (!empty($cloudImageId)) {
                $region = $cloudLocation;
                break;
            }
        }

        $this->assertNotNull($cloudImageId);
        $this->assertNotNull($cloudLocation);

        $createImage = $this->request(static::getUserApiUrl('/images'), Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'          => $testName,
            'os'            => ['id' => $os->id],
            'cloudPlatform' => $platform,
            'cloudLocation' => $region,
            'cloudImageId'  => $cloudImageId
        ]);

        $createImageBody = $createImage->getBody();

        $this->assertEquals(201, $createImage->response->getStatus());
        $this->assertFetchResponseNotEmpty($createImage);
        $this->assertImageObjectNotEmpty($createImageBody->data);
        $this->assertNotEmpty($createImageBody->data->id);

        $createRoleImage = $this->request($imagesUri, Request::METHOD_POST, [], [
            'role' => [
                'id'   => $roleId + 10
            ],
            'image' => [
                'id'   => $createImageBody->data->id,
            ]
        ]);
        $this->assertErrorMessageStatusEquals(400, $createRoleImage);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_VALUE, $createRoleImage);

        $createRoleImage = $this->request($imagesUri, Request::METHOD_POST, [], [
            'role' => [
                'id'   => $roleId
            ]
        ]);
        $this->assertErrorMessageStatusEquals(400, $createRoleImage);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_STRUCTURE, $createRoleImage);

        $createRoleImage = $this->request($imagesUri, Request::METHOD_POST, [], [
            'role' => [
                'id'   => $roleId
            ],
            'image' => [
                'id'   => '11111111-1111-1111-1111-111111111111',
            ]
        ]);
        $this->assertErrorMessageStatusEquals(404, $createRoleImage);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_VALUE, $createRoleImage);

        $createRoleImage = $this->request($imagesUri, Request::METHOD_POST, [], [
            'role' => [
                'id'   => $roleId
            ],
            'image' => [
                'id'   => $createImageBody->data->id,
            ]
        ]);

        $createRoleImageBody = $createRoleImage->getBody();

        $this->assertEquals(201, $createRoleImage->response->getStatus());
        $this->assertFetchResponseNotEmpty($createRoleImage);
        $this->assertRoleImageObjectNotEmpty($createRoleImageBody->data);

        $createRoleImageError = $this->request($imagesUri, Request::METHOD_POST, [], [
            'role' => [
                'id'   => $roleId
            ],
            'image' => [
                'id'   => $createImageBody->data->id,
            ]
        ]);
        $this->assertErrorMessageStatusEquals(400, $createRoleImageError);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_BAD_REQUEST, $createRoleImageError);

        $fetchImage = $this->request($imagesUri . '/' . $createRoleImageBody->data->image->id, Request::METHOD_GET);
        $fetchImageBody = $fetchImage->getBody();

        $this->assertEquals(200, $fetchImage->response->getStatus());
        $this->assertFetchResponseNotEmpty($fetchImage);
        $this->assertImageObjectNotEmpty($fetchImageBody->data);

        $this->assertEquals($cloudImageId, $fetchImageBody->data->cloudImageId);
        $this->assertEquals($testName, $fetchImageBody->data->name);

        // test role images filtering
        $describeRoleImages = $this->request($imagesUri, Request::METHOD_GET, ['role' => $roleId]);
        $this->assertDescribeResponseNotEmpty($describeRoleImages);

        foreach ($describeRoleImages->getBody()->data as $data) {
            $this->assertRoleImageObjectNotEmpty($data);
            $this->assertEquals($roleId, $data->role->id);
        }

        $describeRoleImages = $this->request($imagesUri, Request::METHOD_GET, ['image' => $createImageBody->data->id]);
        $this->assertDescribeResponseNotEmpty($describeRoleImages);

        foreach ($describeRoleImages->getBody()->data as $data) {
            $this->assertRoleImageObjectNotEmpty($data);
            $this->assertEquals($createImageBody->data->id, $data->image->id);
        }

        $describeRoleImages = $this->request($imagesUri, Request::METHOD_GET, ['invalid' => 'value']);
        $this->assertErrorMessageContains($describeRoleImages, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Unsupported filter');

        $currentRole = Role::findPk($roleId);
        /* @var $currentRole Role */
        $this->assertNotEmpty($currentRole);

        $adminImages = Image::find([
            ['envId'         => null],
            ['status'        => Image::STATUS_ACTIVE],
            ['cloudLocation' => $region]
        ]);
        $this->assertNotEmpty($adminImages);

        $adminImage = null;

        foreach ($adminImages as $aImage) {
            /* @var $aImage Image */
            $imageOs = $aImage->getOs();

            if (!empty($imageOs) && $imageOs->generation == $currentRole->getOs()->generation &&
                $imageOs->family == $currentRole->getOs()->family) {
                $adminImage = $aImage;
                break;
            }
        }

        /* @var $adminImage Image */
        $this->assertNotEmpty($adminImage);
        $this->assertNotEquals($createRoleImageBody->data->image->id, $adminImage->hash);

        $replaceImage = $this->request($imagesUri . '/' . $createRoleImageBody->data->image->id . '/actions/replace', Request::METHOD_POST, [], [
            'role' => $roleId,
            'image' => $adminImage->hash
        ]);

        $replaceImageBody = $replaceImage->getBody();

        $this->assertEquals(200, $replaceImage->response->getStatus());
        $this->assertFetchResponseNotEmpty($replaceImage);
        $this->assertRoleImageObjectNotEmpty($replaceImageBody->data);

        $this->assertEquals($adminImage->hash, $replaceImageBody->data->image->id);

        $deleteImage = $this->request($imagesUri . '/' . $replaceImageBody->data->image->id, Request::METHOD_DELETE);
        $this->assertEquals(200, $deleteImage->response->getStatus());

        $delete = $this->request(static::getUserApiUrl("images/{$createImageBody->data->id}"), Request::METHOD_DELETE);
        $this->assertEquals(200, $delete->response->getStatus());

        // test get action
        $notFoundRoleId = 10 + $db->GetOne("SELECT MAX(r.id) FROM roles r");
        $get = $this->request($uri . '/' . $notFoundRoleId, Request::METHOD_GET);
        $this->assertErrorMessageContains($get, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "The Role either does not exist or isn't in scope for the current Environment");

        $get = $this->request($uri . '/' . $body->data->id, Request::METHOD_GET);

        $getBody = $get->getBody();
        $this->assertEquals(200, $get->response->getStatus());
        $this->assertFetchResponseNotEmpty($get);
        $this->assertRolesObjectNotEmpty($getBody->data);

        $this->assertEquals($body->data->id, $getBody->data->id);
        $this->assertEquals($testName, $getBody->data->name);
        $this->assertEquals($testName, $getBody->data->description);
        $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $getBody->data->scope);
        $this->assertEquals($rolesCat->id, $getBody->data->category->id);
        $this->assertEquals($os->id, $getBody->data->os->id);

        // test filters
        $describe = $this->request($uri, Request::METHOD_GET, ['description' => $testName]);
        $this->assertErrorMessageContains($describe, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Unsupported filter');

        $describe = $this->request($uri, Request::METHOD_GET, ['scope' => 'wrong<br>']);
        $this->assertErrorMessageContains($describe, 400, ErrorMessage::ERR_INVALID_VALUE, 'Unexpected scope value');

        $describe = $this->request($uri, Request::METHOD_GET, ['scope' => ScopeInterface::SCOPE_SCALR]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertRolesObjectNotEmpty($data);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['scope' => ScopeInterface::SCOPE_ENVIRONMENT]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertRolesObjectNotEmpty($data);
            $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $data->scope);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['name' => $testName]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertRolesObjectNotEmpty($data);
            $this->assertEquals($testName, $data->name);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['id' => $roleId]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertRolesObjectNotEmpty($data);
            $this->assertEquals($roleId, $data->id);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['os' => $os->id]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertRolesObjectNotEmpty($data);
            $this->assertEquals($os->id, $data->os->id);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['os' => 'invalid*&^^%']);
        $this->assertErrorMessageContains($describe, 400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the OS");

        $describe = $this->request($uri, Request::METHOD_GET, ['category' => $rolesCat->id]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertRolesObjectNotEmpty($data);
            $this->assertEquals($rolesCat->id, $data->category->id);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['category' => '']);
        $this->assertErrorMessageContains($describe, 400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the category");

        // test modify action
        $modify = $this->request($uri . '/' . $body->data->id, Request::METHOD_PATCH);
        $this->assertErrorMessageContains($modify, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Invalid body');

        $modify = $this->request($uri . '/' . $body->data->id, Request::METHOD_PATCH, [], ['id' => 'some id']);
        $this->assertErrorMessageContains($modify, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Identifier of the Role does not match identifier');

        $modify = $this->request($uri . '/' . $body->data->id, Request::METHOD_PATCH, [], ['invalid' => 'err']);
        $this->assertErrorMessageContains($modify, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set');

        $modify = $this->request($uri . '/' . $body->data->id, Request::METHOD_PATCH, [], ['scope' => 'environment']);
        $this->assertErrorMessageContains($modify, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set');

        $modify = $this->request($uri . '/' . $body->data->id, Request::METHOD_PATCH, [], ['description' => '']);

        $modifyBody = $modify->getBody();
        $this->assertEquals(200, $modify->response->getStatus());

        $this->assertFetchResponseNotEmpty($modify);
        $this->assertRolesObjectNotEmpty($modifyBody->data);

        $this->assertEquals($body->data->id, $modifyBody->data->id);
        $this->assertEquals($testName, $modifyBody->data->name);
        $this->assertEquals('', $modifyBody->data->description);
        $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $modifyBody->data->scope);
        $this->assertEquals($rolesCat->id, $modifyBody->data->category->id);
        $this->assertEquals($os->id, $modifyBody->data->os->id);

        // test delete action
        $delete = $this->request(static::getUserApiUrl("/roles/{$body->data->id}", 0), Request::METHOD_DELETE);
        $this->assertErrorMessageContains($delete, 400, ErrorMessage::ERR_INVALID_VALUE, 'Environment has not been provided');
        $delete = $this->request(static::getUserApiUrl("/roles/{$body->data->id}", static::$testEnvId + 1), Request::METHOD_DELETE);
        $this->assertErrorMessageContains($delete, 403, ErrorMessage::ERR_OBJECT_NOT_FOUND, 'Invalid environment');
        $delete = $this->request(static::getUserApiUrl("/roles/{$notFoundRoleId}"), Request::METHOD_DELETE);
        $this->assertErrorMessageContains($delete, 404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "The Role either does not exist or isn't in scope for the current Environment");

        $delete = $this->request($uri . '/' . $body->data->id, Request::METHOD_DELETE);
        $this->assertEquals(200, $delete->status);

        $db->Execute("INSERT INTO roles SET
            name      = ?,
            dtadded   = NOW(),
            env_id	  = NULL,
            client_id = NULL
        ", [$testName]);

        $insertedId = $db->_insertid();

        $delete = $this->request($uri . '/' . $insertedId, Request::METHOD_DELETE);

        $db->Execute("DELETE FROM roles WHERE name = ? AND id = ?", [$testName, $insertedId]);

        $this->assertErrorMessageContains($delete, 403, ErrorMessage::ERR_SCOPE_VIOLATION, 'is not either from the Environment scope or owned');
    }

    /**
     * Asserts if role's object has all properties
     *
     * @param object $data     Single role's item
     */
    public function assertRolesObjectNotEmpty($data)
    {
        $this->assertObjectHasAttribute('id', $data);
        $this->assertObjectHasAttribute('name', $data);
        $this->assertObjectHasAttribute('description', $data);
        $this->assertObjectHasAttribute('scope', $data);
        $this->assertObjectHasAttribute('category', $data);
        $this->assertObjectHasAttribute('os', $data);
    }

    /**
     * Asserts if roleImage object has all properties
     *
     * @param object $data     Single roleImage item
     */
    public function assertRoleImageObjectNotEmpty($data)
    {
        $this->assertObjectHasAttribute('image', $data);
        $this->assertObjectHasAttribute('id', $data->image);
        $this->assertObjectHasAttribute('role', $data);
        $this->assertObjectHasAttribute('id', $data->role);
    }

}