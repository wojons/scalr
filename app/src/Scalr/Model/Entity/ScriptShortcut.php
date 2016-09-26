<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Script shortcut entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (08.05.2014)
 *
 * @Entity
 * @Table(name="script_shortcuts")
 */
class ScriptShortcut extends AbstractEntity
{
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
     * @Column(type="integer")
     * @var integer
     */
    public $scriptId;

    /**
     * @Column(type="string")
     * @var string
     */
    public $scriptPath;

    /**
     * @Column(type="integer")
     * @var integer
     */
    public $farmId;

    /**
     * @Column(type="integer",nullable=true)
     * @var integer
     */
    public $farmRoleId;

    /**
     * Version
     *
     * @Column(type="integer")
     * @var integer
     */
    public $version;

    /**
     * Bloking or non-blocking script
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
     * @Column(type="serialize")
     * @var array
     */
    public $params;

    protected $_script = null;

    /**
     * @return Script|NULL
     */
    public function getScript()
    {
        if (! $this->_script) {
            $this->_script = Script::findPk($this->scriptId);
        }

        return $this->_script;
    }

    public function getScriptName()
    {
        return ($this->scriptId) ? $this->getScript()->name : $this->scriptPath;
    }
}
