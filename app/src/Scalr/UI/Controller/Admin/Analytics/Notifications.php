<?php

use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;
use Scalr\UI\Controller\Admin\Analytics\NotificationTrait;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Admin_Analytics_Notifications extends Scalr_UI_Controller
{
    use NotificationTrait;

    /**
     * {@inheritdoc}
     * @see \Scalr_UI_Controller::hasAccess()
     */
    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    public function defaultAction()
    {
        $ccs = [];
        $projects = [];

        foreach (CostCentreEntity::find([['archived' => 0]]) as $cc) {
            /* @var $cc CostCentreEntity */
            $ccs[$cc->ccId] = $cc->name;
        }

        foreach (ProjectEntity::find([['archived' => 0]]) as $project) {
            /* @var $project ProjectEntity */
            $projects[$project->projectId] = $project->name;
        }

        $this->response->page('ui/admin/analytics/notifications/view.js', [
            'notifications.ccs'      => NotificationEntity::findBySubjectType(NotificationEntity::SUBJECT_TYPE_CC)->getArrayCopy(),
            'notifications.projects' => NotificationEntity::find([
                ['subjectType' => NotificationEntity::SUBJECT_TYPE_PROJECT],
                ['accountId'   => null]
            ])->getArrayCopy(),
            'reports'                => ReportEntity::find([['accountId' => null]])->getArrayCopy(),
            'ccs'                    => $ccs,
            'projects'               => $projects
        ], [], ['ui/admin/analytics/notifications/view.css']);
    }

    /**
     * @param JsonData $notifications
     */
    public function xSaveAction(JsonData $notifications)
    {
        $data = [];

        foreach ($notifications as $id => $settings) {
            if ($id == 'reports') {
                $this->saveReports($settings);
                $data[$id] = ReportEntity::find([['accountId' => null]])->getArrayCopy();
            } elseif ($id == 'notifications.ccs') {
                $this->saveNotifications(NotificationEntity::SUBJECT_TYPE_CC, $settings);
                $data[$id] = NotificationEntity::findBySubjectType(NotificationEntity::SUBJECT_TYPE_CC)->getArrayCopy();
            } elseif ($id == 'notifications.projects') {
                $this->saveNotifications(NotificationEntity::SUBJECT_TYPE_PROJECT, $settings);
                $data[$id] = NotificationEntity::find([['accountId' => null], ['subjectType' => NotificationEntity::SUBJECT_TYPE_PROJECT]])->getArrayCopy();
            }
        }

        $this->response->data($data);
        $this->response->success('Notifications successfully saved');
    }

}
