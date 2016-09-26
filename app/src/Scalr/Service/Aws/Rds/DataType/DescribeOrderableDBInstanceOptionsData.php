<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * DescribeOrderableDBInstanceOptionsData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     24.01.2015
 */
class DescribeOrderableDBInstanceOptionsData extends AbstractRdsDataType
{

    /**
     * The DB instance class filter value.
     * Specify this parameter to show only the available offerings matching the specified DB instance class.
     *
     * @var string
     */
    public $dBInstanceClass;

    /**
     * The name of the engine to retrieve DB instance options for.
     *
     * @var string
     */
    public $engine;

    /**
     * Indicates the database engine version.
     *
     * MySQL Example: 5.1.42
     * Oracle Example: 11.2.0.2.v2
     * SQL Server Example: 10.50.2789.0.v1
     *
     * @var string
     */
    public $engineVersion;

    /**
     * License model information for this DB Instance
     *
     * Valid values: license-included | bring-your-own-license | general-public-license
     *
     * @var string
     */
    public $licenseModel;

    /**
     * The VPC filter value.
     * Specify this parameter to show only the available VPC or non-VPC offerings.
     *
     * @var bool
     */
    public $vpc;

    /**
     * Constructor
     *
     * @param   string  $engine The name of the engine to retrieve DB instance options for.
     */
    public function __construct($engine)
    {
        parent::__construct();
        $this->engine = (string) $engine;
    }

}