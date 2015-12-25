<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Http\Request;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\Entity\Image;
use Scalr\Model\Entity\Os;
use Scalr\Service\Aws;
use Scalr\Tests\Functional\Api\ApiTestCase;

/**
 * ImagesTest
 *
 * @author   Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since    5.4 (12.03.2015)
 */
class ImagesTest extends ApiTestCase
{
    /**
     * @test
     */
    public function testImagesFunctional()
    {
        $testName = str_replace('-', '', $this->getTestName());

        $images = null;
        $uri = self::getUserApiUrl('/images');

        do {
            $query = [];

            if (isset($images->pagination->next)) {
                $parts = parse_url($images->pagination->next);
                parse_str($parts['query'], $query);
            }

            $describe = $this->request($uri, Request::METHOD_GET, $query);
            $this->assertDescribeResponseNotEmpty($describe);

            $images = $describe->getBody();

            foreach ($images->data as $image) {
                $this->assertImageObjectNotEmpty($image);

                if (strpos($image->name, $testName) !== false) {
                    $delete = $this->request($uri . '/' . $image->id, Request::METHOD_DELETE);
                    $this->assertEquals(200, $delete->response->getStatus());
                }
            }
        } while (!empty($images->pagination->next));

        // test create action
        $create = $this->request($uri, Request::METHOD_POST, [], ['scope' => 'invalid']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid scope');

        $create = $this->request($uri, Request::METHOD_POST);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'Invalid body');

        $create = $this->request($uri, Request::METHOD_POST, [], ['invalid' => 'value']);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, 'You are trying to set');

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope' => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'  => 'invalidName^$&&'
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid name of the Image');

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope' => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'  => $testName
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property 'architecture'");

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'architecture'  => 'invalid',
            'name'          => $testName
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Invalid architecture value');

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'          => $testName,
            'architecture' => 'x86_64'
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property 'os.id'");

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'          => $testName,
            'os'            => ['id' => 'invalidOsId'],
            'architecture' => 'x86_64'
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, "OS with id 'invalidOsId' not found.");

        $os = Os::findOne([['status' => Os::STATUS_ACTIVE]]);
        /* @var $os Os */

        $env = \Scalr_Environment::init()->loadById(static::$testEnvId);

        $platform = \SERVER_PLATFORMS::EC2;

        if ($env->isPlatformEnabled($platform)) {
            $env->setPlatformConfig([$platform . '.is_enabled' => 0]);
        }

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'          => $testName,
            'os'            => ['id' => $os->id],
            'cloudPlatform' => $platform,
            'architecture' => 'x86_64'
        ]);

        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_VALUE, $create);
        $this->assertErrorMessageStatusEquals(400, $create);

        $env->setPlatformConfig([$platform . '.is_enabled' => 1]);

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'          => $testName,
            'os'            => ['invalid'],
            'cloudPlatform' => $platform,
            'architecture' => 'x86_64'
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property 'os.id'");

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'          => $testName,
            'os'            => ['id' => $os->id],
            'cloudPlatform' => $platform,
            'architecture' => 'x86_64'
        ]);
        $this->assertErrorMessageContains($create, 400, ErrorMessage::ERR_INVALID_VALUE, 'Unable to find the requested image on the cloud');

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

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'          => $testName,
            'os'            => $os->id,
            'cloudPlatform' => $platform,
            'cloudLocation' => $region,
            'cloudImageId'  => $cloudImageId,
            'architecture' => 'x86_64'
        ]);

        $this->assertFetchResponseNotEmpty($create);

        $imageBody = $create->getBody();

        $this->assertImageObjectNotEmpty($imageBody->data);

        $this->assertEquals(201, $create->response->getStatus());

        $this->assertNotEmpty($imageBody->data->id);
        $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $imageBody->data->scope);
        $this->assertEquals($testName, $imageBody->data->name);
        $this->assertEquals($os->id, $imageBody->data->os->id);
        $this->assertEquals($platform, $imageBody->data->cloudPlatform);
        $this->assertEquals($region, $imageBody->data->cloudLocation);
        $this->assertEquals($cloudImageId, $imageBody->data->cloudImageId);

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'          => $testName,
            'os'            => ['id' => $os->id],
            'cloudPlatform' => $platform,
            'cloudLocation' => $region,
            'cloudImageId'  => $cloudImageId,
            'architecture' => 'x86_64'
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_UNICITY_VIOLATION, $create);
        $this->assertErrorMessageStatusEquals(409, $create);

        // test filtering
        $describe = $this->request($uri, Request::METHOD_GET, ['scope' => ScopeInterface::SCOPE_ENVIRONMENT]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertImageObjectNotEmpty($data);
            $this->assertEquals(ScopeInterface::SCOPE_ENVIRONMENT, $data->scope);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['name' => $testName]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertImageObjectNotEmpty($data);
            $this->assertEquals($testName, $data->name);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['id' => $imageBody->data->id]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertImageObjectNotEmpty($data);
            $this->assertEquals($imageBody->data->id, $data->id);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['os' => $os->id]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertImageObjectNotEmpty($data);
            $this->assertEquals($os->id, $data->os->id);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['os' => 'invalid*&^^%']);
        $this->assertErrorMessageContains($describe, 400, ErrorMessage::ERR_INVALID_VALUE, "Invalid identifier of the OS");

        $describe = $this->request($uri, Request::METHOD_GET, ['cloudPlatform' => $platform, 'cloudLocation' => $region]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertImageObjectNotEmpty($data);
            $this->assertEquals($platform, $data->cloudPlatform);
            $this->assertEquals($region, $data->cloudLocation);
        }

        $describe = $this->request($uri, Request::METHOD_GET, ['cloudLocation' => $region]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_STRUCTURE, $describe);
        $this->assertErrorMessageStatusEquals(400, $describe);

        $describe = $this->request($uri, Request::METHOD_GET, ['cloudImageId' => $cloudImageId]);
        $this->assertDescribeResponseNotEmpty($describe);

        foreach ($describe->getBody()->data as $data) {
            $this->assertImageObjectNotEmpty($data);
            $this->assertEquals($cloudImageId, $data->cloudImageId);
        }

        // test modify action
        $modify = $this->request($uri, Request::METHOD_PATCH, [], [
            'name' => $testName . 'modify',
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_ENDPOINT_NOT_FOUND, $modify);
        $this->assertErrorMessageStatusEquals(404, $modify);

        $modify = $this->request($uri . '/' . $imageBody->data->id, Request::METHOD_PATCH, [], [
            'invalid' => $testName . 'modify',
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_STRUCTURE, $modify);
        $this->assertErrorMessageStatusEquals(400, $modify);

        $modify = $this->request($uri . '/' . $imageBody->data->id, Request::METHOD_PATCH, [], [
            'id' => $testName . 'modify',
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_STRUCTURE, $modify);
        $this->assertErrorMessageStatusEquals(400, $modify);

        $modify = $this->request($uri . '/' . $imageBody->data->id, Request::METHOD_PATCH, [], [
            'scope' => $testName . 'modify',
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_VALUE, $modify);
        $this->assertErrorMessageStatusEquals(400, $modify);

        $notFoundId = '11111111-1111-1111-1111-111111111111';

        $modify = $this->request($uri . '/' . $notFoundId, Request::METHOD_PATCH, [], [
            'name' => $testName . 'modify',
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_OBJECT_NOT_FOUND, $modify);
        $this->assertErrorMessageStatusEquals(404, $modify);

        $entity = Image::findOne([['envId' => null], ['status' => Image::STATUS_ACTIVE]]);
        /* @var $entity Image */
        $this->assertNotEmpty($entity);
        $notAccessibleId = $entity->hash;

        $modify = $this->request($uri . '/' . $notAccessibleId, Request::METHOD_PATCH, [], [
            'name' => $testName . 'modify',
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_SCOPE_VIOLATION, $modify);
        $this->assertErrorMessageStatusEquals(403, $modify);

        $create = $this->request($uri, Request::METHOD_POST, [], [
            'scope'         => ScopeInterface::SCOPE_ENVIRONMENT,
            'name'          => $testName,
            'os'            => ['id' => $entity->osId],
            'cloudPlatform' => $entity->platform,
            'cloudLocation' => $entity->cloudLocation,
            'cloudImageId'  => $entity->id,
            'architecture' => 'x86_64'
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_UNICITY_VIOLATION, $create);
        $this->assertErrorMessageStatusEquals(409, $create);

        // test fetch action
        $fetch = $this->request($uri . '/' . $notFoundId, Request::METHOD_GET);

        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_OBJECT_NOT_FOUND, $fetch);
        $this->assertErrorMessageStatusEquals(404, $fetch);

        $fetch = $this->request($uri . '/' . $imageBody->data->id, Request::METHOD_GET);

        $this->assertFetchResponseNotEmpty($fetch);

        $fetchBody = $fetch->getBody();
        $this->assertImageObjectNotEmpty($fetchBody->data);
        $this->assertEquals($imageBody->data->id, $fetchBody->data->id);

        $fetch = $this->request($uri . '/' . $entity->hash, Request::METHOD_GET);

        $this->assertFetchResponseNotEmpty($fetch);

        $fetchBody = $fetch->getBody();
        $this->assertImageObjectNotEmpty($fetchBody->data);
        $this->assertEquals($entity->hash, $fetchBody->data->id);

        $modify = $this->request($uri . '/' . $imageBody->data->id, Request::METHOD_PATCH, [], [
            'name' => $testName . 'modify',
        ]);

        $this->assertEquals(200, $modify->response->getStatus());
        $this->assertImageObjectNotEmpty($modify->getBody()->data);
        $this->assertEquals($testName . 'modify', $modify->getBody()->data->name);

        // test copy action
        $copy = $this->request($uri . '/' . $imageBody->data->id . '/actions/copy', Request::METHOD_POST);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_STRUCTURE, $copy);
        $this->assertErrorMessageStatusEquals(400, $copy);

        $copy = $this->request($uri . '/' . $imageBody->data->id . '/actions/copy', Request::METHOD_POST, [], [
            'cloudLocation' => 'invalid',
            'cloudPlatform' => 'ec2'
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_VALUE, $copy);
        $this->assertErrorMessageStatusEquals(400, $copy);

        $copy = $this->request($uri . '/' . $imageBody->data->id . '/actions/copy', Request::METHOD_POST, [], [
            'cloudLocation' => Aws::REGION_US_EAST_1,
            'cloudPlatform' => 'gce'
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_INVALID_VALUE, $copy);
        $this->assertErrorMessageStatusEquals(400, $copy);

        $copy = $this->request($uri . '/' . $imageBody->data->id . '/actions/copy', Request::METHOD_POST, [], [
            'cloudLocation' => $region,
            'cloudPlatform' => 'ec2'
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_BAD_REQUEST, $copy);
        $this->assertErrorMessageStatusEquals(400, $copy);

        $awsRegions = Aws::getCloudLocations();

        $copyTo = null;

        foreach ($awsRegions as $awsRegion) {
            if ($awsRegion != $region) {
                $copyTo = $awsRegion;
                break;
            }
        }

        $this->assertNotNull($copyTo);

        $copy = $this->request($uri . '/' . $notAccessibleId . '/actions/copy', Request::METHOD_POST, [], [
            'cloudLocation' => $copyTo,
            'cloudPlatform' => \SERVER_PLATFORMS::EC2
        ]);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_SCOPE_VIOLATION, $copy);
        $this->assertErrorMessageStatusEquals(403, $copy);

        $copy = $this->request($uri . '/' . $imageBody->data->id . '/actions/copy', Request::METHOD_POST, [], [
            'cloudLocation' => $copyTo,
            'cloudPlatform' => \SERVER_PLATFORMS::EC2
        ]);

        $copyBody = $copy->getBody();

        $this->assertEquals(202, $copy->response->getStatus());
        $this->assertFetchResponseNotEmpty($copy);
        $this->assertImageObjectNotEmpty($copyBody->data);
        $this->assertEquals(\SERVER_PLATFORMS::EC2, $copyBody->data->cloudPlatform);
        $this->assertEquals($copyTo, $copyBody->data->cloudLocation);

        // test delete action
        $delete = $this->request($uri . '/' . $notFoundId, Request::METHOD_DELETE);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_OBJECT_NOT_FOUND, $delete);
        $this->assertErrorMessageStatusEquals(404, $delete);

        $delete = $this->request($uri . '/' . $entity->hash, Request::METHOD_DELETE);
        $this->assertErrorMessageErrorEquals(ErrorMessage::ERR_SCOPE_VIOLATION, $delete);
        $this->assertErrorMessageStatusEquals(403, $delete);

        $delete = $this->request($uri . '/' . $copyBody->data->id, Request::METHOD_DELETE);
        $this->assertEquals(200, $delete->response->getStatus());

        $delete = $this->request($uri . '/' . $imageBody->data->id, Request::METHOD_DELETE);
        $this->assertEquals(200, $delete->response->getStatus());
    }

}