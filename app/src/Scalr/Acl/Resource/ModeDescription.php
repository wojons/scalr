<?php

namespace Scalr\Acl\Resource;

/**
 * Resource Mode Description
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    31.08.2015
 */
class ModeDescription
{
    /**
     * Mnemonic name
     *
     * @var string
     */
    public $constName;

    /**
     * Display name for the Mode
     *
     * @var string
     */
    public $name;

    /**
     * Mode description
     *
     * @var string
     */
    public $description;

    /**
     * Constructor
     *
     * @param    string   $constName   Mnemonic name
     * @param    string   $name        Display name for the Mode
     * @param    string   $description Description
     */
    public function __construct($constName, $name, $description)
    {
        $this->constName = $constName;
        $this->name = $name;
        $this->description = $description;
    }
}