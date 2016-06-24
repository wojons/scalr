<?php

namespace Scalr\UI\Controller\Account2\Analytics;

use Scalr_UI_Exception_NotFound;
use Scalr\Stats\CostAnalytics\Entity\NotificationEntity;
use Scalr\Stats\CostAnalytics\Entity\ReportEntity;

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

            $notification->accountId = $this->user->getAccountId();
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
            ['accountId'   => $this->user->getAccountId()]
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

            $report->accountId = $this->user->getAccountId();
            $report->subjectType = $item['subjectType'];

            $subject = null;

            if ($report->subjectType == ReportEntity::SUBJECT_TYPE_PROJECT && $item['subjectId']) {
                $subject = $this->getContainer()->analytics->projects->get($item['subjectId']);
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
            ['accountId'   => $this->user->getAccountId()],
            ['subjectType' => ReportEntity::SUBJECT_TYPE_PROJECT]
        ];

        if ($projectId) {
            $criteria[] = ['subjectId' => $projectId];
        }

        foreach (ReportEntity::find($criteria)as $report) {
            /* @var $report ReportEntity */
            if (!in_array($report->uuid, $uuids) && $report->hasAccessPermissions($this->getUser())) {
                $report->delete();
            }
        }
    }
}