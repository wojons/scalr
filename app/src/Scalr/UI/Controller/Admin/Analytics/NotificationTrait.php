<?php

namespace Scalr\UI\Controller\Admin\Analytics;

use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;
use Scalr_UI_Exception_NotFound;

/**
 * NotificationTrait
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 */
trait NotificationTrait
{
    /**
     * Saves/modifies/deletes notifications
     *
     * @param int   $subjectType Notification subject type
     * @param array $settings    Array of notifications to create/modify
     * @param string $projectId  optional Projects id.
     * @throws \Scalr\Exception\ModelException
     */
    protected function saveNotifications($subjectType, $settings, $projectId = null)
    {
        $uuids = [];

        foreach ($settings['items'] as $item) {
            $notification = new NotificationEntity();

            if ($item['uuid']) {
                $notification->findPk($item['uuid']);

                if (!$notification->hasAccessPermissions($this->getUser())) {
                    continue;
                }
            }

            $notification->subjectType = $subjectType;
            $notification->subjectId = $item['subjectId'] ? $item['subjectId'] : null;
            $notification->notificationType = $item['notificationType'];
            $notification->threshold = $item['threshold'];
            $notification->recipientType = $item['recipientType'];
            $notification->emails = $item['emails'];
            $notification->status = $item['status'];
            $notification->save();

            $uuids[] = $notification->uuid;
        }

        $criteria = [
            ['subjectType' => $subjectType],
            ['accountId'   => null]
        ];

        if ($projectId) {
            $criteria[] = ['subjectId' => $projectId];
        }

        foreach (NotificationEntity::find($criteria) as $notification) {
            /* @var $notification NotificationEntity */
            if (!in_array($notification->uuid, $uuids) && $notification->hasAccessPermissions($this->getUser())) {
                $notification->delete();
            }
        }
    }

    /**
     * Saves/modifies/deletes reports
     *
     * @param array $settings    Array of reports to create/modify
     * @param string $projectId  optional Projects id.
     * @throws Scalr_UI_Exception_NotFound
     * @throws \Scalr\Exception\AnalyticsException
     * @throws \Scalr\Exception\ModelException
     */
    protected function saveReports($settings, $projectId = null)
    {
        $uuids = [];

        foreach ($settings['items'] as $item) {
            $report = new ReportEntity();

            if ($item['uuid']) {
                $report->findPk($item['uuid']);

                if (!$report->hasAccessPermissions($this->getUser())) {
                    continue;
                }
            }

            $report->subjectType = $item['subjectType'];

            $subject = null;

            if ($report->subjectType == ReportEntity::SUBJECT_TYPE_CC && $item['subjectId']) {
                $subject = $this->getContainer()->analytics->ccs->get($item['subjectId']);
            } elseif ($report->subjectType == ReportEntity::SUBJECT_TYPE_PROJECT && $item['subjectId']) {
                $subject = $this->getContainer()->analytics->projects->get($item['subjectId']);
            } elseif ($item['subjectType'] == -1) {
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
            $report->status = $item['status'];
            $report->save();

            $uuids[] = $report->uuid;
        }

        $criteria = [
            ['accountId'   => null]
        ];

        if ($projectId) {
            $criteria[] = ['subjectId' => $projectId];
        }

        foreach (ReportEntity::find($criteria) as $report) {
            /* @var $report ReportEntity */
            if (!in_array($report->uuid, $uuids) && $report->hasAccessPermissions($this->getUser())) {
                $report->delete();
            }
        }
    }
}