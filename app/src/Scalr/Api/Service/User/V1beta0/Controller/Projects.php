<?php

namespace Scalr\Api\Service\User\V1beta0\Controller;

use Exception;
use Scalr\Acl\Acl;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\DataType\ResultEnvelope;
use Scalr\Api\Rest\Controller\ApiController;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Api\Rest\Http\Request;
use Scalr\Api\Service\User\V1beta0\Adapter\ProjectAdapter;
use Scalr\Exception\AnalyticsException;
use Scalr\Stats\CostAnalytics\Entity\CostCentrePropertyEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectPropertyEntity;

/**
 * User/Version-1beta0/Projects API Controller
 *
 * @author N.V.
 */
class Projects extends ApiController
{

    private static $ccControllerClass = 'Scalr\Api\Service\User\V1beta0\Controller\CostCenters';

    /**
     * @var CostCenters
     */
    private $ccController;

    /**
     * Gets Cost Centers controller
     *
     * @return  CostCenters
     */
    public function getCcController()
    {
        if (empty($this->ccController)) {
            $this->ccController = $this->getContainer()->api->controller(static::$ccControllerClass);
        }

        return $this->ccController;
    }

    /**
     * Gets specified Script taking into account both scope and authentication token
     *
     * @param   string  $projectId  Unique identifier of the project
     *
     * @return ProjectEntity Returns the Project Entity on success
     * @throws ApiErrorException
     */
    public function getProject($projectId)
    {
        /* @var $project ProjectEntity */
        $project = ProjectEntity::findPk($projectId);

        if (!$project) {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Requested Project either does not exist or is not owned by your environment.");
        }

        if (!$this->hasPermissions($project)) {
            //Checks entity level write access permissions
            throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
        }

        return $project;
    }

    /**
     * Retrieves the list of the projects
     *
     * @return array Returns describe result
     * @throws ApiErrorException
     */
    public function describeAction()
    {
        $criteria = $this->getDefaultCriteria();

        if (empty($this->params('name')) && empty($this->params('billingCode'))) {
            $criteria[] = ['archived' => ProjectEntity::NOT_ARCHIVED];
        }
        
        return $this->adapter('project')->getDescribeResult($criteria);
    }

    /**
     * Creates a new Project in this Environment
     */
    public function createAction()
    {
        $this->checkPermissions(Acl::RESOURCE_ANALYTICS_ACCOUNT, Acl::PERM_ANALYTICS_ACCOUNT_MANAGE_PROJECTS);

        $object = $this->request->getJsonBody();

        /* @var $projectAdapter ProjectAdapter */
        $projectAdapter = $this->adapter('project');

        //Pre validates the request object
        $projectAdapter->validateObject($object, Request::METHOD_POST);

        $project = $projectAdapter->toEntity($object);

        $project->projectId = null;

        $user = $this->getUser();

        $project->accountId = $user->getAccountId();
        $project->shared = ProjectEntity::SHARED_WITHIN_ACCOUNT;

        $project->createdById = $user->getId();
        $project->createdByEmail = $user->getEmail();

        $cc = $this->getCcController()->getCostCenter($project->ccId);

        if (!empty($cc)) {
            if ($cc->archived) {
                throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_VALUE, "Cost center '{$cc->ccId}' already archived and can not be used");
            }

            if ($cc->getProperty(CostCentrePropertyEntity::NAME_LOCKED) == 1) {
                throw new ApiErrorException(403, ErrorMessage::ERR_PERMISSION_VIOLATION, "Insufficient permissions");
            }

            $email = $cc->getProperty(CostCentrePropertyEntity::NAME_LEAD_EMAIL);
            $emailData = [
                'projectName' => $projectAdapter->validateString(trim($project->name)),
                'ccName'      => $cc->name
            ];

        } else {
            throw new ApiErrorException(404, ErrorMessage::ERR_OBJECT_NOT_FOUND, "Cost center with ID '{$project->ccId}' not found");
        }

        if (empty($object->billingCode)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property billingCode");
        }

        $projectAdapter->validateString($object->billingCode, "Billing code contains invalid characters");

        if (empty($object->leadEmail) || !filter_var($object->leadEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ApiErrorException(400, ErrorMessage::ERR_INVALID_STRUCTURE, "Missed property leadEmail");
        }

        $projectAdapter->validateString($object->leadEmail, "Lead E-Mail code contains invalid characters");

        $projectAdapter->validateEntity($project);

        $db = $project->db();
        $db->BeginTrans();

        //Saves entity
        try {
            $project->save();

            $project->saveProperty(ProjectPropertyEntity::NAME_BILLING_CODE, $object->billingCode);
            $project->saveProperty(ProjectPropertyEntity::NAME_LEAD_EMAIL, $object->leadEmail);

            if (!empty($object->description)) {
                $projectAdapter->validateString($object->description, "Description contains invalid characters");

                $project->saveProperty(ProjectPropertyEntity::NAME_DESCRIPTION, $object->description);
            }

            $db->CommitTrans();
        } catch (AnalyticsException $e) {
            $db->RollbackTrans();

            throw new ApiErrorException(409, ErrorMessage::ERR_UNICITY_VIOLATION, $e->getMessage());
        }

        if (!empty($email)) {
            try {
                $this->getContainer()->mailer->sendTemplate(SCALR_TEMPLATES_PATH . '/emails/analytics_on_project_add.eml.php', $emailData, $email);
            } catch (Exception $e) {
                \Scalr::getContainer()->logger->info($e->getMessage());
            }

        }

        //Responds with 201 Created status
        $this->response->setStatus(201);

        return $this->result($projectAdapter->toData($project));
    }

    /**
     * Gets default search criteria
     *
     * @return array Returns array of the search criteria
     * @throws ApiErrorException
     */
    public function getDefaultCriteria()
    {
        $ccId = $this->getCcController()->getEnvironmentCostCenterId();

        if (empty($ccId)) {
            throw new ApiErrorException(500, ErrorMessage::ERR_INTERNAL_SERVER_ERROR, "Cost Center not configured for this environment");
        }

        return [
            ['ccId' => $ccId],
            ['$or' => [
                ['shared' => ProjectEntity::SHARED_WITHIN_CC],
                ['$and' => [
                    ['shared' => ProjectEntity::SHARED_WITHIN_ACCOUNT],
                    ['accountId' => $this->getUser()->getAccountId()]
                ]]
            ]]
        ];
    }

    /**
     * Fetches detailed info about one project
     *
     * @param    string $projectId  Unique identifier of the project
     *
     * @return   ResultEnvelope
     *
     * @throws   ApiErrorException
     */
    public function fetchAction($projectId)
    {
        return $this->result($this->adapter('project')->toData($this->getProject($projectId)));
    }
}