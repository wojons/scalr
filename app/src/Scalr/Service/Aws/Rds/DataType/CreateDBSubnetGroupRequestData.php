<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * CreateDBSubnetGroupRequestData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 *
 * @property \Scalr\Service\Aws\DataType\ListDataType $subnetIds
 *           The EC2 Subnet IDs for the DB subnet group.
 */
class CreateDBSubnetGroupRequestData extends AbstractRdsDataType
{

    /**
     * List of the public properties
     * which is managed by magic getter and setters internally.
     *
     * @var  array
     */
    protected $_properties = array('subnetIds');

    /**
     * The description for the DB subnet group.
     *
     * @var string
     */
    public $dBSubnetGroupDescription;

    /**
     * The name for the DB subnet group. This value is stored as a lowercase string.
     *
     * @var string
     */
    public $dBSubnetGroupName;

    /**
     * Constructor
     *
     * @param   string     $dBSubnetGroupDescription       The description for the DB subnet group.
     * @param   string     $dBSubnetGroupName              The name for the DB subnet group. This value is stored as a lowercase string.
     */
    public function __construct($dBSubnetGroupDescription, $dBSubnetGroupName)
    {
        parent::__construct();
        $this->dBSubnetGroupDescription = (string) $dBSubnetGroupDescription;
        $this->dBSubnetGroupName = (string) $dBSubnetGroupName;
    }

    /**
     * Sets SubnetIds list
     *
     * @param   ListDataType|array|string $subnetIds
     *          The EC2 Subnet IDs for the DB subnet group.
     * @return  CreateDBSubnetGroupRequestData
     */
    public function setSubnetIds($subnetIds)
    {
        if ($subnetIds !== null && !($subnetIds instanceof ListDataType)) {
            $subnetIds = new ListDataType($subnetIds);
        }
        return $this->__call(__FUNCTION__, array($subnetIds));
    }

}