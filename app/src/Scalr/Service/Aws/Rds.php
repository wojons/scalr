<?php
namespace Scalr\Service\Aws;

use Scalr\Service\Aws\Rds\DataType\DescribeDBEngineVersionsData;
use Scalr\Service\Aws\Rds\DataType\DBEngineVersionList;

/**
 * Amazon RDS interface
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     07.03.2013
 *
 * @property  \Scalr\Service\Aws\Rds\Handler\DbInstanceHandler $dbInstance
 *            DBInstance service interface handler
 *
 * @property  \Scalr\Service\Aws\Rds\Handler\DbSecurityGroupHandler $dbSecurityGroup
 *            DBSecurityGroup service interface handler
 *
 * @property  \Scalr\Service\Aws\Rds\Handler\DbParameterGroupHandler $dbParameterGroup
 *            DBParameterGroup service interface handler
 *
 * @property  \Scalr\Service\Aws\Rds\Handler\DbSnapshotHandler $dbSnapshot
 *            DBSnapshot service interface handler
 *
 * @property  \Scalr\Service\Aws\Rds\Handler\EventHandler $event
 *            Event service interface handler
 *
 * @property  \Scalr\Service\Aws\Rds\Handler\DbSubnetGroupHandler $dbSubnetGroup
 *            Db subnet group service interface handler
 *
 * @property  \Scalr\Service\Aws\Rds\Handler\OptionGroupHandler $optionGroup
 *            Option group service interface handler
 *
 * @property  \Scalr\Service\Aws\Rds\Handler\TagHandler $tag
 *            Tag service interface handler
 *
 * @method    \Scalr\Service\Aws\Rds\V20141031\RdsApi getApiHandler() getApiHandler()  Gets an RdsApi handler
 */
class Rds extends AbstractService implements ServiceInterface
{

    /**
     * API Version 20130110
     */
    const API_VERSION_20130110 = '20130110';

    /**
     * API Version 20130110
     */
    const API_VERSION_20141031 = '20141031';

    /**
     * Current version of the API
     */
    const API_VERSION_CURRENT = self::API_VERSION_20141031;

    /**
     * Amazon RDS db instance Resource type
     */
    const DB_INSTANCE_RESOURCE_TYPE = 'db';

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getAllowedEntities()
     */
    public function getAllowedEntities()
    {
        return array('dbInstance', 'dbSecurityGroup', 'dbParameterGroup', 'dbSnapshot', 'event', 'dbSubnetGroup', 'optionGroup', 'tag');
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getAvailableApiVersions()
     */
    public function getAvailableApiVersions()
    {
        return array(self::API_VERSION_20130110, self::API_VERSION_20141031);
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getCurrentApiVersion()
     */
    public function getCurrentApiVersion()
    {
        return self::API_VERSION_CURRENT;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.ServiceInterface::getUrl()
     */
    public function getUrl()
    {
        $region = $this->getAws()->getRegion();

        return 'rds.' . $region . '.amazonaws.com';
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\Service\Aws\AbstractService::getName()
     */
    public function getName()
    {
        return 'rds';
    }

    /**
     * Gets Amazon Resource Name (ARN) for rds resources
     *
     * @param string $resourceName  Resource identifier for the Amazon RDS resource.
     * @param string $resourceType  The type of Amazon RDS resource (db|es|og|pg|ri|secgrp|snapshot|subgrp)
     * @return string
     */
    public function getResourceName($resourceName, $resourceType)
    {
        return 'arn:aws:rds:' . $this->getAws()->getRegion() . ':' . $this->getAws()->getAccountNumber() . ':' . $resourceType . ':' . $resourceName;
    }

    /**
     * Returns a list of the available DB engines.
     *
     * @param DescribeDBEngineVersionsData $request
     * @param string                       $marker
     * @param int                          $maxRecords
     * @return DBEngineVersionList
     * @throws RdsException
     */
    public function describeDBEngineVersions(DescribeDBEngineVersionsData $request = null, $marker = null, $maxRecords = null)
    {
        return $this->getApiHandler()->describeDBEngineVersions($request, $marker, $maxRecords);
    }

}
