<?php

use Scalr\Model\Entity\ScalrHost;
use Scalr\Model\Entity\ScalrService;
use Scalr\Model\Entity\Account\User;

class Scalr_UI_Controller_Dashboard_Widget_Scalrhealth extends Scalr_UI_Controller_Dashboard_Widget
{
    public function getDefinition()
    {
        return [
            'type' => 'local'
        ];
    }

    public function getContent($params = [])
    {
        if ($this->user->getType() != User::TYPE_SCALR_ADMIN) {
            throw new Scalr_Exception_InsufficientPermissions();
        }

        $hosts = [];

        foreach (ScalrHost::find() as $host) {
            $hosts[] = [
                'host'      => $host->host,
                'version'   => $host->version,
                'edition'   => $host->edition,
                'revision'  => empty($host->gitCommit) ? '' : $host->gitCommit,
                'revDate'   => empty($host->gitCommitAdded) ? '' : $host->gitCommitAdded->format('Y-m-d H:i:s O')
            ];
        }

        $stateNames = ScalrService::listStateNames();
        $allServices = [];

        foreach (ScalrService::find([['name' => ['$nin' => ScalrService::EXCLUDED_SERVICES]]]) as $scalrService) {
            $lastTime = empty($scalrService->lastFinish) ? time() : $scalrService->lastFinish->getTimestamp();

            $allServices[] = [
                'name'         => ucfirst(str_replace("_", " ", $scalrService->name)),
                'numWorkers'   => $scalrService->numWorkers,
                'numTasks'     => $scalrService->numTasks,
                'lastStart'    => !empty($scalrService->lastStart) ? Scalr_Util_DateTime::getIncrescentTimeInterval($scalrService->lastStart) : '',
                'timeSpent'    => !empty($scalrService->lastStart) ? $lastTime - $scalrService->lastStart->getTimestamp() : '',
                'state'        => $stateNames[$scalrService->state]
            ];
        }

        return [
            'hosts' => $hosts,
            'services' => $allServices
        ];
    }

    /**
     * Remove host
     *
     * @param string $hostName host primary key
     * @throws Exception
     */
    public function xRemoveAction($hostName)
    {
        $host = ScalrHost::findPk($hostName);
        if (! $host) {
            throw new Exception('Host not found');
        }

        $deletedHost = $host->host;
        $host->delete();

        $this->response->data(['host' => $deletedHost]);
        $this->response->success('Host successfully removed');
    }

    public function hasAccess()
    {
        return $this->getUser()->isScalrAdmin();
    }
}
