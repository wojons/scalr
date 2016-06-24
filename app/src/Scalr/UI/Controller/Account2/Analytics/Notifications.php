<?php

use Scalr\Acl\Acl;
use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;
use Scalr\UI\Controller\Account2\Analytics\NotificationTrait;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Account2_Analytics_Notifications extends Scalr_UI_Controller
{
    use NotificationTrait;

    /**
     * {@inheritdoc}
     * @see \Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_ANALYTICS_PROJECTS_ACCOUNT);
    }

    public function defaultAction()
    {
        $projects = [];

        foreach ($this->getContainer()->analytics->projects->getAccountProjects($this->user->getAccountId()) as $project) {
            /* @var $project ProjectEntity */
            if (!$project->archived && $project->shared === ProjectEntity::SHARED_WITHIN_ACCOUNT) {
                $projects[$project->projectId] = $project->name;
            }
        }

        $this->response->page('ui/account2/analytics/notifications/view.js', [
            'notifications.projects' => NotificationEntity::find([
                ['subjectType' => NotificationEntity::SUBJECT_TYPE_PROJECT],
                ['$or' => [
                    ['subjectId'   => ['$in' => array_keys($projects)]],
                    ['subjectId'   => null]
                ]]
            ])->getArrayCopy(),
            'reports'                => ReportEntity::find([
                ['subjectType' => ReportEntity::SUBJECT_TYPE_PROJECT],
                ['$or' => [
                    ['subjectId'   => ['$in' => array_keys($projects)]],
                    ['subjectId'   => null]
                ]]
            ])->getArrayCopy(),
            'projects'               => $projects
        ], [], ['ui/admin/analytics/notifications/view.css']);
    }

    /**
     * @param JsonData $notifications
     */
    public function xSaveAction(JsonData $notifications)
    {
        $data = [];
        $projects = [];

        foreach ($this->getContainer()->analytics->projects->getAccountProjects($this->user->getAccountId()) as $project) {
            /* @var $project ProjectEntity */
            if (!$project->archived && $project->shared === ProjectEntity::SHARED_WITHIN_ACCOUNT) {
                $projects[$project->projectId] = $project->name;
            }
        }

        foreach ($notifications as $id => $settings) {
            if ($id == 'reports') {
                $this->saveReports($settings);

                $data[$id] = ReportEntity::find([
                    ['subjectType' => ReportEntity::SUBJECT_TYPE_PROJECT],
                    ['$or' => [
                        ['subjectId'   => ['$in' => array_keys($projects)]],
                        ['subjectId'   => null]
                    ]]
                ])->getArrayCopy();
            } elseif ($id == 'notifications.projects') {
                $this->saveNotifications(NotificationEntity::SUBJECT_TYPE_PROJECT, $settings);

                $data[$id] = NotificationEntity::find([
                    ['subjectType' => NotificationEntity::SUBJECT_TYPE_PROJECT],
                    ['$or' => [
                        ['subjectId'   => ['$in' => array_keys($projects)]],
                        ['subjectId'   => null]
                    ]]
                ])->getArrayCopy();
            }
        }

        $this->response->data($data);
        $this->response->success('Notifications successfully saved');
    }

}
