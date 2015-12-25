<?php

use Scalr\Acl\Acl;
use Scalr\Model\Entity;

class Scalr_UI_Controller_Db_Backups extends Scalr_UI_Controller
{
    public function hasAccess()
    {
        return parent::hasAccess() && $this->request->isAllowed(Acl::RESOURCE_DB_BACKUPS);
    }

    public function defaultAction()
    {
        $farms = self::loadController('Farms')->getList();
        array_unshift($farms, array('id' => 0, 'name' => 'All farms'));

        $data = $this->getBackupsList();
        $this->response->page('ui/db/backups/view.js', array(
                'farms' => $farms,
                'backups' => $data,
                'env' => $this->user->getEnvironments()
            ),
            array('ui/db/backups/calendarviews.js'),
            array('ui/db/backups/view.css')
        );
    }

    public function detailsAction()
    {
        $this->response->page('ui/db/backups/details.js',
            array(
                'backup' => $this->getBackupDetails($this->getParam('backupId'))
            ), array(), array( 'ui/db/backups/view.css')
        );
    }

    public function xGetListBackupsAction()
    {
        $this->response->data(array('backups' => $this->getBackupsList($this->getParam('time'))));
    }

    private function getBackupsList($time = '')
    {
        $data = array();
        $time = ($time == '') ? time() : strtotime($time);

        $query = "
            SELECT b.id AS backupId, b.farm_id AS farmId, b.service AS serviceName, b.dtcreated AS time, f.name AS farmName
            FROM `services_db_backups` b
            LEFT JOIN `farms` f ON b.farm_id = f.id
            WHERE b.status = ? AND b.env_id = ?
            AND DATE_FORMAT(CONVERT_TZ(b.dtcreated, 'SYSTEM', ?), '%Y-%m') = ?
        ";

        $userTimezone = $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE);
        if (empty($userTimezone)) {
            $userTimezone = 'SYSTEM';
        }

        $args = array(Scalr_Db_Backup::STATUS_AVAILABLE, $this->getEnvironmentId(), $userTimezone, date('Y-m', $time));

        if ($this->getParam('farmId')) {
            $query .= ' AND b.farm_id = ?';
            $args[] = $this->getParam('farmId');
        }

        list($query, $args) = $this->request->prepareFarmSqlQuery($query, $args, 'f');
        $dbBackupResult = $this->db->GetAll($query, $args);
        foreach ($dbBackupResult as $row) {
            $dt = new DateTime($row['time']);
            Scalr_Util_DateTime::convertTimeZone($dt, $this->user->getSetting(Scalr_Account_User::SETTING_UI_TIMEZONE));

            $row['time'] = $dt->format('h:ia');

            if (empty($row['farmName'])) {
                $row['farmName'] = '*removed farm*';
            }

            $data[$dt->format('j M')][] = $row;
        }

