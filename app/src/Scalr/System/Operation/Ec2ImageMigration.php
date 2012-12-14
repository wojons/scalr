<?php

class Scalr_System_OperationResource
{
	const TYPE_PERMANENT = 'permanent';
	const TYPE_TEMPORARY = 'tmp';
	
	private $id;
	private $type;
	private $objectType;
	
	public function __construct() {
		
	}
}

class Scalr_System_OperationProgress
{
	private $progress,
		$operationId,
		$db;
	
	public function __construct($operationId, $progressDefinition) {
		$this->progress = $progressDefinition;
		$this->operationId = $operationId;
		$this->db = Core::GetDBInstance();
		
		$this->load();
	} 
	
	private function load()
	{
		//$steps = $this->db->Execute("SELECT * FROM operation_progress WHERE operation_id = ?", array($this->operationId));
		
	}
	
	private function updatePhaseProgress($phase)
	{
		$stepsCount = count($this->progress[$phase]['steps']);
		$completeSteps = 0;
		foreach ($this->progress[$phase]['steps'] as $step) {
			if ($step['status'] == 'complete')
				$completeSteps++;
		}
		
		if ($completeSteps == $stepsCount) {
			$this->progress[$phase]['status'] = 'complete';
			$this->progress[$phase]['progress'] = 100;
		} else {
			$this->progress[$phase]['progress'] = (int)(100 / $stepsCount * $completeSteps);
			$this->progress[$phase]['status'] = 'in-progress';
		}
	}
	
	public static function init($operationId, $manifest)
	{
		return new self($operationId, json_decode(file_get_contents($manifest), true));
	}
	
	public function update($phase, $step, $progress, $timestamp = null, $strict = true)
	{
		if ($strict) {
			if (!$this->progress[$phase])
				throw new Exception("Unknown phase '%s'", array($phase));
		
			if ($step && !$this->progress[$phase]['steps'][$step])
				throw new Exception("Unknown step '%s' in phase '%s'", array($step, $phase));
		} else {
			if (!$this->progress[$phase]) {
				//TODO:
			}
			
			if ($step && !$this->progress[$phase]['steps'][$step]) {
				//TODO:
			}
		}
		
		if ($step) {
			$this->progress[$phase]['steps'][$step]['progress'] = $progress;
			if ($progress == 100) {
				$this->progress[$phase]['steps'][$step]['status'] = 'complete';
				$this->updatePhaseProgress($phase);
			} else {
				$this->progress[$phase]['steps'][$step]['status'] = 'in-progress';
			}
		} else {
			$this->progress[$phase]['progress'] = $progress;
			if ($progress == 100)
				$this->progress[$phase]['status'] = 'complete';
			else
				$this->progress[$phase]['status'] = 'in-progress';
		}
	}
	
	public function setWarning()
	{
		
	}
	
	public function setError()
	{
		
	}
}

class Scalr_System_Operation
{
	const STATUS_PENDING 		= 'pending';
	const STATUS_IN_PROGRESS 	= 'in-rpogress';
	const STATUS_FAILED 		= 'failed';
	const STATUS_SUCCESS		= 'success';
	
	protected $id;
	
	/**
	 * Volumes, Snapshots, etc. All resources that were created during operation.
	 * @var array
	 */
	protected $resources;
	protected $config;
	
	
	protected $envId,
		$accountId,
		$userId,
		
		$status,
		$dtAdded;
	
	public function __construct($id = null)
	{
		$this->id = $id;
		if (!$this->id)	
			$this->id = Scalr::GenerateUID();
		
		$this->progress = Scalr_System_OperationProgress::init($this->id, dirname(__FILE__).'/Manifests/Ec2ImageMigration.json');
	}
	
	public function loadById($id)
	{
		//TODO:
	}
	
	public function getId()
	{
		return $this->id;
	}
	
	protected function setConfigVariable($name, $value)
	{
		$this->config[$name] = $value;
	}
	
	protected function getConfigVariable($name)
	{
		return $this->config[$name];
	}
	
	protected function getResource($name)
	{
	
	}
	
	protected function addResource($name, $id)
	{
	
	}
	
	public function onSuccess() {}
	
	public function onFailure() {}
	
	protected function onStart() {}
	
	public function start()
	{
		if ($this->status != self::STATUS_PENDING)
			throw new Exception("You can start only pendding operations");
	
		$this->status = self::STATUS_IN_PROGRESS;
		$this->save();
		
		$this->onStart();
	}
}

class Scalr_System_Operation_BuildRole extends Scalr_System_Operation
{
	/*
	 * Steps:
	 * *** Prepare environment
	 * 1. Launch server
	 * 2. Prepare environment (SSH to the instance, upload script)
	 * 3. Install software
	 *  *** Create image
	 * 4. Create image
	 * 5. Create role
	 * 5. Replace servers
	 * 
	 * ** Cleanup
	 */
}

