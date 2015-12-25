<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;
use Scalr\DataType\ScopeInterface;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Exception\Model\Entity\Os\OsMismatchException;
use Scalr\Exception\Model\Entity\Image\ImageNotFoundException;
use Scalr\Exception\Model\Entity\Image\NotAcceptableImageStatusException;
use Scalr\Exception\Model\Entity\Image\ImageInUseException;
use Scalr\Util\CryptoTool;

/**
 * Role entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (23.07.2014)
 *
 * @Entity
 * @Table(name="roles")
 */
class Role extends AbstractEntity implements ScopeInterface, AccessPermissionsInterface
{
    /**
     * Scalr scope Role
     * @deprecated
     */
    const ORIGIN_SHARED = 'SHARED';

    /**
     * Either environment or account scope Role
     * @deprecated
     */
    const ORIGIN_CUSTOM = 'CUSTOM';

    /**
     * The identifier of the Role
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var integer
     */
    public $id;

    /**
     * The name of the role
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * @deprecated
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $origin = self::ORIGIN_SHARED;

    /**
     * The description
     *
     * @Column(type="string")
     * @var string
     */
    public $description;

    /**
     * The identifier of the client's account
     *
     * It is set when the Role is from account scope
     *
     * @Column(type="integer",name="client_id",nullable=true)
     * @var integer
     */
    public $accountId;

    /**
     * The identifier of the environment
     *
     * It is set when the Role is from environment scope
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $envId;

    /**
     * Identifier of the category
     *
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $catId;

    /**
     * The list of the supported behaviors
     *
     * @Column(type="string",nullable=true)
     * @var string
     */
    public $behaviors;

    /**
     * Whether the role is deprecated
     *
     * @Column(type="boolean")
     * @var bool
     */
    public $isDeprecated = false;

    /**
     * Whether it is development Role
     *
     * @Column(name="is_devel",type="boolean")
     * @var bool
     */
    public $isDevelopment = false;

    /**
     * Whether it is QuickStart Role
     *
     * @Column(name="is_quick_start",type="boolean")
     * @var bool
     */
    public $isQuickStart = false;

    /**
     * The generation
     *
     * @deprecated
     * @Column(type="integer")
     * @var int
     */
    public $generation = 2;

    /**
     * The timestamp when the Role was added
     *
     * @Column(name="dtadded",type="datetime",nullable=true)
     * @var \DateTime
     */
    public $added;

    /**
     * The timestamp when the Role was used last time
     *
     * @Column(name="dt_last_used",type="datetime",nullable=true)
     * @var \DateTime
     */
    public $lastUsed;

    /**
     * The identifier of the User who added the Role
     *
     * @Column(name="added_by_userid",type="integer",nullable=true)
     * @var int
     */
    public $addedByUserId;

    /**
     * The email address of the User who added the Role
     *
     * @Column(name="added_by_email",type="string",nullable=true)
     * @var int
     */
    public $addedByEmail;

    /**
     * The identifier of the OS
     *
     * @Column(type="string")
     * @var string
     */
    public $osId;

    /**
     * @var Os
     */
    private $_os;

    /**
     * Role behaviors list
     *
     * @var string[]
     */
    private $_behaviors;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->added = new \DateTime();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\ScopeInterface::getScope()
     */
    public function getScope()
    {
        return !empty($this->envId) ? self::SCOPE_ENVIRONMENT : (!empty($this->accountId) ? self::SCOPE_ACCOUNT : self::SCOPE_SCALR);
    }

    /**
     * Validates the name
     *
     * @param    string $name  The name of the Role
     * @return   bool   Returns TRUE when the name is valid or FALSE otherwise
     */
    public static function isValidName($name)
    {
        return !!preg_match('/^[A-Za-z0-9]+[A-Za-z0-9-]*[A-Za-z0-9]+$/i', $name);
    }

