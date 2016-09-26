<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * DescribeDBEngineVersionsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 *
 */
class DescribeDBEngineVersionsData extends AbstractRdsDataType
{

    /**
     * The name of a specific DB parameter group family to return details for.
     *
     * @var string
     */
    public $dBParameterGroupFamily;

    /**
     * Indicates that only the default version of the specified engine or engine and major version combination is returned.
     *
     * @var bool
     */
    public $defaultOnly;

    /**
     * The database engine to return.
     *
     * @var string
     */
    public $engine;

    /**
     * The database engine version to return.
     *
     * @var string
     */
    public $engineVersion;

    /**
     * If this parameter is specified, and if the requested engine supports the CharacterSetName parameter for CreateDBInstance,
     * the response includes a list of supported character sets for each engine version.
     *
     * @var bool
     */
    public $listSupportedCharacterSets;

}