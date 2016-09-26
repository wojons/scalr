<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * DBEngineVersionData
 *
 * @property \Scalr\Service\Aws\Rds\DataType\CharacterSetData $defaultCharacterSet
 *           The default character set for new instances of this engine version, if the CharacterSetName parameter of the CreateDBInstance API is not specified.
 *
 * @property \Scalr\Service\Aws\Rds\DataType\CharacterSetList $supportedCharacterSets
 *           A list of the character sets supported by this engine for the CharacterSetName parameter of the CreateDBInstance API.
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 */
class DBEngineVersionData extends AbstractRdsDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('defaultCharacterSet', 'supportedCharacterSets');

    /**
     * The description of the database engine.
     *
     * @var string
     */
    public $dBEngineDescription;

    /**
     * The description of the database engine version.
     *
     * @var string
     */
    public $dBEngineVersionDescription;

    /**
     * The name of the DB parameter group family for the database engine.
     *
     * @var string
     */
    public $dBParameterGroupFamily;

    /**
     * The name of the database engine.
     *
     * @var string
     */
    public $engine;

    /**
     * The version number of the database engine.
     *
     * @var string
     */
    public $engineVersion;

}