    /**
     * Check if given name is used on scalr, account or environment scopes
     *
     * @param   string  $name       Role's name to check
     * @param   int     $accountId  Identifier of account
     * @param   int     $envId      Identifier of environment
     * @return  bool    Returns TRUE if a such name has been already used on scalr or account (or environment) scopes
     */
    public static function isNameUsed($name, $accountId, $envId)
    {
        $criteria = [['accountId' => null]];
        if ($accountId) {
            if ($envId) {
                $criteria[] = ['$and' => [['accountId' => $accountId], ['envId' => null]]];
                $criteria[] = ['envId' => $envId];
            } else {
                $criteria[] = ['accountId' => $accountId];
            }
        }

        return !!Role::findOne([['name' => $name], ['$or' => $criteria]]);
    }

    /**
     * Gets the Os entity which corresponds to the Role
     *
     * @return  Os          Returns the Os entity which corresponds to the Role.
     *                      If OS has not been defined it will return NULL.
     * @throws  \Exception
     */
    public function getOs()
    {
        if (!$this->_os) {
            $this->_os = Os::findPk($this->osId);
        }

        return $this->_os;
    }

    /**
     * Finds out the Image that corresponds to the Role by the specified criteria
     *
     * @param    $platform        string The cloud platform
     * @param    $cloudLocation   string The cloud location
     * @return   RoleImage        Returns the RoleImage object.
     *                            It throws an exception when the RoleImage does not exist
     * @throws   \Exception
     */
    public function getImage($platform, $cloudLocation)
    {
        if (in_array($platform, [\SERVER_PLATFORMS::GCE, \SERVER_PLATFORMS::AZURE])) {
            $cloudLocation = '';
        }

        $image = RoleImage::findOne([
            ['roleId'        => $this->id],
            ['platform'      => $platform],
            ['cloudLocation' => $cloudLocation]
        ]);

        if (!$image) {
            throw new ImageNotFoundException(sprintf(
                "No valid role image found for roleId: %d, platform: %s, cloudLocation: %s",
                $this->id, $platform, $cloudLocation
            ));
        }

        return $image;
    }

    /**
     * Gets the list of the Images which correspond to the Role
     *
     * @return   array   Return array of all role's images in format: [platform][cloudLocation] = [id, architecture, type]
     * @throws   \Exception
     */
    public function fetchImagesArray()
    {
        $result = [];
        $images = $this->getImages();

        /* @var $image \Scalr\Model\Entity\Image */
        foreach ($images as $image) {
            $result[$image->platform][$image->cloudLocation] = [
                'id'    => $image->id,
                'architecture'  => $image->architecture,
                'type'  => $image->type
            ];
        }

        return $result;
    }

    /**
     * Gets Images which are associated with the Role
     *
     * @param    array        $criteria     optional The search criteria on the Image result set.
     * @param    array        $group        optional The group parameter
     * @param    array        $order        optional The results order looks like [[property1 => true|false], ...]
     * @param    int          $limit        optional The records limit
     * @param    int          $offset       optional The offset
     * @param    bool         $countRecords optional True to calculate total number of the records without limit
     * @return   \Scalr\Model\Collections\EntityIterator Returns Images which are associated with the role
     */
    public function getImages(array $criteria = null, array $group = null, array $order = null, $limit = null, $offset = null, $countRecords = null)
    {
        $image = new Image();
        $roleImage = new RoleImage();

        $criteria = $criteria ?: [];

        $criteria[static::STMT_FROM] = $image->table() . "
            JOIN " . $roleImage->table() . " ON {$roleImage->columnImageId} = {$image->columnId}
                AND {$roleImage->columnPlatform} = {$image->columnPlatform}
                AND {$roleImage->columnCloudLocation} = {$image->columnCloudLocation}";

        $criteria[static::STMT_WHERE] = "{$roleImage->columnRoleId} = " . intval($this->id);

        if ($this->envId) {
            $criteria[] = ['$or' => [['envId' => $this->envId], ['envId' => null]]];
        } else {
            $criteria[] = ['envId' => null];
        }

        return $image->find($criteria, $group, $order, $limit, $offset, $countRecords);
    }

