<?php

namespace Scalr\Tests\Functional\Api\Service\User\V1beta0\Controller;

use Scalr\Api\DataType\ApiEntityAdapter;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Http\Request;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;
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
        static::toDelete(ProjectEntity::class, [$projectId]);
    }

    /**
     * Get Project using identifier
     *
     * @param  string $projectId project identifier
     *
     * @return ApiTestResponse
     */
    public function getProject($projectId)
    {
        $uri = self::getUserApiUrl("/projects/{$projectId}");

        return $this->request($uri, Request::METHOD_GET);
    }

    /**
     * Return list of Projects available in this environment
     *
     * @param  array $filters    optional Filterable properties
     * @param  int   $maxResults optional Max Results
     * @return array
     */
    public function listProjects(array $filters = [], $maxResults = null)
    {
        $envelope = null;
        $projects = [];
        $uri = self::getUserApiUrl('/projects');

        do {
            $params = $filters;

            if ($maxResults) {
                $params[ApiController::QUERY_PARAM_MAX_RESULTS] = $maxResults;
            }

            if (isset($envelope->pagination->next)) {
                $parts = parse_url($envelope->pagination->next);
                parse_str($parts['query'], $params);
            }

            $response = $this->request($uri, Request::METHOD_GET, $params);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $this->assertDescribeResponseNotEmpty($response);

            $envelope = $response->getBody();

            foreach ($envelope->data as $v) {
                $projects[] = $v;
            }

        } while (!empty($envelope->pagination->next) && !$maxResults);

        return $projects;
    }

    /**
     * Create new test project using api request
     *
     * @param  array $projectData
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
    public function testComplex()
    {
        //create archived project
        $projectArch = $this->createTestProject([
            'name'     => $this->getTestName(),
            'archived' => ProjectEntity::ARCHIVED
        ]);

        $projects = $this->listProjects([], 1);

        $adapter = $this->getAdapter('project');

        $filterable = $adapter->getRules()[ApiEntityAdapter::RULE_TYPE_FILTERABLE];

        foreach ($projects as $project) {
            foreach ($filterable as $property) {
                $filterValue = $project->{$property};

                $listResult = $this->listProjects([$property => $filterValue], 1);

                if (!empty($filterValue)) {
                    foreach ($listResult as $filtered) {
                        $this->assertEquals($filterValue, $filtered->{$property}, "Property '{$property}' mismatch");
                    }
                }
            }

            $response = $this->getProject($project->id);

            $this->assertEquals(200, $response->status, $this->printResponseError($response));

            $dbProject = ProjectEntity::findPk($project->id);
            $this->assertFalse((bool) $dbProject->archived);

            $this->assertObjectEqualsEntity($response->getBody()->data, $dbProject, $adapter);
        }

        $filterByName = $this->listProjects(['name' => $projectArch->name], 1);

        $this->assertNotEmpty($filterByName);

        foreach ($filterByName as $project) {
            $this->assertObjectEqualsEntity($project, $projectArch, $adapter);

            $this->assertNotContains($project, $projects, "List of project shouldn't  have an archived project", false, false);
        }

        $filterByBillingCode = $this->listProjects(['billingCode' => $projectArch->getProperty(ProjectPropertyEntity::NAME_BILLING_CODE)], 1);

        $this->assertNotEmpty($filterByBillingCode);

        foreach ($filterByBillingCode as $project) {
            $this->assertObjectEqualsEntity($project, $projectArch, $adapter);

            $this->assertNotContains($project, $projects, "List of project shouldn't  have an archived project", false, false);
        }

        $ccId = Scalr_Environment::init()->loadById($this->getEnvironment()->id)->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID);

        $cc = \Scalr::getContainer()->analytics->ccs->get($ccId);

        //test create project
        $projectData = [
            'name'        => 'test',
            'costCenter'  => [ 'id' => $ccId ],
            'billingCode' => $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE),
            'leadEmail'   => 'test@example.com',
            'description' => 'test'
        ];

        $response = $this->postProject($projectData);

        $this->assertEquals(201, $response->status, $this->printResponseError($response));

        $projectId = $response->getBody()->data->id;

        $dbProject = ProjectEntity::findPk($projectId);

        $this->assertNotEmpty($dbProject);

        $this->projectToDelete($projectId);

        $this->assertObjectEqualsEntity($projectData, $dbProject, $adapter);

        //test empty project name
        $projectData = [
            'name'        => "\t\r\n\0\x0B<a href=\"#\">\t\r\n\0\x0B</a>\t\r\n\0\x0B",
            'costCenter'  => [ 'id' => $ccId ],
            'billingCode' => $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE),
            'leadEmail'   => 'test@example.com',
            'description' => 'test-empty-name'
        ];

        $response = $this->postProject($projectData);

        $this->assertEquals(400, $response->status, $this->printResponseError($response));

        //test wrong ccId
        $projectData = [
            'name'        => "test-wrong-cc-id",
            'costCenter'  => [ 'id' => "\t\r\n\0\x0B<a href=\"#\">\t\r\n\0\x0B</a>\t\r\n\0\x0B" ],
            'billingCode' => $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE),
            'leadEmail'   => 'test@example.com',
            'description' => 'test-empty-name'
        ];

        $response = $this->postProject($projectData);

        $this->assertEquals(404, $response->status, $this->printResponseError($response));

        //test wrong leadEmail
        $projectData = [
            'name'        => "test-wrong-cc-id",
            'costCenter'  => [ 'id' => $ccId ],
            'billingCode' => $cc->getProperty(CostCentrePropertyEntity::NAME_BILLING_CODE),
            'leadEmail'   => "\t\r\n\0\x0B<a href=\"#\">\t\r\n\0\x0B</a>\t\r\n\0\x0B",
            'description' => 'test-empty-lead-email'
        ];

        $response = $this->postProject($projectData);

        $this->assertEquals(400, $response->status, $this->printResponseError($response));
    }

    /**
     * Creates new project for testing purposes
     *
     * @param array $data optional
     *
     * @return ProjectEntity
     */
    public function createTestProject(array $data = [])
    {
        $user = $this->getUser();
        $ccId = Scalr_Environment::init()->loadById($this->getEnvironment()->id)->getPlatformConfigValue(Scalr_Environment::SETTING_CC_ID);

        $projectData = array_merge([
            'ccId'           => $ccId,
            'name'           => $this->getTestName(),
            'accountId'      => $user->getAccountId(),
            'envId'          => $this->getEnvironment()->id,
            'createdById'    => $user->id,
            'createdByEmail' => $user->email
        ], $data);

        /* @var $project ProjectEntity */
        $project = $this->createEntity(new ProjectEntity(), $projectData);
        $project->setCostCenter(\Scalr::getContainer()->analytics->ccs->get($ccId));
        $project->saveProperty(ProjectPropertyEntity::NAME_BILLING_CODE, $projectData['name']);
        $project->save();

        return $project;
    }

    /**
     * Also Removes Project properties generated for test
     *
     * {@inheritdoc}
     */
    public static function tearDownAfterClass()
    {
        //We have to remove CostCenter properties as they don't have foreign keys
        foreach (static::$testData as $rec) {
            if ($rec['class'] === ProjectEntity::class) {
                ProjectPropertyEntity::deleteByProjectId($rec['pk'][0]);
            }
        }

        parent::tearDownAfterClass();
    }

}