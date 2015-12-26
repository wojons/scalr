<?php
use Scalr\Acl\Acl;
use Scalr\UI\Request\JsonData;
use Scalr\Model\Entity\EventDefinition;
use Scalr\DataType\ScopeInterface;

class Scalr_UI_Controller_Scripts_Events extends Scalr_UI_Controller
{
    const CALL_PARAM_NAME = 'eventId';

    public function hasAccess()
    {
        return $this->user->isScalrAdmin() || $this->request->isAllowed(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS);
    }

    public function defaultAction()
    {
        $this->response->page('ui/scripts/events/view.js', [
            'scope' => $this->request->getScope(),
            'events' => $this->getList()
        ]);
    }

    /**
     * @param   string  $scope  Filter for UI
     * @return  array
     * @throws  Scalr_Exception_Core
     */
    public function getList($scope = '')
    {
        $criteria = [];
        switch ($this->request->getScope()) {
            case ScopeInterface::SCOPE_SCALR:
                $criteria[] = ['accountId' => NULL];
                break;

            case ScopeInterface::SCOPE_ACCOUNT:
                $criteria[] = ['$or' => [
                    ['accountId' => $this->user->getAccountId()],
                    ['accountId' => NULL]
                ]];
                $criteria[] = ['envId' => NULL];
                break;

            case ScopeInterface::SCOPE_ENVIRONMENT:
                $criteria[] = ['$or' => [
                    ['accountId' => $this->user->getAccountId()],
                    ['accountId' => NULL]
                ]];
                $criteria[] = ['$or' => [
                    ['envId' => NULL],
                    ['envId' => $this->getEnvironmentId(true)]
                ]];
        }

        /* Filter for UI */
        switch ($scope) {
            case ScopeInterface::SCOPE_SCALR:
                $criteria[] = ['accountId' => NULL];
                break;

            case ScopeInterface::SCOPE_ACCOUNT:
                $criteria[] = ['accountId' => $this->user->getAccountId()];
                $criteria[] = ['envId' => NULL];
                break;

            case ScopeInterface::SCOPE_ENVIRONMENT:
                $criteria[] = ['envId' => $this->getEnvironmentId(true)];
                break;

            default:
                break;
        }

        $data = [];
        foreach (EventDefinition::find($criteria) as $event) {
            /* @var $event EventDefinition */
            $s = get_object_vars($event);
            if ($event->envId) {
                $s['scope'] = 'environment';
            } else if ($event->accountId) {
                $s['scope'] = 'account';
            } else {
                $s['scope'] = 'scalr';
            }
            $s['used'] = $event->getUsed($this->user->getAccountId());
            $s['status'] = $s['used'] ? 'In use' : 'Not used';
            $data[] = $s;
        }

        return $data;
    }

    /**
     * @param   string    $scope
     */
    public function xListAction($scope = '')
    {
        $this->response->data([
            'data' => $this->getList($scope)
        ]);
    }

