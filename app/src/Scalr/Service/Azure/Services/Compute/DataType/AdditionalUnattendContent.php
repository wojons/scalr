<?php

namespace Scalr\Service\Azure\Services\Compute\DataType;

use Scalr\Service\Azure\DataType\AbstractDataType;

/**
 * AdditionalUnattendContent
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     5.8.6
 *
 */
class AdditionalUnattendContent extends AbstractDataType
{
    /**
     * Specifies the name of the pass that the content applies to.
     * The only allowable value is oobeSystem.
     *
     * @var string
     */
    public $pass;

    /**
     * Specifies the name of the component to configure with the added content.
     * The only allowable value is Microsoft-Windows-Shell-Setup.
     *
     * @var string
     */
    public $component;

    /**
     * Specifies the name of the setting to which the content applies.
     * Possible values are: FirstLogonCommands and AutoLogon
     *
     * @var string
     */
    public $settingName;

    /**
     * Specifies the base-64 encoded XML formatted content that is added to the unattend.xml file for the specified path and component.
     *
     * @var string
     */
    public $content;

    /**
     * Constructor
     *
     * @param   string     $pass            Specifies the name of the pass that the content applies to.
     *                                      The only allowable value is oobeSystem.
     *
     * @param   string     $component       Specifies the name of the component to configure with the added content.
     *                                      The only allowable value is Microsoft-Windows-Shell-Setup.
     *
     * @param   string     $settingName     Specifies the name of the setting to which the content applies.
     *                                      Possible values are: FirstLogonCommands and AutoLogon
     *
     * @param   string     $content         Specifies the base-64 encoded XML formatted content that is added to the unattend.xml file for the specified path and component.
     */
    public function __construct($pass, $component, $settingName, $content)
    {
        $this->pass         = $pass;
        $this->component    = $component;
        $this->settingName  = $settingName;
        $this->content      = $content;
    }

}