<?php
namespace Scalr\Model\Entity;

use DateTime;
use Scalr\DataType\AccessPermissionsInterface;
use Scalr\DataType\ScopeInterface;
use Scalr\Model\AbstractEntity;
use Scalr\Model\Entity\Script;

/**
 * Script revisions entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (08.05.2014)
 *
 * @Entity
 * @Table(name="script_versions")
 */
class ScriptVersion extends AbstractEntity implements ScopeInterface, AccessPermissionsInterface
{
    /**
     * Script's ID
     *
     * @Id
     * @Column(type="integer")
     *
     * @var integer
     */
    public $scriptId;

    /**
     * Version (revision)
     *
     * @Id
     * @Column(type="integer")
     *
     * @var integer
     */
    public $version;

    /**
     * Date when script was created
     *
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $dtCreated;

    /**
     * Content
     *
     * @Column(type="string")
     * @var string
     */
    public $content;

    /**
     * Script variables
     *
     * @Column(type="serialize")
     * @var array
     */
    public $variables;

    /**
     *
     * @Column(type="integer")
     *
     * @var integer
     */
    public $changedById;

    /**
     *
     * @Column(type="string")
     * @var string
     */
    public $changedByEmail;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->dtCreated = new DateTime();
    }

    public function save()
    {
        $this->variables = [];
        $variables = Script::fetchVariables($this->content);

        if (!empty($variables)) {
            $builtin = array_keys(\Scalr_Scripting_Manager::getScriptingBuiltinVariables());
            foreach ($variables as $var) {
                if (! in_array($var, $builtin))
                    $this->variables[$var] = ucwords(str_replace("_", " ", $var));
            }
        }

        parent::save();
    }

    /**
     * Gets scope
     *
     * @return   string  Returns scope
     */
    public function getScope()
    {
        /* @var $script Script */
        $script = Script::findPk($this->scriptId);

        return $script->getScope();
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\AccessPermissionsInterface::hasAccessPermissions()
     */
    public function hasAccessPermissions($user, $environment = null, $modify = null)
    {
        /* @var $script Script */
        $script = Script::findPk($this->scriptId);

        switch ($script->getScope()) {
            case static::SCOPE_ACCOUNT:
                return $script->accountId == $user->accountId && (empty($environment) || !$modify);

            case static::SCOPE_ENVIRONMENT:
                return $environment
                    ? $script->envId == $environment->id
                    : $user->hasAccessToEnvironment($script->envId);

            case static::SCOPE_SCALR:
                return !$modify;

            default:
                return false;
        }
    }
}