class Scalr_System_Operation_Ec2ImageMigration extends Scalr_System_Operation
{
	const CONFIG_ROLE_ID = 'roleId';
	const CONFIG_SOURCE_LOCATION = 'sourceLocation';
	const CONFIG_DEST_LOCATION = 'destLocation';
	
	const RES_SOURCE_SERVER_ID = 'sourceServerId';
	const RES_DEST_SERVER_ID = 'destServerId';
	const RES_SOURCE_VOLUME_ID = 'sourceVolumeId';
	const RES_DESTINATION_VOLUME_ID = 'destVolumeId';

	
	/*
	 * Steps:
	 * *** Prepare environment:
	 * 1. Launch servers
	 * 2. Wait for source and destination servers
	 * 3. Attach source EBS volume
	 * 4. Attch destination EBS volume
	 * *** Create image
	 * 5. Copy data from source to destination
	 * 6. Create snapshtot of destination volume
	 * 7. Register new AMI
	 * 8. Update Role
	 * 
	 * ** Cleanup
	 * 
	 * 
	 * 
	 * 
	 * launch_servers -> attach_volumes -> copying_data -> creating_snapshot -> registering_ami -> updating_scalr_role
	 */
	
	public function setConfig(array $config)
	{
		if (!$config[self::CONFIG_ROLE_ID])
			throw new Exception("'roleId' config option is required for Ec2ImageMigration operation");
		else
			$this->setConfigVariable(self::CONFIG_ROLE_ID, $config[self::CONFIG_ROLE_ID]);
		
		if (!$config[self::CONFIG_SOURCE_LOCATION])
			throw new Exception("'sourceLocation' config option is required for Ec2ImageMigration operation");
		else
			$this->setConfigVariable(self::CONFIG_SOURCE_LOCATION, $config[self::CONFIG_SOURCE_LOCATION]);
		
		if (!$config[self::CONFIG_DEST_LOCATION])
			throw new Exception("'destLocation' config option is required for Ec2ImageMigration operation");
		else
			$this->setConfigVariable(self::CONFIG_DEST_LOCATION, $config[self::CONFIG_DEST_LOCATION]);
	}
	
	public function __construct($id = null) {
		parent::__construct($id);
		
		//Init phases
		//$p->progress = 
	}
	
	public function updateProgress($phaseId, $stepId, $status, $progress, $message)
	{
		
	}
	
	public function checkOperationProgress()
	{
		switch ($this->subStatus) {
			case "launch_servers":
				
				$this->initServers();
				
				break;
				
			case "attach_volumes":
				
				$this->initVolumes();
				
				break;
		}
	}
	
	protected function onStart()
	{
		parent::onStart();
		
		$this->initServers();	
	}
	
	private function initVolumes()
	{
		
	}
	
	private function initServers()
	{
		$sourceServerId = $this->getResource(self::RES_SOURCE_SERVER_ID);
		if ($sourceServerId) {
			//
			//Check status
			//
		} else {
			
			$dbRole = DBRole::loadById($this->getConfigVariable(self::CONFIG_ROLE_ID));
			$imageId = $dbRole->getImageId(SERVER_PLATFORMS::EC2, $this->getConfigVariable(self::CONFIG_SOURCE_LOCATION));
			
			$creInfo = new ServerCreateInfo(SERVER_PLATFORMS::EC2, null, 0, 0);
			$creInfo->clientId = $this->accountId;
			$creInfo->envId = $this->envId;
			$creInfo->farmId = 0;
			$creInfo->roleId = $dbRole->id;
			$creInfo->SetProperties(array());
			
			$sourceDbServer = DBServer::Create($creInfo, true);
			$sourceDbServer->status = SERVER_STATUS::TEMPORARY;
			$sourceDbServer->save();
			
			//Launch source server
			$sourceLaunchOptions = new Scalr_Server_LaunchOptions();
			$sourceLaunchOptions->imageId = $imageId;
			$sourceLaunchOptions->cloudLocation = $this->getConfigVariable(self::CONFIG_SOURCE_LOCATION);
			$sourceLaunchOptions->architecture = $dbRole->architecture;
			
			$sourceLaunchOptions->serverType = 't1.micro';
			$sourceLaunchOptions->userData = "#cloud-config\ndisable_root: false";
			
			try {
				PlatformFactory::NewPlatform(SERVER_PLATFORMS::EC2)->LaunchServer($sourceDbServer, $sourceLaunchOptions);
				$this->addResource(self::RES_SOURCE_SERVER_ID, $sourceDbServer->serverId);
			}
			catch(Exception $e) {
				$this->onFailure($e->getMessage());
			}
		}
	}
}