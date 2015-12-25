<?php

namespace Scalr\Upgrade;

use Scalr\Upgrade\Entity\MysqlUpgradeEntity;
use Scalr\Upgrade\Entity\FilesystemUpgradeEntity;
use Scalr\Upgrade\Entity\AbstractUpgradeEntity;
use Scalr\Model\Entity\SettingEntity;
use \DateTime, DateTimeZone;
use Scalr\Exception;

define('FS_STORAGE_PATH', CACHEPATH . '/upgrades/');

define('UPGRADE_PID_FILEPATH', CACHEPATH . '/upgrade.pid');

/**
 * UpgradeHandler
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    4.5.0 (10.10.2013)
 */
class UpgradeHandler
{

    const DB_TABLE_UPGRADES = 'upgrades';

    const DB_TABLE_UPGRADE_MESSAGES = 'upgrade_messages';

    const CMD_RUN_SPECIFIC = 'run-specific';

    /**
     * Path to filesystem storage including enclosing slash
     */
    const FS_STORAGE_PATH = FS_STORAGE_PATH;

    /**
     * Prevents infinity loops
     */
    const MAX_ATTEMPTS = 10;

    /**
     * Database instance
     *
     * @var \ADODB_mysqli
     */
    protected $db;

    /**
     * Max datetime which has been processed
     *
     * @var string
     */
    private $maxDate;

    /**
     * Console
     *
     * @var Console
     */
    protected $console;

    /**
     * The updates list
     *
     * @var UpdateCollection
     */
    private $updates;

    /**
     * The state before upgrade
     *
     * @var array
     */
    private $stateBefore;

    /**
     * Attempts counter to handle loops
     *
     * @var array
     */
    private $attempts;

    /**
     * Recurrences of the failed status
     *
     * @var array
     */
    private $recurrences;

    /**
     * Options
     *
     * @var array
     */
    private $opt;

    /**
     * Constructor
     *
     * @param   object   $opt  Run options
     */
    public function __construct($opt)
    {
        $this->opt = $opt;
        $this->db = \Scalr::getDb();
        $this->console = new Console();
        $this->console->interactive = !empty($opt->interactive);
        $this->updates = new UpdateCollection();
        $this->maxDate = '2013-01-01 00:00:00';
    }

