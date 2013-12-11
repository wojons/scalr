<?php

namespace Scalr\Upgrade\Entity;

use Scalr\Upgrade\UpgradeHandler;
use Scalr\Exception\UpgradeException;

/**
 * FilesystemUpgradeEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (15.10.2013)
 */
class FilesystemUpgradeEntity extends AbstractUpgradeEntity
{
	/**
     * {@inheritdoc}
     * @see Scalr\Upgrade.AbstractEntity::save()
     */
    public function save()
    {
        $pk = $this->getPrimaryKey();
        $actual = $this->getActual();

        $new = !$actual->$pk;
        if ($new && empty($this->$pk)) {
            throw new UpgradeException('%s has not been provided yet for class %s', $pk, get_class($this));
        }

        //Synchronizes actual state of the entity
        $this->getChanges()->synchronize();

        //Saves in the local directory
        $filename = UpgradeHandler::FS_STORAGE_PATH . $actual->uuid;
        if (file_exists($filename) && !is_writable($filename)) {
            throw new UpgradeException(sprintf('Could not open file "%s" for writing.', $filename));
        }
        file_put_contents($filename, serialize($actual));
        if ($new) {
            @chmod($filename, 0666);
        }
    }

	/**
     * {@inheritdoc}
     * @see Scalr\Upgrade\Entity.AbstractUpgradeEntity::createFailureMessage()
     */
    public function createFailureMessage($log)
    {
        //Feature is not supported for filesystem storage yet.
    }
}
