<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Script revisions entity
 *
 * @author   Igor Vodiasov  <invar@scalr.com>
 * @since    5.0 (08.05.2014)
 *
 * @Entity
 * @Table(name="script_versions")
 */
class ScriptVersion extends AbstractEntity
{
    /**
     * Script's ID
     *
     * @Id
     * @Column(type="integer")
     * @var integer
     */
    public $scriptId;

    /**
     * Version (revision)
     *
     * @Id
     * @Column(type="integer")
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
        $this->dtCreated = new \DateTime();
    }

    public function save()
    {
        $this->variables = [];
        $matches = [];

        $text = preg_replace('/(\\\%)/si', '$$scalr$$', $this->content);
        preg_match_all("/\%([^\%\s]+)\%/si", $text, $matches);

        $builtin = array_keys(\Scalr_Scripting_Manager::getScriptingBuiltinVariables());
        foreach ($matches[1] as $var) {
            if (! in_array($var, $builtin))
                $this->variables[$var] = ucwords(str_replace("_", " ", $var));
        }

        parent::save();
    }
}