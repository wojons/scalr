<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * CharacterSetData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 */
class CharacterSetData extends AbstractRdsDataType
{

    /**
     * The description of the character set.
     *
     * @var string
     */
    public $characterSetDescription;

    /**
     * The name of the character set.
     *
     * @var string
     */
    public $characterSetName;

}