        return $data;
    }

    private function getBackupDetails($backupId)
    {
        $links = array();
        /* @var $backup \Scalr_Db_Backup */
        $backup = Scalr_Db_Backup::init()->loadById($backupId);

        $this->user->getPermissions()->validate($backup);

        $data = array(
            'backup_id'      => $backup->id,
            'farm_id'        => $backup->farmId,
            'type'           => ROLE_BEHAVIORS::GetName($backup->service) ? ROLE_BEHAVIORS::GetName($backup->service) : 'unknown',
            'date'           => Scalr_Util_DateTime::convertTz($backup->dtCreated),
            'size'           => $backup->size ? round($backup->size / 1024 / 1024, 2) : 0,
            'provider'       => $backup->provider,
            'cloud_location' => $backup->cloudLocation,
            'farmName'       => DBFarm::LoadByIDOnlyName($backup->farmId)
        );
        $downloadParts = $backup->getParts();

        foreach ($downloadParts as $part) {
            $part['size'] = $part['size'] ? round($part['size']/1024/1024, 2) : '';
            if ($part['size'] == 0)
                $part['size'] = 0.01;

            if ($data['provider'] == 's3') {
                $part['link'] = $this->getS3SignedUrl($part['path']);
            } else if ($data['provider'] == 'cf') {
                if ($backup->platform == SERVER_PLATFORMS::RACKSPACE)
                    $part['link'] = $this->getCfSignedUrl($part['path'], $data['cloud_location'], $backup->platform);
                else
                    $part['link'] = $this->getSwiftSignerUrl($part['path'], $backup->platform, $backup->cloudLocation);
            } else if ($data['provider'] == 'gcs') {
                $part['link'] = $this->getGcsSignedUrl($part['path']);
            } else
                continue;

            $part['path'] = pathinfo($part['path']);
            $links[$part['number']] = $part;
        }
        $data['links'] = $links;

        return $data;
    }

    public function xRemoveBackupAction()
    {
        $this->request->restrictAccess(Acl::RESOURCE_DB_BACKUPS, Acl::PERM_DB_BACKUPS_REMOVE);

        $backup = Scalr_Db_Backup::init()->loadById($this->getParam('backupId'));
        $this->user->getPermissions()->validate($backup);

        $backup->delete();
        $this->response->success('Backup successfully queued for removal.');
    }

    private function getS3SignedUrl($path)
    {
        $bucket = substr($path, 0, strpos($path, '/'));
        $resource = substr($path, strpos($path, '/') + 1, strlen($path));
        $expires = time() + 3600;

        $AWSAccessKey = $this->getEnvironment()->cloudCredentials(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_ACCESS_KEY];
        $AWSSecretKey = $this->getEnvironment()->cloudCredentials(SERVER_PLATFORMS::EC2)->properties[Entity\CloudCredentialsProperty::AWS_SECRET_KEY];

        $stringToSign = "GET\n\n\n{$expires}\n/" . str_replace(".s3.amazonaws.com", "", $bucket) . "/{$resource}";

        $signature = urlencode(base64_encode(hash_hmac("sha1", utf8_encode($stringToSign), $AWSSecretKey, TRUE)));

        $authenticationParams = "AWSAccessKeyId={$AWSAccessKey}&Expires={$expires}&Signature={$signature}";

        return $link = "http://{$bucket}.s3.amazonaws.com/{$resource}?{$authenticationParams}";
    }

    private function getCfSignedUrl($path, $location, $platform)
    {
        $expires = time() + 3600;

        $ccProps = $this->environment->cloudCredentials("{$location}." . $platform)->properties;

        $user = $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_USERNAME];
        $key = $ccProps[Entity\CloudCredentialsProperty::RACKSPACE_API_KEY];

        $cs = Scalr_Service_Cloud_Rackspace::newRackspaceCS($user, $key, $location);

        $auth = $cs->authToReturn();

        $stringToSign = "GET\n\n\n{$expires}\n/{$path}";
        $signature = urlencode(base64_encode(hash_hmac("sha1", utf8_encode($stringToSign), $key, true)));

        $authenticationParams = "temp_url_sig={$signature}&temp_url_expires={$expires}";

        $link = "{$auth['X-Cdn-Management-Url']}/{$path}?{$authenticationParams}";

        return $link;
    }

    public function getGcsSignedUrl($path)
    {
        $expires = time() + 3600;
        $stringToSign = "GET\n\n\n{$expires}\n/{$path}";
        $link = "http://storage.googleapis.com/{$path}";
        $googleAccessId = str_replace('.apps.googleusercontent.com', '@developer.gserviceaccount.com', $this->environment->cloudCredentials(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_CLIENT_ID]);

        $signer = new Google_Signer_P12(
            base64_decode($this->environment->cloudCredentials(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_KEY]),
            $this->environment->cloudCredentials(SERVER_PLATFORMS::GCE)->properties[Entity\CloudCredentialsProperty::GCE_JSON_KEY] ? null : 'notasecret'
        );
        $signature = $signer->sign($stringToSign);
        $signature = urlencode(base64_encode($signature));

        return "{$link}?GoogleAccessId={$googleAccessId}&Expires={$expires}&Signature={$signature}";
    }

    public function getSwiftSignerUrl($path, $platform, $cloudLocation)
    {
        $expires = time() + 3600;
        $method = 'GET';

        $rs = $this->environment->openstack($platform, $cloudLocation);
        $basePath = $rs->swift->getEndpointUrl();
        $objectPath = explode("/v1/", $basePath);

        $stringToSign = "{$method}\n{$expires}\n/v1/{$objectPath[1]}/{$path}";

        $response = $rs->swift->describeService();
        $key = $response->getHeader('X-Account-Meta-Temp-Url-Key');
        if (! $key) {
            $key = Scalr::GenerateRandomKey(32);
            $rs->swift->updateService(array(
                '_headers' => array('X-Account-Meta-Temp-URL-Key' => $key)
            ));
        }

        $signature = urlencode(hash_hmac("sha1", utf8_encode($stringToSign), $key));
        return "{$basePath}/{$path}?temp_url_sig={$signature}&temp_url_expires={$expires}";
    }
}
