<?php

use Scalr\UI\Request\JsonData;
use Scalr\UI\Utils;
use Scalr\Model\Entity\Announcement;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity\Account\User\UserSetting;
use Scalr\Model\Entity\SettingEntity;
use Scalr\DataType\ScopeInterface;
use Scalr\UI\Request\Validator;
use Scalr\Acl\Acl;

class Scalr_UI_Controller_Announcements extends Scalr_UI_Controller
{
    /**
     * Announcement from Scalr blog
     */
    const TYPE_SCALRBLOG = 'scalrblog';

    /**
     * Announcement from changelog
     */
    const TYPE_CHANGELOG = 'changelog';

    /**
     * User created announcement
     */
    const TYPE_MESSAGE   = 'message';

    /**
     * Default params for announcements retrieving
     */
    const CONFIG_DEFAULT = [
        self::TYPE_SCALRBLOG => [
            'interval'      => 7862400, //91 day
            'feedUrlCfg'    => 'scalr.ui.announcements_rss_url',
            'cacheId'       => SettingEntity::ANNOUNCEMENT_SCALRBLOG_CACHE,
            'cacheLifetime' => 3600
        ],
        self::TYPE_CHANGELOG => [
            'interval'      => 7862400, //91 day
            'feedUrlCfg'    => 'scalr.ui.changelog_rss_url',
            'cacheId'       => SettingEntity::ANNOUNCEMENT_CHANGELOG_CACHE,
            'cacheLifetime' => 3600
        ],
        self::TYPE_MESSAGE   => [
            'interval' => 7862400 //91 day
        ]
    ];

    /**
     * Unix timestamp of the request, which gets announcements for dashboard and popup.
     * Used for announcement filtering. For RSS feeds also used for cache invalidation.
     *
     * @var int
     */
    private $tmRequestDashboard;

    public function hasAccess()
    {
        return $this->user ? true : false;
    }

    public function defaultAction()
    {
        $this->viewAction();
    }

    /**
     * If session is not virtual, - sets UserSetting::NAME_UI_ANNOUNCEMENT_TIME
     *
     * @param int $tm  Unix timestamp
     */
    public function xSetTmAction($tm)
    {
        if (Scalr_Session::getInstance()->isVirtual()) {
            $data = ['tmUpdated' => false];
        } else {
            $this->getUser()->saveSetting(UserSetting::NAME_UI_ANNOUNCEMENT_TIME, $tm);
            $data = ['tmUpdated' => true];

        }
        $data['tm'] = $this->getUser()->getSetting(UserSetting::NAME_UI_ANNOUNCEMENT_TIME);

        $this->response->data($data);
    }

    /**
     * Get announcements for dashboard `Announcement` and popup `What's new in Scalr`
     *
     * @return array
     * @throws Scalr_Exception_Core
     */
    public function xListAnnouncementsAction()
    {
        $data     = [];
        $limit    = 100;
        $this->tmRequestDashboard = time();

        foreach (self::CONFIG_DEFAULT as $type => $config) {
            $provider = null;
            switch ($type) {
                case self::TYPE_CHANGELOG:
                    $provider = 'rssAnnouncements';
                    break;

                case self::TYPE_SCALRBLOG:
                    $provider = 'rssAnnouncements';
                    break;

                case self::TYPE_MESSAGE:
                    $config['accountId'] = $this->getUser()->accountId ?: null;
                    $provider = 'dbAnnouncements';
            }
            if ($provider) {
                $providerData = call_user_func([$this, $provider], $type, $config);
                $data         = array_merge($data, $providerData['data']);
            }
        }

        usort($data, function ($a, $b) {
            // ORDER BY `timestamp` DESC
            return $a['timestamp'] < $b['timestamp'] ? 1 : -1;
        });

        if ($limit < count($data)) {
            array_splice($data, $limit);
        }

        $this->response->data([
            'data'     => $data,
            'tm'       => $this->tmRequestDashboard
        ]);
    }

    /**
     * @param  JsonData  $sort   Sorting order, defaults to added desc
     * @param  string    $query  optional If set search in fields `id` (exact match), `title`, `msg` (partial match)
     * @param  int       $start  optional Offset
     * @param  int       $limit  optional Limit
     * @throws Exception
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xListAction(JsonData $sort, $query = null, $start = 0, $limit = 25)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ANNOUNCEMENTS);

        $criteria = [];
        $scope = $this->request->getScope();
        switch ($scope) {
            case ScopeInterface::SCOPE_SCALR:
                //scalr-scoped messages only
                $criteria[] = ['accountId' => null];
                break;

            case ScopeInterface::SCOPE_ACCOUNT:
                //account-related messages
                $criteria[] = ['$or' => [
                    ['accountId' => null],
                    ['accountId' => $this->getUser()->accountId ?: null]
                ]];
                break;

            default:
                throw new Scalr_Exception_InsufficientPermissions();
        }

        if ($query) {
            $queryLike = '%' . $query . '%';
            $criteria[] = ['$or' => [
                ['id'    => ['$like' => $query]],
                ['title' => ['$like' => $queryLike]],
                ['msg'   => ['$like' => $queryLike]]
            ]];
        }

        $result = Announcement::find($criteria, null, Utils::convertOrder($sort, ['added' => false], ['id', 'title', 'msg', 'added']), $limit, $start, true);
        $data = [];

        /* @var $announcement Scalr\Model\Entity\Announcement */
        foreach ($result as $announcement) {
            $data[] = $this->prepareDataForList($announcement);
        }