    /**
     * Add, replace or remove image in role
     *
     * @param   string  $platform      The cloud platform
     * @param   string  $cloudLocation The cloud location
     * @param   string  $imageId       optional Either Identifier of the Image to add or NULL to remove
     * @param   integer $userId        The identifier of the User who adds the Image
     * @param   string  $userEmail     The email address of the User who adds the Image
     *
     * @throws ImageInUseException
     * @throws ImageNotFoundException
     * @throws NotAcceptableImageStatusException
     * @throws OsMismatchException
     * @throws \Scalr\Exception\ModelException
     */
    public function setImage($platform, $cloudLocation, $imageId, $userId, $userEmail)
    {
        if (in_array($platform, [\SERVER_PLATFORMS::GCE, \SERVER_PLATFORMS::AZURE]))
            $cloudLocation = '';

        $history = new ImageHistory();
        $history->roleId = $this->id;
        $history->platform = $platform;
        $history->cloudLocation = $cloudLocation;
        $history->addedById = $userId;
        $history->addedByEmail = $userEmail;

        $oldImage = null;
        try {
            $oldImage = $this->getImage($platform, $cloudLocation);
            $history->oldImageId = $oldImage->imageId;

            if ($imageId) {
                if ($oldImage->imageId == $imageId) {
                    return;
                }
            }
        } catch (\Exception $e) {}

        if ($imageId) {
            /* @var $newImage Image */
            $newImage = Image::findOne([
                ['id'            => $imageId],
                ['platform'      => $platform],
                ['cloudLocation' => $cloudLocation],
                ['$or' => [
                    ['accountId' => null],
                    ['$and' => [
                        ['accountId' => $this->accountId],
                        ['$or' => [
                            ['envId' => null],
                            ['envId' => $this->envId]
                        ]]
                    ]]
                ]]
            ]);

            if (!$newImage) {
                throw new ImageNotFoundException(sprintf(
                    "The Image does not exist, or isn't owned by your account: %s, %s, %s",
                    $platform, $cloudLocation, $imageId
                ));
            }

            if ($newImage->status !== Image::STATUS_ACTIVE) {
                throw new NotAcceptableImageStatusException(sprintf(
                    "You can't add image %s because of its status: %s", $newImage->id, $newImage->status
                ));
            }

            if ($newImage->getOs()->family && $newImage->getOs()->generation) {
                // check only if they are set
                if ($this->getOs()->family != $newImage->getOs()->family || $this->getOs()->generation != $newImage->getOs()->generation) {
                    throw new OsMismatchException(sprintf("OS mismatch between Image: %s, family: %s and Role: %d, family: %s",
                        $newImage->id, $newImage->getOs()->family, $this->id, $this->getOs()->family
                    ));
                }
            }

            $history->imageId = $newImage->id;

            if ($oldImage) {
                $oldImage->delete();
            }

            $newRoleImage = new RoleImage();
            $newRoleImage->roleId = $this->id;
            $newRoleImage->imageId = $newImage->id;
            $newRoleImage->platform = $newImage->platform;
            $newRoleImage->cloudLocation = $newImage->cloudLocation;

            $newRoleImage->save();
        } else if ($oldImage) {
            if ($oldImage->isUsed()) {
                throw new ImageInUseException(sprintf(
                    "The Image for roleId: %d, platform: %s, cloudLocation: %s is used by some FarmRole",
                    $oldImage->roleId, $oldImage->platform, $oldImage->cloudLocation
                ));
            }

            $oldImage->delete();
        }

        $history->save();
    }

    /**
     * Gets role behaviors list
     *
     * @return string[]
     */
    public function getBehaviors()
    {
        if (empty($this->_behaviors)) {
            $this->_behaviors = array_unique(explode(",", $this->behaviors));
            sort($this->_behaviors);
        }

        return $this->_behaviors;
    }

