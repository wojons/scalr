<?php

namespace Scalr\Service\Aws\Repository;

use Scalr\Service\Aws\AbstractRepository;
use Scalr\Service\Aws\Rds\DataType\DBClusterData;

/**
 * RdsDBClusterRepository
 *
 * @author N.V.
 */
class RdsDBClusterRepository extends AbstractRepository
{

    /**
     * Reflection class name.
     * @var string
     */
    private static $reflectionClassName = 'Scalr\\Service\\Aws\\Rds\\DataType\\DBClusterData';

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractRepository::getReflectionClassName()
     */
    public function getReflectionClassName()
    {
        return self::$reflectionClassName;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Service\Aws.AbstractRepository::getIdentifier()
     */
    public function getIdentifier()
    {
        return 'dBClusterIdentifier';
    }

    /**
     * Finds one element in entity manager by the identifier
     *
     * @param    string               $id An dBInstanceIdentifier
     * @return   DBClusterData       Returns ClusterData or NULL if nothing found.
     */
    public function find($id)
    {
        return parent::find($id);
    }
}