    /**
     * @param   integer $id
     * @param   string  $name
     * @param   string  $description
     * @param   bool    $replaceEvent
     * @throws  Exception
     * @throws  Scalr_Exception_Core
     */
    public function xSaveAction($id = 0, $name, $description, $replaceEvent = false)
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS, Acl::PERM_GENERAL_CUSTOM_EVENTS_MANAGE);

        $validator = new \Scalr\UI\Request\Validator();
        $validator->addErrorIf(!preg_match("/^[A-Za-z0-9]+$/si", $name), 'name', "Name should contain only alphanumeric characters");
        $validator->addErrorIf(strlen($name) > 25, 'name', "Name should be less than 25 characters");
        $validator->addErrorIf(in_array($name, array_keys(EVENT_TYPE::getScriptingEvents())), 'name',
            sprintf("'%' is reserved name for event. Please select another one.", $name)
        );

        $scope = $this->request->getScope();

        if (! $id) {
            $criteria = [
                ['name' => $name]
            ];
            if ($this->user->isScalrAdmin()) {
                $criteria[] = ['accountId' => NULL];
            } else {
                $criteria[] = ['$or' => [['accountId' => $this->user->getAccountId()], ['accountId' => NULL]]];
                if ($scope == 'account') {
                    $criteria[] = ['envId' => NULL];
                } else {
                    $criteria[] = ['$or' => [['envId' => NULL], ['envId' => $this->getEnvironmentId(true)]]];
                }
            }

            $validator->addErrorIf(EventDefinition::find($criteria)->count(), 'name', 'This name is already in use. Note that Event names are case-insensitive.');

            // check replacements
            $replacements = NULL;
            if ($this->user->isScalrAdmin()) {
                $replacements = EventDefinition::find([
                    ['name'      => $name],
                    ['accountId' => ['$ne' => NULL]]
                ]);
            } else if ($scope == 'account') {
                $replacements = EventDefinition::find([
                    ['name'      => $name],
                    ['accountId' => $this->user->getAccountId()],
                    ['envId'     => ['$ne' => NULL]]
                ]);
            }
        }

        if (!$validator->isValid($this->response))
            return;

        if ($replacements && $replacements->count() && !$replaceEvent) {
            $this->response->data(['replaceEvent' => true]);
            $this->response->failure();
            return;
        }

        if ($id) {
            $event = EventDefinition::findPk($id);
            /* @var $event EventDefinition */
            if (! $event)
                throw new Exception('Event not found');

            if (
                $this->user->isScalrAdmin() && $event->accountId == NULL && $event->envId == NULL ||
                $this->user->isUser() && $event->accountId == $this->user->getAccountId() && ($event->envId == NULL || $event->envId == $this->getEnvironmentId())
            ) {
                $event->description = $description;
            } else {
                throw new Scalr_Exception_InsufficientPermissions();
            }

            $event->save();
        } else {
            $event = new EventDefinition();
            if ($this->user->isScalrAdmin()) {
                $event->accountId = NULL;
                $event->envId = NULL;
            } else {
                $event->accountId = $this->user->getAccountId();
                $event->envId = $scope == 'account' ? NULL : $this->getEnvironmentId();
            }

            $event->name = $name;
            $event->description = $description;
            $event->save();

            if ($replacements) {
                foreach ($replacements as $e) {
                    $e->delete();
                }
            }
        }

        $used = $event->getUsed($this->user->getAccountId());
        $this->response->data([
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'description' => $event->description,
                'used' => $used,
                'scope' => $scope,
                'status' => $used ? 'In use' : 'Not used'
            ]
        ]);

        $this->response->success('Custom event definition successfully saved');
    }

    /**
     * @param JsonData $ids
     * @throws Exception
     */
    public function xGroupActionHandlerAction(JsonData $ids)
    {
        $processed = array();
        $errors = array();

        if (count($ids) == 0)
            throw new Exception('Empty id\'s list');

        foreach (EventDefinition::find(['id' => ['$in' => $ids]]) as $event) {
            /* @var $event EventDefinition */
            if (
                $this->user->isScalrAdmin() && $event->accountId == NULL && $event->envId == NULL ||
                $this->user->isUser() && $event->accountId == $this->user->getAccountId() && ($event->envId == NULL || $event->envId == $this->getEnvironmentId(true))
            ) {
                if ($event->getUsed($this->user->getAccountId())) {
                    $errors[] = 'Custom event is in use and can\'t be removed.';
                } else {
                    $processed[] = $event->id;
                    $event->delete();
                }
            } else {
                $errors[] = 'Insufficient permissions to remove custom event';
            }
        }

        $num = count($ids);
        if (count($processed) == $num) {
            $this->response->success('Custom events successfully removed');
        } else {
            array_walk($errors, function(&$item) { $item = '- ' . $item; });
            $this->response->warning(sprintf("Successfully removed %d from %d custom events. \nFollowing errors occurred:\n%s", count($processed), $num, join($errors, '')));
        }

        $this->response->data(array('processed' => $processed));
    }

    /**
     * @param string $eventName
     * @param int $farmId
     * @param int $farmRoleId
     * @param string $serverId
     * @throws Exception
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function fireAction($eventName = '', $farmId = 0, $farmRoleId = 0, $serverId = '')
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS, Acl::PERM_GENERAL_CUSTOM_EVENTS_FIRE);

        $data['farmWidget'] = self::loadController('Farms', 'Scalr_UI_Controller')->getFarmWidget(array(
            'farmId' => ($farmId == 0 ? '' : (string) $farmId), // TODO: remove (string) and use integer keys for whole project [UI-312]
            'farmRoleId' => (string) $farmRoleId,
            'serverId' => $serverId
        ), array('addAll', 'requiredFarm'));

        $data['eventName'] = $eventName;
        $data['events'] = array_map(
            function($item) {
                if ($item->envId) {
                    $scope = 'environment';
                } else if ($item->accountId) {
                    $scope = 'account';
                } else {
                    $scope = 'scalr';
                }
                return [
                    'name' => $item->name,
                    'description' => $item->description,
                    'scope' => $scope
                ];
            },
            EventDefinition::find([
                ['$or' => [['accountId' => NULL], ['accountId' => $this->user->getAccountId()]]],
                ['$or' => [['envId' => NULL], ['envId' => $this->getEnvironmentId()]]]
            ], null, ['name' => 'asc'])->getArrayCopy()
        );

        $this->response->page('ui/scripts/events/fire.js', $data);
    }

    /**
     * @param   string      $eventName
     * @param   int         $farmId
     * @param   int         $farmRoleId
     * @param   string      $serverId
     * @param   JsonData    $eventParams
     * @throws  Exception
     * @throws  Scalr_Exception_InsufficientPermissions
     */
    public function xFireAction($eventName, $farmId = 0, $farmRoleId = 0, $serverId = '', JsonData $eventParams)
    {
        $this->request->restrictAccess(Acl::RESOURCE_GENERAL_CUSTOM_EVENTS, Acl::PERM_GENERAL_CUSTOM_EVENTS_FIRE);

        if (! EventDefinition::findOne([
            ['name' => $eventName],
            ['$or' => [['accountId' => null], ['accountId' => $this->user->getAccountId()]]],
            ['$or' => [['envId' => null], ['envId' => $this->getEnvironmentId()]]]
        ])) {
            throw new Exception("Event definition not found");
        }

        if ($serverId) {
            $dbServer = DBServer::LoadByID($serverId);
            $this->user->getPermissions()->validate($dbServer);

            $servers = array($dbServer);
        } else if ($farmRoleId) {
            $dbFarmRole = DBFarmRole::LoadByID($farmRoleId);
            $this->user->getPermissions()->validate($dbFarmRole);

            $servers = $dbFarmRole->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));
        } else {
            $dbFarm = DBFarm::LoadByID($farmId);
            $this->user->getPermissions()->validate($dbFarm);

            $servers = $dbFarm->GetServersByFilter(array('status' => SERVER_STATUS::RUNNING));
        }

        if (count($servers) == 0)
            throw new Exception("No running Servers found. Event was not fired.");

        foreach ($servers as $dbServer) {
            /* @var $dbServer DBServer */
            $event = new CustomEvent($dbServer, $eventName, (array)$eventParams);
            Scalr::FireEvent($dbServer->farmId, $event);
        }

        $this->response->success(sprintf("Event successfully fired on behalf of %s Server(s)", count($servers)));
    }
}