    /**
     * Gets path to updates
     *
     * @return   string Returns path to updates without trailing slash
     */
    public static function getPathToUpdates()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'Updates';
    }

    /**
     * Gets last datetime which has been processed
     *
     * @return  string  Returns UTC in format 'YYYY-MM-DD HH:ii:ss'
     */
    protected function getLastDate()
    {
        return $this->maxDate;
    }

    /**
     * Getches statuses of the previous updates for specified database service
     * from container
     *
     * @param   string $service  Service name for database connection in DI container
     */
    private function fetchMysqlStatusBefore($service = 'adodb')
    {
        $db = \Scalr::getContainer()->{$service};
        //Loads performed updates of MYSQL type
        $rs = $db->Execute("
            SELECT LOWER(HEX(u.`uuid`)) `uuid`, u.`released`, u.`appears`, u.`applied`, u.`status`, LOWER(HEX(u.`hash`)) `hash`
            FROM `" . self::DB_TABLE_UPGRADES . "` u
        ");
        while ($rec = $rs->FetchRow()) {
            $entity = new MysqlUpgradeEntity();
            $entity->load($rec);
            $this->stateBefore[$rec['uuid']] = $entity;
            if (isset($entity->appears) && $this->maxDate < $entity->appears) {
                $this->maxDate = $entity->appears;
            }
        }
    }

    /**
     * Fetches statuses of the previous updates
     */
    private function fetchStatusBefore()
    {
        $this->stateBefore = new \ArrayObject();

        $this->fetchMysqlStatusBefore('adodb');

        if (\Scalr::getContainer()->analytics->enabled) {
            try {
                $this->fetchMysqlStatusBefore('cadb');
            } catch (\Exception $e) {
                if (preg_match('~connect|upgrades. doesn.t exist~i', $e->getMessage())) {
                    //Database may not exist because of creating from some update
                } else {
                    throw $e;
                }
            }
        }

        //Loads updates of FileSystem type
        self::checkFilesystemStorage();

        //Loads performed updates of Filesystem type
        foreach (new FilesystemStorageIterator(self::FS_STORAGE_PATH) as $fileInfo) {
            /* @var $fileInfo \SplFileInfo */
            if (!$fileInfo->isReadable()) {
                throw new Exception\UpgradeException(sprintf(
                    'Could not read from file "%s". Lack of access permissions.', $fileInfo->getFilename()
                ));
            }
            $entity = new FilesystemUpgradeEntity();
            $obj = unserialize(file_get_contents($fileInfo->getPathname()));
            if (!is_object($obj)) {
                throw new Exception\UpgradeException(sprintf(
                    'There was error while trying to load record from filesystem storage "%s". Object is expected, %s given',
                    $fileInfo->getPathname(), gettype($obj)
                ));
            }
            $entity->load($obj);
            $this->stateBefore[$entity->uuid] = $entity;
            if (isset($entity->appears) && $this->maxDate < $entity->appears) {
                $this->maxDate = $entity->appears;
            }
            unset($obj);
        };
    }

    /**
     * Checks filesystem storage
     *
     * @throws   Exception\UpgradeException
     */
    public static function checkFilesystemStorage()
    {
        if (!is_dir(self::FS_STORAGE_PATH)) {
            if (@mkdir(self::FS_STORAGE_PATH, 0777) === false) {
                throw new Exception\UpgradeException(sprintf(
                    'Could not create directory "%s". Lack of access permissions to application cache folder.',
                    self::FS_STORAGE_PATH
                ));
            } else {
                file_put_contents(self::FS_STORAGE_PATH . '.htaccess', "Order Deny,Allow\nDeny from all\n");
                chmod(self::FS_STORAGE_PATH . '.htaccess', 0644);
            }
        }
    }

    /**
     * Loads updates from the implemented classes
     *
     * @retrun bool Returns TRUE if all updates loaded successful, FALSE otherwise
     */
    protected function loadUpdates()
    {
        $this->fetchStatusBefore();

        $success = true;

        foreach (new UpdatesIterator(self::getPathToUpdates()) as $fileInfo) {
            /* @var $fileInfo \SplFileInfo */
            $updateClass = __NAMESPACE__ . '\\Updates\\' . substr($fileInfo->getFilename(), 0, 20);
            try {
                /* @var $update \Scalr\Upgrade\AbstractUpdate */
                $update = new $updateClass($fileInfo, $this->stateBefore);
                $this->updates[$update->getUuidHex()] = $update;
            } catch (\Exception $e) {
                $this->console->error("Error. Could not load update %s. %s", $fileInfo->getPathname(), $e->getMessage());
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Applies update
     *
     * @param   AbstractUpdate $upd Update to apply
     *
     * @return bool Returns true if update is successful, false otherwise
     *
     * @throws Exception\UpgradeException
     */
    protected function applyUpdate(AbstractUpdate $upd)
    {
        if (!isset($this->attempts[$upd->getUuidHex()])) {
            $this->attempts[$upd->getUuidHex()] = 1;
        } else {
            $this->attempts[$upd->getUuidHex()]++;
        }

        if ($this->attempts[$upd->getUuidHex()] > self::MAX_ATTEMPTS) {
            throw new Exception\UpgradeException(sprintf(
                '"%s" Failed due to infinity loop. Max number of attempts (%d) reached!',
                $upd->getName(),
                self::MAX_ATTEMPTS
            ));
        }

        $refuseReason = $upd->isRefused();

        if (false !== $refuseReason) {
            if ($this->opt->verbosity) {
                $this->console->notice('%s is ignored. %s', $upd->getName(), (string)$refuseReason);
            }
            return true;
        }

        if ($upd->getStatus() == AbstractUpgradeEntity::STATUS_OK) {
            //Upgrade file is updated.
            $upd->updateAppears();
            if (isset($this->opt->cmd) && $this->opt->cmd == self::CMD_RUN_SPECIFIC && $this->opt->uuid == $upd->getUuidHex()) {
                //User has requested re-execution of update
                $upd->setStatus(AbstractUpgradeEntity::STATUS_PENDING);
                $upd->updateHash();
                $upd->getEntity()->save();
            } else {
                //Compare checksum
                if ($upd->getEntity()->hash == $upd->getHash()) {
                    //file modified time could be the issue
                    $upd->updateApplied();
                    $upd->getEntity()->save();

                    if (!empty($this->opt->verbosity) ||
                        isset($this->opt->cmd) && $this->opt->cmd == self::CMD_RUN_SPECIFIC && $this->opt->uuid == $upd->getUuidHex()) {
                        $this->console->warning('Ingnoring %s because of having complete status.', $upd->getName());
                    }

                    return true;
                } else {
                    //We should ignore changes in the script and update hash
                    $upd->updateHash();
                    $upd->getEntity()->save();

                    return true;
                }
            }
        }

        $this->console->success('%s...', ($upd->description ?: $upd->getName()));

        //Checks updates this upgrade depends upon
        if (!empty($upd->depends)) {
            foreach ($upd->depends as $uuid) {
                $uuidhex = AbstractUpdate::castUuid($uuid);
                if (!empty($this->updates[$uuidhex])) {
                    $update = $this->updates[$uuidhex];
                    if ($update->getStatus() == AbstractUpgradeEntity::STATUS_OK) {
                        //Relative update has already been successfully applied.
                        continue;
                    }
                } else if (isset($this->stateBefore[$uuidhex])) {
                    /* @var $upgradeEntity \Scalr\Upgrade\Entity\AbstractUpgradeEntity */
                    $upgradeEntity = $this->stateBefore[$uuidhex];
                    if ($upgradeEntity->status == AbstractUpgradeEntity::STATUS_OK) {
                        //Relative update has already been applied
                        continue;
                    } else {
                        //Relative update needs to be applied before dependant.
                        $this->console->warning(
                            '"%s" has been declined as it depends on incomplete update "%s" which has status "%s". '
                          . 'Desired class "%s" does not exist in the expected folder.',
                            $upd->getName(),
                            $uuid,
                            $upgradeEntity->getStatusName(),
                            $upgradeEntity->getUpdateClassName()
                        );
                        return false;
                    }
                } else {
                    //Relative update has not been delivered yet.
                    $this->console->warning(
                        '"%s" has been postponed as it depends on "%s" which has not been delivered yet.',
                        $upd->getName(), $uuid
                    );
                    return false;
                }

                if ($update->getStatus() == AbstractUpgradeEntity::STATUS_FAILED && isset($this->recurrences[$update->getUuidHex()])) {
                    //Recurrence of the failed status. We don't need to report about it again.
                    $this->console->warning(
                        '"%s" has been declined because of failure dependent update "%s".',
                        $upd->getName(), $uuid
                    );
                    return false;
                }

                //Relative update has not been applied or it has incomplete status.
                //We need to apply it first.
                if ($this->applyUpdate($update) === false) {
                    $this->console->warning(
                        '"%s" has been declined. Could not apply related update "%s".',
                        $upd->getName(), $update->getName()
                    );
                    return false;
                }
            }
        }

        //Checks if update class implements SequenceInterface
        $stages = $upd instanceof SequenceInterface ? range(1, $upd->getNumberStages()) : array(1);
        $skip = 0;

        foreach ($stages as $stage) {
            //Checks if update is applied
            if ($upd->isApplied($stage)) {
                $upd->console->warning(
                    'Skips over the stage %d of update %s because it has already been applied.',
                    $stage, $upd->getName()
                );
                $skip++;
                continue;
            }

            //Validates environment before applying
            if (!$upd->validateBefore($stage)) {
                $this->console->error(
                    'Error. Stage %d of update %s could not be applied because of invalid environment! validateBefore(%d) returned false.',
                    $stage, $upd->getName(), $stage
                );

                return false;
            }

            //Applies the update
            try {
                $upd->run($stage);
            } catch (\Exception $e) {
                //We should avoid repetition when another update depends on failed.
                $this->recurrences[$upd->getUuidHex()] = true;

                $upd->setStatus(AbstractUpgradeEntity::STATUS_FAILED);
                $upd->console->error('Error. Stage %d of update %s failed! %s', $stage, $upd->getName(), $e->getMessage());
                $upd->getEntity()->save();
                $upd->getEntity()->createFailureMessage($upd->console->getLog());

                return false;
            }
        }

        $this->console->success("%s - OK", ($upd->description ?: $upd->getName()));
        $upd->setStatus(AbstractUpgradeEntity::STATUS_OK);
        $upd->updateHash();
        $upd->updateApplied();
        $upd->getEntity()->save();

        return true;
    }

    /**
     * Checks and creates pid file
     *
     * @return boolean Returns false if process has already been started or creates a new pid file
     */
    public static function checkPid()
    {
        $dbScalr = \Scalr::getContainer()->adodb;

        if (file_exists(UPGRADE_PID_FILEPATH) && ($pid = file_get_contents(UPGRADE_PID_FILEPATH)) > 0 && !self::isOutdatedPid(trim($pid))) {
            return false;
        }

        foreach ([['adodb', 'Scalr\Model\Entity\SettingEntity'], ['cadb', 'Scalr\Stats\CostAnalytics\Entity\SettingEntity']] as $p) {
            if ($p[0] == 'cadb' && !\Scalr::getContainer()->analytics->enabled) continue;

            try {
                $db = \Scalr::getContainer()->{$p[0]};
                $database = $db->getOne("SELECT DATABASE()");
            } catch (\Exception $e) {
                printf("\nCould not check pid file for %s service\n", $p[0]);
                continue;
            }

            if ($db->getOne("SHOW TABLES FROM `" . $database . "` LIKE ?", ['settings'])) {
                if (($pid = call_user_func($p[1] . '::getValue', SettingEntity::ID_UPGRADE_PID)) > 0 && !self::isOutdatedPid($pid)) {
                    return false;
                }

                call_user_func($p[1] . '::setValue', SettingEntity::ID_UPGRADE_PID, time());
            }
        }

        file_put_contents(UPGRADE_PID_FILEPATH, time());
        chmod(UPGRADE_PID_FILEPATH, 0666);

        return true;
    }

    /**
     * Checks if pid is outdated
     *
     * @param   string    $timestamp
     * @return  boolean   Returns true if current pid is outdated
     */
    private static function isOutdatedPid($timestamp)
    {
        if (!is_numeric($timestamp)) return true;
        $date = new DateTime('@' . $timestamp, new DateTimeZone('UTC'));
        return $date->diff(new DateTime('now', new DateTimeZone('UTC')), true)->days > 0;
    }


    /**
     * Removes pid
     */
    public static function removePid()
    {
        if (file_exists(UPGRADE_PID_FILEPATH)) {
            unlink(UPGRADE_PID_FILEPATH);
        }

        //!TODO get rid of code duplication
        foreach ([['adodb', 'Scalr\Model\Entity\SettingEntity'], ['cadb', 'Scalr\Stats\CostAnalytics\Entity\SettingEntity']] as $p) {
            if ($p[0] == 'cadb' && !\Scalr::getContainer()->analytics->enabled) continue;
            try {
                $db = \Scalr::getContainer()->{$p[0]};
                $database = $db->getOne("SELECT DATABASE()");
            } catch (\Exception $e) {
                printf("\nCould not remove pid file for %s service\n", $p[0]);
                continue;
            }

            if ($db->getOne("SHOW TABLES FROM `" . $database . "` LIKE ?", ['settings'])) {
                call_user_func($p[1] . '::setValue', SettingEntity::ID_UPGRADE_PID, null);
            }
        }
    }

    /**
     * Runs upgrade process
     *
     * @return bool Returns true if all updates completed successfully, false otherwise
     */
    public function run()
    {
        if (!self::checkPid()) {
            $this->console->warning("Cannot start a new process because another one has already been started.");
            return false;
        }

        register_shutdown_function('Scalr\Upgrade\UpgradeHandler::removePid');

        //Loads updates
        $successful = $this->loadUpdates();

        if (isset($this->opt->cmd) && $this->opt->cmd == self::CMD_RUN_SPECIFIC) {
            $pending = [];
            if (!isset($this->updates[$this->opt->uuid])) {
                $this->console->warning("Could not find specified update %s", $this->opt->uuid);
                exit(1);
            }
            $pending[] = $this->updates[$this->opt->uuid];
        } else {
            $dt = new \DateTime($this->getLastDate(), new \DateTimeZone('UTC'));
            $pending = $this->updates->getPendingUpdates($dt->getTimestamp());
        }

        if (count($pending) == 0) {
            $this->console->out('Scalr is up-to-date');
            return $successful;
        }

        $this->console->success('Starting Scalr upgrade');

        //Applies updates
        foreach ($pending as $update) {
            $update->console->interactive = $this->console->interactive;
            $successful = $this->applyUpdate($update) && $successful;
        }

        $this->console->success('Done');

        return $successful;
    }
}