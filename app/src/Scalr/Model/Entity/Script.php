<?php
namespace Scalr\Model\Entity;

use Exception;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\Exception\ModelException;
use Scalr\Exception\ObjectInUseException;
use Scalr\Model\AbstractEntity;
use Scalr\DataType\ScopeInterface;
use Scalr_Account_User;
use Scalr_Exception_Core;

/**
 * Script entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (08.05.2014)
 *
 * @Entity
 * @Table(name="scripts")
 */
class Script extends AbstractEntity implements ScopeInterface, AccessPermissionsInterface
{
    const TARGET_ALL = 'all';
    const TARGET_FARM = 'farm';
    const TARGET_ROLE = 'role';
    const TARGET_INSTANCE = 'instance';
    const TARGET_ROLES = 'roles';
    const TARGET_FARMROLES = 'farmroles';
    const TARGET_BEHAVIORS = 'behaviors';

    const OS_LINUX = 'linux';
    const OS_WINDOWS = 'windows';

    /**
     * ID
     *
     * @Id
     * @GeneratedValue
     * @Column(type="integer")
     * @var integer
     */
    public $id;

    /**
     * Script's name
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Description
     *
     * @Column(type="string")
     * @var string
     */
    public $description;

    /**
     * Date when script was created
     *
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $dtCreated;

    /**
     * Date when script was modified last time
     *
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $dtChanged;

    /**
     * OS family of script (linux, windows)
     *
     * @Column(type="string")
     * @var string
     */
    public $os = '';

    /**
     * Sync or async script
     *
     * @Column(type="integer")
     * @var integer
     */
    public $isSync;

    /**
     * Timeout
     *
     * @Column(type="integer")
     * @var integer
     */
    public $timeout;

    /**
     * The identifier of the client's account
     *
     * @Column(type="integer",nullable=true)
     *
     * @var integer
     */
    public $accountId;

    /**
     * The identifier of the client's environment
     *
     * @Column(type="integer",nullable=true)
     *
     * @var integer
     */
    public $envId;

    /**
     *
     * @Column(type="integer")
     * @var integer
     */
    public $createdById;

    /**
     *
     * @Column(type="string")
     * @var string
     */
    public $createdByEmail;

