<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\RdsListDataType;

/**
 * TagsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 */
class TagsData extends RdsListDataType
{
    /**
     * The tag key.
     *
     * @var string
     */
    public $key;

    /**
     * The tag value.
     *
     * @var string
     */
    public $value;

    /**
     * Constructor
     *
     * @param   string     $key   optional The key of the tag
     * @param   string     $value optional The value of the tag
     */
    public function __construct($key = null, $value = null)
    {
        parent::__construct();
        $this->key = $key;
        $this->value = $value;
    }

}