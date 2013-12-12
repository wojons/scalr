<?php

namespace Scalr\Upgrade\Entity;

use Scalr\Exception\UpgradeException;
use Scalr\Upgrade\UpgradeHandler;
use \ReflectionClass;
use \ReflectionProperty;
use \Scalr;
use \DateTime;
use \DateTimeZone;

/**
 * MysqlUpgradeEntity
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (15.10.2013)
 */
class MysqlUpgradeEntity extends AbstractUpgradeEntity
{
	/**
     * {@inheritdoc}
     * @see Scalr\Upgrade.AbstractEntity::save()
     */
    public function save()
    {
        $db = Scalr::getDb();
        $pk = $this->getPrimaryKey();
        $actual = $this->getActual();

        $new = !$actual->$pk;
        if ($new && empty($this->$pk)) {
            throw new UpgradeException('%s has not been provided yet for class %s', $pk, get_class($this));
        }

        $set = "";
        foreach ($this->getChanges() as $prop => $value) {
            if ($prop == 'hash' || $prop == 'uuid') {
                $qstr = "UNHEX(" . $db->qstr($value) . ")";
            } else {
                $qstr = $db->qstr($value);
            }
            $set .= ",`" . $prop . "` = " . $qstr;
        }

        if (!empty($set)) {
            $stmt = ($new ? "INSERT" : "UPDATE") . " `" . UpgradeHandler::DB_TABLE_UPGRADES . "`
                SET " . ltrim($set, ',') . "
                " . (!$new ? "WHERE `" . $pk . "` = UNHEX(" . $db->qstr($this->$pk) . ")" : "");

            $db->Execute($stmt);

            //Synchronizes actual state of the entity
            $this->getChanges()->synchronize();
        }
    }

	/**
     * {@inheritdoc}
     * @see Scalr\Upgrade\Entity.AbstractUpgradeEntity::createFailureMessage()
     */
    public function createFailureMessage($log)
    {
        $db = Scalr::getDb();
        $db->Execute("
            INSERT `" . UpgradeHandler::DB_TABLE_UPGRADE_MESSAGES . "`
            SET `created` = ?,
                `uuid` = UNHEX(?),
                `message` = ?
        ", array(
            gmdate('Y-m-d H:i:s'),
            $this->uuid,
            $log
        ));
    }
}