        $this->response->data([
            'total' => $result->totalNumber,
            'data'  => $data
        ]);
    }

    /**
     * Add or update announcement message
     *
     * @param  string  $msg   Announcement's text
     * @param  string  $title Announcement's title
     * @param  int     $id    optional Announcement's ID
     * @throws Exception
     * @throws Scalr_Exception_Core
     * @throws Scalr_Exception_InsufficientPermissions
     * @throws \Scalr\Exception\ModelException
     */
    public function xSaveAction($msg, $title, $id = null)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ANNOUNCEMENTS);

        /* @var $announcement Scalr\Model\Entity\Announcement */
        if (empty($id)) {
            $announcement = new Announcement();
            /* @var $user Scalr\Model\Entity\Account\User */
            $user = $this->getUser();
            $announcement->accountId      = $user->accountId ?: null;
            $announcement->createdById    = $user->id;
            $announcement->createdByEmail = $user->email;
            $announcement->added          = new \DateTime();
        } else {
            $announcement = Announcement::findPk($id);

            if (!$announcement) {
                throw new Exception('Announcement was not found');
            }

            $this->request->checkPermissions($announcement, true);
        }

        $validator = new Validator();
        $validator->validate($msg, 'msg', $validator::NOEMPTY);
        $validator->validate($title, 'title', $validator::NOEMPTY);
        $validator->addErrorIf(strlen($title) > 100, 'title', 'Maximum length for this field is 100');

        if (!$validator->isValid($this->response)) {
            return;
        }

        $announcement->title = $title;
        $announcement->msg   = $msg;
        $announcement->save();

        $this->response->data(['announcement' => $this->prepareDataForList($announcement)]);
        $this->response->success("Announcement saved");
    }

    /**
     * @param  JsonData $ids Array of announcements' ids to remove
     * @throws Scalr_Exception_Core
     * @throws \Scalr\Exception\ModelException
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function xRemoveAction(JsonData $ids)
    {
        $this->request->restrictAccess(Acl::RESOURCE_ANNOUNCEMENTS);

        $processed = [];
        $errors = [];

        foreach ($ids as $messageId) {
            /* @var $announcement Scalr\Model\Entity\Announcement */
            $announcement = Announcement::findPk($messageId);

            if (!$announcement) {
                $errors[] = sprintf('Announcement with ID "%u" was not found', $messageId);
            } elseIf (!$this->request->hasPermissions($announcement, true)) {
                $errors[] = sprintf('You don\'t have permissions to remove announcement with ID: "%u"', $messageId);
            } else {
                $announcement->delete();
                $processed[] = $messageId;
            }
        }

        if (count($errors)) {
            $this->response->warning("Announcements successfully removed, but some errors occurred:\n" . implode("\n", $errors));
        } else {
            $this->response->success("Announcements successfully removed");
        }

        $this->response->data(['processed' => $processed]);
    }


    /**
     * @throws Scalr_Exception_InsufficientPermissions
     */
    public function viewAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_ANNOUNCEMENTS);

        $this->response->page('ui/announcement/view.js');
    }

    /**
     * Transform entity of type MESSAGE for client-side (dashboard, popup)
     *
     * @param  Announcement $announcement
     * @return array  Contains keys `type`, `title`, `msg` `time`, `timestamp`.
     */
    private function prepareDataForDashboard(Announcement $announcement)
    {
        return [
            'type'      => self::TYPE_MESSAGE,
            'title'     => $announcement->title,
            'msg'       => $announcement->msg,
            'time'      => Scalr_Util_DateTime::convertTz($announcement->added, 'M d Y'),
            'timestamp' => $announcement->added->getTimestamp()
        ];
    }

    /**
     * Transform entity of type MESSAGE for client-side (view, edit)
     *
     * @param  Announcement $announcement
     * @return array  Contains keys  `id`, `accountId`, `added`, `title`, `msg`, `user`.
     */
    private function prepareDataForList(Announcement $announcement)
    {
        $obj = [
            'id'        => $announcement->id,
            'accountId' => $announcement->accountId,
            'added'     => Scalr_Util_DateTime::convertTz($announcement->added, 'M d, Y G:i'),
            'title'     => $announcement->title,
            'msg'       => $announcement->msg
        ];

        /* @var $user Scalr\Model\Entity\Account\User */
        $user = User::findPk($announcement->createdById);
        if ($user) {
            $obj['user'] = ['name' => $user->fullName, 'email' => $user->email];
        } else {
            $obj['user'] = ['name' => '#' . $announcement->createdById, 'email' => $announcement->createdByEmail];
        }

        return $obj;
    }

    /**
     * Get RSS feed content
     *
     * @param  string $url  RSS feed's URL
     * @return SimpleXMLElement|false
     */
    private function getRssFeedXml($url) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $content = curl_exec($curl);
        curl_close($curl);

        return empty($content) ? false : simplexml_load_string($content);
    }

    /**
     * Get announcements from rss feed or from rss cache if any
     *
     * @param  $type    string  Announcement type
     * @param  $params  mixed[] See self::CONFIG_DEFAULT
     *                  - interval      int     Diff in seconds from NOW. For announcements filtering.
     *                  - feedUrlCfg    string  Name of the config parameter with RSS feed url.
     *                  - cacheId       string  ID of SettingEntity, holding cache.
     *                  - cacheLifetime int     Cache lifetime in seconds.
     * @return array
     * @throws \Scalr_Exception_Core
     */
    private function rssAnnouncements($type, $params)
    {
        $now            = $this->tmRequestDashboard;
        $minTm          = $now - $params['interval'];
        $cacheId        = $params['cacheId'];
        $cacheLifeTime  = $params['cacheLifetime'];
        $data           = [];
        $limit          = 100;
        $feedUrl        = $this->getContainer()->config->get($params['feedUrlCfg']);
        $cache          = SettingEntity::getValue($cacheId);

        if (empty($feedUrl)) {
            if (!empty($cache)) {
                SettingEntity::setValue($cacheId, null);
            }

            return ['data' => []];
        }

        /* @var $cacheObj  array */
        if (!empty($cache)) {
            $cacheObj = unserialize($cache);
        }

        if (isset($cacheObj) && ($now - $cacheObj['tm'] < $cacheLifeTime)) {
            $data = $cacheObj['data'];
        } else {
            /* @var $feedXml SimpleXMLElement */
            $feedXml = $this->getRssFeedXml($feedUrl);
            if ($feedXml !== false) {
                switch ($type) {
                    case self::TYPE_CHANGELOG:
                        foreach ($feedXml->entry as $key => $item) {
                            $published = strtotime((string)$item->published);
                            $data[] = [
                                'title'     => (string)$item->title,
                                'url'       => (string)$item->link->attributes()->href,
                                'timestamp' => $published
                            ];
                        }
                        break;

                    case self::TYPE_SCALRBLOG:
                        foreach ($feedXml->channel->item as $key => $item) {
                            $published = strtotime((string)$item->pubDate);
                            $data[] = [
                                'title'     => (string)$item->title,
                                'url'       => (string)$item->link,
                                'timestamp' => $published
                            ];
                        }
                }
            }
            SettingEntity::setValue($cacheId, serialize([
                'tm'   => $now,
                'data' => $data
            ]));
        }

        $count = 0;
        foreach ($data as &$item) {
            if ($limit < $count || $minTm > $item['timestamp']) {
                break;
            }
            $item['type'] = $type;
            $item['time'] = date('M d Y', $item['timestamp']);
            $count++;
        }

        if ($count < count($data)) {
            array_splice($data, $count);
        }

        return ['data' => $data];
    }

    /**
     * Get announcements from DB ordered by `added` desc
     *
     * @param  $type   string  Announcement type, equals self::TYPE_MESSAGE
     * @param  $params mixed[]
     *                 - interval  int Diff in seconds from NOW. For announcements filtering.
     *                 - accountId int optional Account ID
     * @return array
     */
    private function dbAnnouncements($type, $params)
    {
        $data  = [];
        $minDt = (new \DateTime())->setTimestamp($this->tmRequestDashboard - $params['interval']);
        $limit = 100;

        if (empty($params['accountId'])) {
            $criteria = [['accountId' => null]];
        } else {
            $criteria = ['$or' => [
                ['accountId' => $params['accountId']],
                ['accountId' => null]
            ]];
        }

        $criteria[] = ['added' => ['$gte' => $minDt]];

        $result = Announcement::find($criteria, null, ['added' => false], $limit);

        /* @var $announcement Scalr\Model\Entity\Announcement */
        foreach ($result as $announcement) {
            $data[] = $this->prepareDataForDashboard($announcement);
        }

        return ['data' => $data];
    }
}