    /**
     * Sets role behaviors
     *
     * @param   array|\Scalr\UI\Request\JsonData   $behaviors  Array of behaviors
     */
    public function setBehaviors($behaviors)
    {
        $this->_behaviors = array_unique($behaviors);
        sort($this->_behaviors);
        $this->behaviors = implode(',', $this->_behaviors);
    }

    /**
     * Check if role has behavior
     *
     * @param   string  $behavior   Behavior name
     *
     * @return  bool    Returns true if role has behavior, false otherwise
     */
    public function hasBehavior($behavior)
    {
        return in_array($behavior, $this->getBehaviors());
    }

    /**
     * Checks whether the Role is already used in some Farm
     *
     * @return boolean Returns TRUE if the Role is already used or FALSE otherwise
     */
    public function isUsed()
    {
        return !!$this->db()->GetOne("
            SELECT EXISTS(SELECT 1 FROM farm_roles WHERE role_id = ?)
        ", [$this->id]);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        switch ($this->getScope()) {
            case static::SCOPE_ACCOUNT:
                return $this->accountId == $user->accountId && (empty($environment) || !$modify);

            case static::SCOPE_ENVIRONMENT:
                return $environment
                     ? $this->envId == $environment->id
                     : $user->hasAccessToEnvironment($this->envId);

            case static::SCOPE_SCALR:
                return !$modify || $user->isScalrAdmin();

            default:
                return false;
        }
    }

    /**
     * Get scripts of the Role
     * TODO refactor this method to new Entities
     *
     * @return  array
     */
    public function getScripts()
    {
        $dbParams = $this->db()->Execute("SELECT role_scripts.*, scripts.name AS script_name FROM role_scripts LEFT JOIN scripts ON role_scripts.script_id = scripts.id WHERE role_id = ?", array($this->id));
        $retval = array();
        while ($script = $dbParams->FetchRow()) {
            $retval[] = array(
                'role_script_id' => (int) $script['id'],
                'event_name' => $script['event_name'],
                'target' => $script['target'],
                'script_id' => (int) $script['script_id'],
                'script_name' => $script['script_name'],
                'version' => (int) $script['version'],
                'timeout' => $script['timeout'],
                'isSync' => (int) $script['issync'],
                'params' => unserialize($script['params']),
                'order_index' => $script['order_index'],
                'hash' => $script['hash'],
                'script_path' => $script['script_path'],
                'run_as' => $script['run_as'],
                'script_type' => $script['script_type'],
                'os' => $script['os']
            );
        }

        return $retval;
    }

