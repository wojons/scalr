<?php

use Scalr\Acl\Acl;
use Scalr\Service\Aws\Rds\DataType\DescribeEventRequestData;

class Scalr_UI_Controller_Tools_Aws_Rds extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        if (!parent::hasAccess() || !$this->request->isAllowed(Acl::RESOURCE_AWS_RDS)) return false;

        if (!in_array(SERVER_PLATFORMS::EC2, $this->getEnvironment()->getEnabledPlatforms()))
            throw new Exception("You need to enable RDS platform for current environment");

        return true;
    }

    public function logsAction()
    {
        $this->response->page('ui/tools/aws/rds/logs.js');
    }

    /**
     *
     * @param string $cloudLocation
     * @param string $name          optional
     * @param string $type          optional
     */
    public function xListLogsAction($cloudLocation, $name = null, $type = null)
    {
        $request = new DescribeEventRequestData();
        $request->sourceIdentifier = $name ?: null;
        $request->sourceType = $type ?: null;
        $events = $this->environment->aws($cloudLocation)->rds->event->describe($request);
        $logs = array();
        /* @var $event \Scalr\Service\Aws\Rds\DataType\EventData */
        foreach ($events as $event) {
            if ($event->message) {
                $logs[] = array(
                    'Message' => $event->message,
                    'Date' => $event->date,
                    'SourceIdentifier' => $event->sourceIdentifier,
                    'SourceType' => $event->sourceType,
                );
            }
        }
        $response = $this->buildResponseFromData($logs, ['Date', 'Message']);
        foreach ($response['data'] as &$row) {
            $row['Date'] = Scalr_Util_DateTime::convertTz($row['Date']);
        }

        $this->response->data($response);
    }
}
