<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;
use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\ProjectAdapter;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Tests\Functional\Api\ApiTestCase;
use Scalr\Tests\Functional\Api\ApiTestResponse;
use Scalr_Environment;

/**
 * Projects Test
 *
 * @author N.V.
 */
class ProjectsTest extends ApiTestCase
{

    public function projectToDelete($projectId)
    {
        static::$testData['Scalr\Stats\CostAnalytics\Entity\ProjectEntity'][] = $projectId;
    }

    /**
     * @param string $projectId
     *
     * @return ApiTestResponse
     */
    public function getProject($projectId)
    {
        $uri = self::getUserApiUrl("/projects/{$projectId}");

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * @param array $filters
     *
     * @return array
     */
    public function listProjects(array $filters = [])
    {
        $envelope = null;
        $projects = [];
        $uri = self::getUserApiUrl('/projects');

        do {
            $params = $filters;

            if (isset($envelope->pagination->next)) {
                $parts = parse_url($envelope->pagination->next);
                parse_str($parts['query'], $params);
            }

            $response = $this->request($uri, Request::METHOD_GET, $params);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $this->assertDescribeResponseNotEmpty($response);

            $envelope = $response->getBody();

            $projects[] = $envelope->data;
        } while (!empty($envelope->pagination->next));

        return call_user_func_array('array_merge', $projects);
    }

    /**
     * @param array $projectData
     *
     * @return ApiTestResponse
     */
    public function postProject(array &$projectData)
    {
        $projectData['name'] = "project-name-{$projectData['name']}";
        $projectData['description'] = "project-description-{$projectData['description']}";

        $uri = self::getUserApiUrl('/projects');
        return $this->request($uri, Request::METHOD_POST, [], $projectData);
    }

    /**
     * @test
     */
    public function textComplex()
    {
        $projects = $this->listProjects();

        $adapter = $this->getAdapter('project');

        foreach ($projects as $project) {
            foreach ($adapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE] as $property) {
                foreach($this->listProjects([ $property => $project->{$property} ]) as $filteredProject) {
                    $this->assertEquals($project->{$property}, $filteredProject->{$property});
                }
            }

            $response = $this->getProject($project->id);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $dbProject = ProjectEntity::findPk($project->id);

            $this->assertObjectEqualsEntity($response->getBody()->data, $dbProject, $adapter);
        }

        $ccId = Scalr_Environment::init()->loadById($this->getEnvironment()->id)->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID);
        $cc = \Scalr::getContainer()->analytics->ccs->get($ccId);

        $projectData = [
            'name' => 'test',
            'costCenter' => [ 'id' => $ccId ],
            'billingCode' => $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE),
            'leadEmail' => 'test@example.com',
            'description' => 'test'
        ];

        $response = $this->postProject($projectData);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $projectId = $response->getBody()->data->id;

        $dbProject = ProjectEntity::findPk($projectId);

        $this->assertNotEmpty($dbProject);

        $this->projectToDelete($projectId);

        $this->assertObjectEqualsEntity($projectData, $dbProject, $adapter);
    }
}