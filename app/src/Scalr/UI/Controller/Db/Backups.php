<?php
class Scalr_UI_Controller_Db_Backups extends Scalr_UI_Controller
{
	public function hasAccess()
	{
		return true;
	}

	public function defaultAction()
	{
		$data = $this->getBackupsList();
		$this->response->page( 'ui/db/backups/view.js', array(
			'backups' => $data, 
			'env' => $this->user->getEnvironments()
		), 
		array( 'ui/db/backups/calendarviews.js' ),
		array( 'ui/db/backups/view.css' ) );
	}

	public function detailsAction ()
	{
		$this->response->page( 'ui/db/backups/details.js', array(
			'backup' => $this->getBackupDetails( $this->getParam( 'backupId' ) )
		), array(), array( 'ui/db/backups/view.css' ) );
	}

	public function xGetListBackupsAction() {
		$this->response->data( array( 'backups' => $this->getBackupsList( $this->getParam( 'time' ) ) ) );
	}

	private function getBackupsList( $time = '' ) {
		$data = array();
		$time = ( $time == '' ) ? time() : strtotime($time);

		$sql = 'SELECT id as backup_id, farm_id, service as role, dtcreated as date FROM services_db_backups WHERE status = ? AND env_id = ?';

		if( !$this->getParam( 'query' ) && !$this->getParam( 'farmId' ) )
			$sql.= ' AND DATE_FORMAT(dtcreated, "%Y-%m") = ?';
		else
			$sql.= ' AND DATE_FORMAT(dtcreated, "%Y") = ?';

		if ( $this->getParam( 'query' ) )
			$sql.= ' AND :FILTER:';

		if ( $this->getParam( 'farmId' ) )
			$sql.= ' AND farm_id = '.$this->db->qstr($this->getParam('farmId'));

		$dbBackupResult = $this->buildResponseFromSql(
			$sql,
			array(),
			array( 'service' ),
			array(
				Scalr_Db_Backup::STATUS_AVAILABLE,
				$this->getEnvironmentId(),
				( $this->getParam( 'query' ) || $this->getParam( 'farmId' ) ) ? date( "Y", $time ) : date( "Y-m", $time )
			),
			true
		);
		$dbBackupResult = $dbBackupResult['data'];

		foreach ( $dbBackupResult as $row ) {
			$date = strtotime(Scalr_Util_DateTime::convertTz($row['date']));
			$row['date'] = date('h:ia ',$date);
			$row['farm'] = DBFarm::LoadByIDOnlyName($row['farm_id']);
			$data[date('n Y', $date)][date('j F o', $date)][date('H:i', $date)] = $row;
		}
		
		return $data;
	}

	private function getBackupDetails($backupId) {
		
		$links = array();
		$backup = Scalr_Db_Backup::init()->loadById($backupId);
		
		$this->user->getPermissions()->validate($backup);
		
		$data = array(
			'backup_id' => $backup->id,
			'farm_id'	=> $backup->farmId,
			'type'		=> ROLE_BEHAVIORS::GetName($backup->service) ? ROLE_BEHAVIORS::GetName($backup->service) : 'unknown',
			'date'		=> Scalr_Util_DateTime::convertTz($backup->dtCreated),
			'size'		=> $backup->size ? round($backup->size / 1024 / 1024, 2) : 0,
			'provider'	=> $backup->provider,
			'cloud_location' => $backup->cloudLocation,
			'farmName'	=> DBFarm::LoadByIDOnlyName($backup->farmId)
		);
		$downloadParts = $backup->getParts();

		foreach ($downloadParts as $part) {
			$part['size'] = $part['size'] ? round($part['size']/1024/1024, 2) : 0;
			$part['link'] = $data['provider'] == 's3' ? $this->getS3SignedUrl($part['path']) : $this->getCfSignedUrl($part['path'], $data['cloud_location']);
			$part['path'] = pathinfo($part['path']);
			$links[$part['number']] = $part;
		}
		$data['links'] = $links;
		return $data;
	}

	public function xRemoveBackupAction () {
		
		$backup = Scalr_Db_Backup::init()->loadById($this->getParam('backupId'));
		$this->user->getPermissions()->validate($backup);
		
		$backup->delete();
		$this->response->success('Backup successfully queued for removal.');
	}

	private function getS3SignedUrl($path) {
		 $bucket = substr($path, 0, strpos($path, '/'));
		 $resource = substr($path, strpos($path, '/') + 1, strlen($path));
		 $expires = time() + 3600;

		 $AWSAccessKey = $this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::ACCESS_KEY);
		 $AWSSecretKey = $this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Ec2::SECRET_KEY);

		 $stringToSign = "GET\n\n\n{$expires}\n/" . str_replace(".s3.amazonaws.com", "", $bucket) . "/{$resource}";
		 $signature = urlencode(
			 			base64_encode(
							hash_hmac( "sha1", utf8_encode( $stringToSign ), $AWSSecretKey, TRUE )
						)
		 			);

		 $authenticationParams = "AWSAccessKeyId={$AWSAccessKey}&Expires={$expires}&Signature={$signature}";

		 return $link = "http://{$bucket}.s3.amazonaws.com/{$resource}?{$authenticationParams}";
	}

	private function getCfSignedUrl($path, $location) {
		$expires = time() + 3600;

		$user = $this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Rackspace::USERNAME, true, $location);
		$key = $this->getEnvironment()->getPlatformConfigValue(Modules_Platforms_Rackspace::API_KEY, true, $location);

		$cs = Scalr_Service_Cloud_Rackspace::newRackspaceCS($user, $key, $location);
		$auth = $cs->authToReturn();

		$stringToSign = "GET\n\n\n{$expires}\n/{$path}";
		$signature = urlencode(
						base64_encode(
							hash_hmac("sha1", utf8_encode( $stringToSign ), $key, true)
						)
					);

		$authenticationParams = "temp_url_sig={$signature}&temp_url_expires={$expires}";

		$link = "{$auth['X-Cdn-Management-Url']}/{$path}?{$authenticationParams}";
		return $link;
	}
}