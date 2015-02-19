<?php
namespace Scalr\Service\Aws\Rds\Handler;

use Scalr\Service\Aws\Client\ClientException;
use Scalr\Service\Aws\Rds\DataType\DBSubnetGroupData;
use Scalr\Service\Aws\Rds\DataType\DBSubnetGroupList;
use Scalr\Service\Aws\RdsException;
use Scalr\Service\Aws\Rds\AbstractRdsHandler;
use Scalr\Service\Aws\Rds\DataType\CreateDBSubnetGroupRequestData;
use Scalr\Service\Aws\Rds\DataType\ModifyDBSubnetGroupRequestData;

/**
 * Amazon RDS DbSubnetGroupHandler
 *
 * @author    Vlad Dobrovolskiy   <v.dobrovolskiy@scalr.com>
 * @since     12.01.2015
 */
class DbSubnetGroupHandler extends AbstractRdsHandler
{
    /**
     * DescribeDBSubnetGroup action
     *
     * Returns a list of DBSubnetGroup descriptions.
     * If a DBSubnetGroupName is specified, the list will contain only the descriptions of the specified DBSubnetGroup.
     *
     * @param   string     $dBSubnetGroupName   optional Subnet group name
     * @param   string     $marker              optional Pagination token, provided by a previous request.
     * @param   string     $maxRecords          optional The maximum number of records to include in the response.
     * @return  DBSubnetGroupList               Returns the list of the DBSubnetGroupData
     * @throws  ClientException
     * @throws  RdsException
     */
    public function describe($dBSubnetGroupName = null, $marker = null, $maxRecords = null)
    {
        return $this->getRds()->getApiHandler()->describeDBSubnetGroups($dBSubnetGroupName, $marker, $maxRecords);
    }

    /**
     * DeleteDBSubnetGroup action
     * Deletes a DB subnet group.
     *
     * @param   string  $dBSubnetGroupName  Subnet group name
     * @return  bool       Returns true on success or throws an exception.
     * @throws  ClientException
     * @throws  RdsException
     */
    public function delete($dBSubnetGroupName)
    {
        return $this->getRds()->getApiHandler()->deleteDBSubnetGroup($dBSubnetGroupName);
    }

    /**
     * CreateDBSubnetGroup
     *
     * Creates a new DB subnet group. DB subnet groups must contain at least one subnet in at least two AZs in the region.
     *
     * @param   CreateDBSubnetGroupRequestData   $request   Create subnet group request data
     * @return  DBSubnetGroupData
     * @throws  ClientException
     * @throws  RdsException
     */
    public function create(CreateDBSubnetGroupRequestData $request)
    {
        return $this->getRds()->getApiHandler()->createDBSubnetGroup($request);
    }

    /**
     * ModifyDBSubnetGroup
     *
     * Modifies an existing DB subnet group.
     * DB subnet groups must contain at least one subnet in at least two AZs in the region.
     *
     * @param   ModifyDBSubnetGroupRequestData $request Modify subnet group request data
     * @return  DBSubnetGroupData
     * @throws  ClientException
     * @throws  RdsException
     */
    public function modify(ModifyDBSubnetGroupRequestData $request)
    {
        return $this->getRds()->getApiHandler()->modifyDBSubnetGroup($request);
    }

}