    /**
     * Enable/disable script parameters interpolation
     *
     * @Column(type="integer")
     * @var integer
     */
    public $allowScriptParameters = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->dtCreated = new \DateTime();
        $this->dtChanged = new \DateTime();
    }

    /**
     * @var \ArrayObject
     */
    private $_versions;

    /**
     * Gets the list of the revisions associated with the script
     *
     * @param bool $resetCache
     * @return \ArrayObject Returns the list of ScriptVersion objects
     */
    public function getVersions($resetCache = false)
    {
        if ($this->_versions === null || $resetCache) {
            $this->fetchVersions();
        }
        return $this->_versions;
    }

    /**
     * Gets entity of specified version of script
     *
     * @param int $version
     *
     * @return ScriptVersion|null
     */
    public function getVersion($version)
    {
        return ScriptVersion::findOne([
            ['scriptId' => $this->id],
            ['version' => $version],
        ]);
    }

    /**
     * Fetches list of the versions associated with the script (refreshes)
     *
     * @return \ArrayObject Returns the list of ScriptVersion objects
     */
    public function fetchVersions()
    {
        $this->_versions = ScriptVersion::result(ScriptVersion::RESULT_ENTITY_COLLECTION)->find(
            [['scriptId' => $this->id]], null, ['version' => true]
        );

        return $this->_versions;
    }

    /**
     * Get latest version of script
     *
     * @return ScriptVersion
     *
     * @throws \Exception
     */
    public function getLatestVersion()
    {
        /* @var $version ScriptVersion */
        $version = ScriptVersion::findOne([['scriptId' => $this->id]], null, ['version' => false]);

        if (!$version) {
            throw new Exception(sprintf('No version found for script %d', $this->id));
        }

        return $version;
    }

    /**
     * Saves changes, if script already exists, or saves new script
     *
     * @throws ModelException
     */
    public function save()
    {
        $id = $this->id;
        $this->dtChanged = new \DateTime();

        parent::save();

        if (!$id) {
            // we should create first version
            $version = new ScriptVersion();
            $version->scriptId = $this->id;
            $version->version = 1;
            $version->content = '';
            $version->changedById = $this->createdById;
            $version->changedByEmail = $this->createdByEmail;
            $version->save();
        }
    }

    /**
     * Deletes this script
     *
     * @throws Scalr_Exception_Core
     * @throws Exception
     * @throws ModelException
     */
    public function delete()
    {
        // Check script usage
        $usage = [];

        $farmRolesCount = $this->db()->GetOne("SELECT COUNT(DISTINCT farm_roleid) FROM farm_role_scripts WHERE scriptid=?",
            array($this->id)
        );
        if ($farmRolesCount > 0) {
            $message = [];
            foreach ($this->db()->GetCol("SELECT DISTINCT farm_roleid FROM farm_role_scripts WHERE scriptid = ? LIMIT 3", array($this->id)) as $id) {
                $dbFarmRole = \DBFarmRole::LoadByID($id);
                $message[] = $dbFarmRole->GetFarmObject()->Name . ' (' . $dbFarmRole->Alias . ')';
            }

            $usage[] = sprintf("%d farm roles: %s%s", $farmRolesCount, join(', ', $message), $farmRolesCount > 3 ? ' and others' : '');
        }

        $rolesCount = $this->db()->GetOne("SELECT COUNT(DISTINCT role_id) FROM role_scripts WHERE script_id=?",
            array($this->id)
        );
        if ($rolesCount > 0) {
            $message = [];
            foreach ($this->db()->GetCol("SELECT DISTINCT role_id FROM role_scripts WHERE script_id = ? LIMIT 3", array($this->id)) as $id) {
                $dbRole = \DBRole::LoadByID($id);
                $message[] = $dbRole->name;
            }

            $usage[] = sprintf("%d roles: %s%s", $rolesCount, join(', ', $message), $rolesCount > 3 ? ' and others' : '');
        }

        $accountCount = $this->db()->GetOne("SELECT COUNT(*) FROM account_scripts WHERE script_id=?",
            array($this->id)
        );
        if ($accountCount > 0) {
            $usage[] = sprintf("%d orchestration rule(s) on account level", $accountCount);
        }

        $taskCount = $this->db()->GetOne("SELECT COUNT(*) FROM scheduler WHERE script_id = ?",
            array($this->id)
        );
        if ($taskCount > 0) {
            $usage[] = sprintf("%d scheduler task(s)", $taskCount);
        }

        if (count($usage)) {
            throw new ObjectInUseException(sprintf('Script "%s" being used by %s, and can\'t be deleted',
                $this->name,
                join(', ', $usage)
            ));
        }

        Tag::deleteTags(Tag::RESOURCE_SCRIPT, $this->id);

        parent::delete();
    }

    /**
     * @param \Scalr_Account_User $user
     * @param int $envId
     * @throws \Scalr_Exception_InsufficientPermissions
     */
    public function checkPermission(\Scalr_Account_User $user, $envId)
    {
        if ($this->accountId && $this->accountId != $user->getAccountId()) {
            throw new \Scalr_Exception_InsufficientPermissions();
        }

        if ($this->envId && $this->envId != $envId) {
            throw new \Scalr_Exception_InsufficientPermissions();
        }
    }

    /**
     * Forks specified script into new script
     *
     * @param string             $name  New script name
     * @param Scalr_Account_User $user  User performs a fork
     * @param int                $envId Environment of the new script
     *
     * @return Script Forked script
     * @throws \Exception
     */
    public function fork($name, Scalr_Account_User $user, $envId = null)
    {
        $script = new static();
        $script->name = $name;
        $script->description = $this->description;
        $script->os = $this->os;
        $script->isSync = $this->isSync;
        $script->allowScriptParameters = $this->allowScriptParameters;
        $script->timeout = $this->timeout;
        $script->accountId = $user->getAccountId() ?: NULL;
        $script->envId = $envId ? $envId : $this->envId;
        $script->createdById = $user->getId();
        $script->createdByEmail = $user->getEmail();
        $script->save();

        $version = new ScriptVersion();
        $version->scriptId = $script->id;
        $version->changedById = $user->getId();
        $version->changedByEmail = $user->getEmail();
        $version->content = $this->getLatestVersion()->content;
        $version->version = 1;
        $version->save();

        return $script;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\ScopeInterface::getScope()
     */
    public function getScope()
    {
        return !empty($this->envId) ? static::SCOPE_ENVIRONMENT : (!empty($this->accountId) ? static::SCOPE_ACCOUNT : static::SCOPE_SCALR);
    }

    public static function getList($accountId, $envId)
    {
        $criteria = [];
        if ($accountId && $envId) {//environment scope
            $criteria[] = ['$or' => [
                ['$and' => [['accountId' => $accountId], ['envId' => $envId]]],
                ['$and' => [['accountId' => $accountId], ['envId' => null]]],
                ['$and' => [['accountId' => null], ['envId' => null]]]
            ]];
        } elseif ($accountId && !$envId) {//account scope
            $criteria[] = ['$or' => [
                ['$and' => [['accountId' => $accountId], ['envId' => null]]],
                ['$and' => [['accountId' => null], ['envId' => null]]]
            ]];
        } else {//scalr scope
            $criteria[] = ['envId' => null];
            $criteria[] = ['accountId' => null];
        }

        return array_map(function(Script $script) {
            return [
                'id' => $script->id,
                'name' => $script->name,
                'description' => $script->description,
                'os' => $script->os,
                'isSync' => $script->isSync,
                'allowScriptParameters' => $script->allowScriptParameters,
                'timeout' => $script->timeout ? $script->timeout : (
                    $script->isSync == 1 ? \Scalr::config('scalr.script.timeout.sync') : \Scalr::config('scalr.script.timeout.async')
                ),
                // TODO: at first check createdById
                'createdByEmail' => $script->createdByEmail,
                'accountId' => $script->accountId,
                'scope' => $script->envId ? self::SCOPE_ENVIRONMENT : ($script->accountId ? self::SCOPE_ACCOUNT : self::SCOPE_SCALR),
                'versions' => array_map(function(ScriptVersion $version) use($script) {
                    return [
                        'version' => $version->version,
                        'versionName' => $version->version,
                        'variables' => $script->allowScriptParameters ? $version->variables : []
                    ];
                }, $script->getVersions()->getArrayCopy())
            ];
        }, self::find($criteria, null, ['name' => true])->getArrayCopy());
    }

    public static function getScriptingData($accountId, $envId)
    {
        return [
            'events' => array_merge(\EVENT_TYPE::getScriptingEventsWithScope(), EventDefinition::getList($accountId, $envId)),
            'scripts' => static::getList($accountId, $envId)
        ];
    }

    public static function fetchVariables($content)
    {
        $text = preg_replace('/(\\\%)/si', '$$scalr$$', $content);
        preg_match_all("/\%([^\%\s]+)\%/si", $text, $matches);
        return $matches[1];
    }

    public static function hasVariables($content)
    {
        $variables = self::fetchVariables($content);
        return !empty($variables);
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
                return !$modify;

            default:
                return false;
        }
    }
}