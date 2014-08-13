<?php

use Scalr\Stats\CostAnalytics\Entity\SettingEntity;
use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\CostCentreEntity;
use Scalr\Stats\CostAnalytics\Entity\ProjectEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;
use Scalr\UI\Request\JsonData;

class Scalr_UI_Controller_Analytics_Notifications extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return $this->user->isAdmin();
    }

    public function defaultAction()
    {
        $ccs = array();
        $projects = array();

        foreach (CostCentreEntity::find([['archived' => 0]]) as $cc) {
            $ccs[$cc->ccId] = $cc->name;
        }

        foreach (ProjectEntity::find([['archived' => 0]]) as $project) {
            $projects[$project->projectId] =$project->name;
        }

        $this->response->page('ui/analytics/notifications/view.js', array(
            'notifications.ccs' => array(
                'enabled' => SettingEntity::getValue(SettingEntity::ID_NOTIFICATIONS_CCS_ENABLED),
                'items'   => NotificationEntity::findBySubjectType(NotificationEntity::SUBJECT_TYPE_CC)
            ),
            'notifications.projects' => array(
                'enabled' => SettingEntity::getValue(SettingEntity::ID_NOTIFICATIONS_PROJECTS_ENABLED),
                'items'   => NotificationEntity::findBySubjectType(NotificationEntity::SUBJECT_TYPE_PROJECT)
            ),
            'reports' => array(
                'enabled' => SettingEntity::getValue(SettingEntity::ID_REPORTS_ENABLED),
                'items'   => ReportEntity::all()
            ),
            'ccs' => $ccs,
            'projects' => $projects
        ), array(), array());
    }

    /**
     * @param JsonData $notifications
     */
    public function xSaveAction(JsonData $notifications)
    {
        foreach ($notifications as $id => $settings) {
            if ($id == SettingEntity::ID_REPORTS_ENABLED) {
                $this->saveReports(SettingEntity::ID_REPORTS_ENABLED, $settings);
            } elseif ($id == SettingEntity::ID_NOTIFICATIONS_CCS_ENABLED) {
                $this->saveNotifications($id, NotificationEntity::SUBJECT_TYPE_CC, $settings);
            } elseif ($id == SettingEntity::ID_NOTIFICATIONS_PROJECTS_ENABLED) {
                $this->saveNotifications($id, NotificationEntity::SUBJECT_TYPE_PROJECT, $settings);
            }
        }
    }

    private function saveNotifications($id, $subjectType, $settings)
    {
        SettingEntity::setValue($id, $settings['enabled']);

        $uuids = array();
        foreach ($settings['items'] as $item) {
            $notification = new NotificationEntity();
            if ($item['uuid']) {
                $notification->findPk($item['uuid']);
            }
            $notification->subjectType = $subjectType;
            $notification->notificationType = $item['notificationType'];
            $notification->threshold = $item['threshold'];
            $notification->recipientType = $item['recipientType'];
            $notification->emails = $item['emails'];
            $notification->save();
            $uuids[] = $notification->uuid;
        }
        foreach (NotificationEntity::findBySubjectType($subjectType) as $notification) {
            if (!in_array($notification->uuid, $uuids)) {
                $notification->delete();
            }
        }
    }

    private function saveReports($id, $settings)
    {
        SettingEntity::setValue($id, $settings['enabled']);
        $uuids = array();
        foreach ($settings['items'] as $item) {
            $report = new ReportEntity();
            if ($item['uuid']) {
                $report->findPk($item['uuid']);
            }
            $report->subjectType = $item['subjectType'];

            $subject = null;
            if ($report->subjectType == ReportEntity::SUBJECT_TYPE_CC) {
                $subject = $this->getContainer()->analytics->ccs->get($item['subjectId']);
            } elseif ($report->subjectType == ReportEntity::SUBJECT_TYPE_PROJECT) {
                $subject = $this->getContainer()->analytics->projects->get($item['subjectId']);
            } else {
                $report->subjectType = null;
                $report->subjectId = null;
            }

            if ($report->subjectType) {
                if ($item['subjectId'] && !$subject) {
                    throw new Scalr_UI_Exception_NotFound();
                }
                $report->subjectId = $item['subjectId'] ? $item['subjectId'] : null;
            }
            $report->period = $item['period'];
            $report->emails = $item['emails'];
            $report->save();
            $uuids[] = $report->uuid;
        }
        foreach (ReportEntity::all() as $report) {
            if (!in_array($report->uuid, $uuids)) {
                $report->delete();
            }
        }
    }

}