    /**
     * Set scripts of the Role
     * TODO refactor this method to new Entities
     *
     * @param   array   $scripts
     */
    public function setScripts($scripts)
    {
        if (! $this->id)
            return;

        if (! is_array($scripts))
            return;

        $ids = array();
        foreach ($scripts as $script) {
            // TODO: check permission for script_id
            if (!$script['role_script_id']) {
                $this->db()->Execute('INSERT INTO role_scripts SET
                    `role_id` = ?,
                    `event_name` = ?,
                    `target` = ?,
                    `script_id` = ?,
                    `version` = ?,
                    `timeout` = ?,
                    `issync` = ?,
                    `params` = ?,
                    `order_index` = ?,
                    `hash` = ?,
                    `script_path` = ?,
                    `run_as` = ?,
                    `script_type` = ?
                ', array(
                    $this->id,
                    $script['event_name'],
                    $script['target'],
                    $script['script_id'] != 0 ? $script['script_id'] : NULL,
                    $script['version'],
                    $script['timeout'],
                    $script['isSync'],
                    serialize($script['params']),
                    $script['order_index'],
                    (!$script['hash']) ? CryptoTool::sault(12) : $script['hash'],
                    $script['script_path'],
                    $script['run_as'],
                    $script['script_type']
                ));
                $ids[] = $this->db()->Insert_ID();
            } else {
                $this->db()->Execute('UPDATE role_scripts SET
                    `event_name` = ?,
                    `target` = ?,
                    `script_id` = ?,
                    `version` = ?,
                    `timeout` = ?,
                    `issync` = ?,
                    `params` = ?,
                    `order_index` = ?,
                    `script_path` = ?,
                    `run_as` = ?,
                    `script_type` = ?
                    WHERE id = ? AND role_id = ?
                ', array(
                    $script['event_name'],
                    $script['target'],
                    $script['script_id'] != 0 ? $script['script_id'] : NULL,
                    $script['version'],
                    $script['timeout'],
                    $script['isSync'],
                    serialize($script['params']),
                    $script['order_index'],
                    $script['script_path'],
                    $script['run_as'],
                    $script['script_type'],

                    $script['role_script_id'],
                    $this->id
                ));
                $ids[] = $script['role_script_id'];
            }
        }

        $toRemove = $this->db()->Execute('SELECT id, hash FROM role_scripts WHERE role_id = ? AND id NOT IN (\'' . implode("','", $ids) . '\')', array($this->id));
        while ($rScript = $toRemove->FetchRow()) {
            $this->db()->Execute("DELETE FROM farm_role_scripting_params WHERE hash = ? AND farm_role_id IN (SELECT id FROM farm_roles WHERE role_id = ?)",
                array($rScript['hash'], $this->id)
            );
            $this->db()->Execute("DELETE FROM role_scripts WHERE id = ?", array($rScript['id']));
        }
    }

    /**
     * Gets the number of Farms which are using this Role
     *
     * @param   int   $accountId    optional Identifier of account
     * @param   int   $envId        optional Identifier of environment
     * @return  int   Returns farm's count which uses current role
     */
    public function getFarmsCount($accountId = null, $envId = null)
    {
        $sql = "SELECT COUNT(DISTINCT f.id)
                FROM farm_roles fr
                JOIN farms f ON fr.farmid = f.id
                WHERE fr.role_id = ?";
        $args = [$this->id];

        if ($accountId) {
            $sql .= " AND f.clientid = ?";
            $args[] = $accountId;
        }

        if ($envId) {
            $sql .= " AND f.env_id = ?";
            $args[] = $envId;
        }

        return $this->db()->GetOne($sql, $args);
    }

    /**
     * Get the number of Servers which are using this Role
     *
     * @param   string  $accountId  optional    Identifier of account
     * @param   string  $envId      optional    Identifier of environment
     * @return  int
     */
    public function getServersCount($accountId = null, $envId = null)
    {
        $sql = "SELECT COUNT(*)
                FROM servers s
                JOIN farm_roles ON s.farm_roleid = farm_roles.id
                WHERE farm_roles.role_id = ?";
        $args = [$this->id];

        if ($envId) {
            $sql .= " AND s.env_id = ?";
            $args[] = $envId;
        }

        if ($accountId) {
            $sql .= " AND s.client_id = ?";
            $args[] = $accountId;
        }

        return $this->db()->GetOne($sql, $args);
    }

    /**
     * Return array of environments where this role is allowed explicitly.
     * Empty array means everywhere.
     *
     * @return  array   Array of envId
     */
    public function getAllowedEnvironments()
    {
        $r = new RoleEnvironment();
        return $this->db()->GetCol("SELECT {$r->columnEnvId} FROM {$r->table()} WHERE $r->columnRoleId = ?", [$this->id]);
    }

    public function save()
    {
        $this->db()->BeginTrans();
        try {
            parent::save();

            $this->db()->Execute("DELETE FROM `role_behaviors` WHERE `role_id` = ?", [$this->id]);
            $sql = $args = [];
            foreach ($this->getBehaviors() as $behavior) {
                $sql[] = '(?, ?)';
                $args = array_merge($args, [$this->id, $behavior]);
            }

            if (count($sql)) {
                $this->db()->Execute("INSERT INTO `role_behaviors` (`role_id`, `behavior`) VALUES " . join(', ', $sql), $args);
            }

            $this->db()->CommitTrans();
        } catch (\Exception $e) {
            $this->db()->RollbackTrans();
            throw $e;
        }
    }
}
