<?php
namespace Scalr\Service\Aws\Repository;

use Scalr\Service\Aws\Ec2\DataType\SnapshotData;
use Scalr\Service\Aws\AbstractRepository;

/**
 * Ec2SnapshotRepository
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     30.01.2013
 */
class Ec2SnapshotRepository extends AbstractRepository
{

    /**
     * Reflection class name.
     * @var string
     */
    private static $reflectionClassName = 'Scalr\\Service\\Aws\\Ec2\\DataType\\SnapshotData';

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
        return 'snapshotId';
    }

    /**
     * Finds one element in entity manager by unique identifier
     *
     * @param    string             $id  An snapshotId
     * @return   SnapshotData       Returns InstanceData or NULL if nothing found.
     */
    public function find($id)
    {
        return parent::find($id);
    }
}