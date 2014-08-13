<?php
namespace Scalr\Service\CloudStack\Services\Template\DataType;

use Scalr\Service\CloudStack\DataType\AbstractDataType;

/**
 * UpdateTemplatePermissionsData
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    4.5.2
 */
class UpdateTemplatePermissionsData extends AbstractDataType
{

    /**
     * Required
     * The template ID
     *
     * @var string
     */
    public $id;

    /**
     * A comma delimited list of accounts. If specified, "op" parameter has to be passed in.
     *
     * @var string
     */
    public $accounts;

    /**
     * True if the template/iso is extractable, false other wise. Can be set only by root admin
     *
     * @var string
     */
    public $isextractable;

    /**
     * True for featured template/iso, false otherwise
     *
     * @var string
     */
    public $isfeatured;

    /**
     * True for public template/iso, false for private templates/isos
     *
     * @var string
     */
    public $ispublic;

    /**
     * Permission operator (add, remove, reset)
     *
     * @var string
     */
    public $op;

    /**
     * A comma delimited list of projects. If specified, "op" parameter has to be passed in.
     *
     * @var string
     */
    public $projectids;

    /**
     * Constructor
     *
     * @param   string  $id     The template ID
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

}
