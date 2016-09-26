<?php
namespace Scalr\Service\Aws\Rds\DataType;

use Scalr\Service\Aws\DataType\ListDataType;
use Scalr\Service\Aws\Rds\AbstractRdsDataType;

/**
 * ModifyDBSubnetGroupRequestData
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 *
 * @property \Scalr\Service\Aws\DataType\ListDataType $subnetIds
 *           The EC2 Subnet IDs for the DB subnet group.
 */
class ModifyDBSubnetGroupRequestData extends AbstractRdsDataType
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
     * @param   string     $dBSubnetGroupName              The name for the DB subnet group. This value is stored as a lowercase string.
     * @param   string     $dBSubnetGroupDescription       optional The description for the DB subnet group.
     */
    public function __construct($dBSubnetGroupName, $dBSubnetGroupDescription = null)
    {
        parent::__construct();

        if ($dBSubnetGroupDescription !== null) {
            $this->dBSubnetGroupDescription = (string) $dBSubnetGroupDescription;
        }

        $this->dBSubnetGroupName = (string) $dBSubnetGroupName;
    }

    /**
     * Sets SubnetIds list
     *
     * @param   ListDataType|array|string $subnetIds
     *          The EC2 Subnet IDs for the DB subnet group.
     * @return  ModifyDBSubnetGroupRequestData
     */
    public function setSubnetIds($subnetIds)
    {
        if ($subnetIds !== null && !($subnetIds instanceof ListDataType)) {
            $subnetIds = new ListDataType($subnetIds);
        }
        return $this->__call(__FUNCTION__, array($subnetIds));
    }

}