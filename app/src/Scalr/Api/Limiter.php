<?php

namespace Scalr\Api;

use ADODB_Exception;
use ADORecordSet;
use DateTime;
use DateTimeZone;
use Scalr\Api\DataType\ErrorMessage;
use Scalr\Api\Rest\Exception\ApiErrorException;
use Scalr\Exception\ModelException;
use Scalr\Model\Entity\Account\User;
use Scalr\Model\Entity\ApiCounter;

/**
 * API Rate Limiter
 *
 * @author N.V.
 */
class Limiter
{

    /**
     * Indicates whether limits is enabled
     *
     * @var bool
     */
    private $enabled = false;

    /**
     * Limit value
     *
     * @var int
     */
    private $limit;

    /**
     * Max HEAP size for MySQL MEMORY tables
     *
     * @var int
     */
    private $maxHeapSize;

    /**
     * Limiter
     *
     * @param   array   $config API Limits configuration
     */
    public function __construct($config)
    {
        $this->enabled = (bool) $config['enabled'];
        $this->limit = (int) $config['limit'];
        $this->maxHeapSize = (int) $config['storage_max_size'];
    }

    /**
     * Check rate limit of specified account, and increase requests count.
     * In case of exceeding limit generates exception.
     *
     * @param   int $apiKeyId  API key id to check limit
     *
     * @return bool
     *
     * @throws ApiErrorException
     * @throws ModelException
     */
    public function checkAccountRateLimit($apiKeyId)
    {
        if ($this->enabled) {
            $date = new DateTime('now', new DateTimeZone("UTC"));

            $counter = $this->safeDbCall(function() use ($date, $apiKeyId) {
                return ApiCounter::findPk($date, $apiKeyId);
            }, [$this, 'createApiCountersTable']);

            if (empty($counter)) {
                $counter = new ApiCounter();

                $counter->date = $date;
                $counter->apiKeyId = $apiKeyId;
            }

            $counter->requests++;

            $this->safeDbCall(function() use ($counter) {
                $counter->save();
            }, [$this, 'truncateApiCounters']);

            if ($counter->requests > $this->limit) {
                throw new ApiErrorException(403, ErrorMessage::ERR_LIMIT_EXCEEDED, "The maximum request rate permitted by the Scalr APIs has been exceeded for your account. For best results, use an increasing or variable sleep interval between requests.");
            }
        }

        return true;
    }

    /**
     * Performs actions with the DB that can lead to failure
     *
     * @param   callable    $action     Call that can lead to DB failure, should not accept arguments
     * @param   callable    $fallback   Action in case of failure, takes arguments: ADODB_Exception and callable, that will be called if managed to recover
     *
     * @return  mixed   Returns result of $action, if not failed, result of $fallback otherwise
     */
    private function safeDbCall(callable $action, callable $fallback)
    {
        $adodbStore = $this->forceAdodbStoreMysqliResult();

        try {
            $result = $action();
        } catch (ADODB_Exception $e) {
            $result = $fallback($e, $action);
        }

        $this->forceAdodbStoreMysqliResult();

        return $result;
    }

    /**
     * Handles exception, when table does not exists.
     * Creates `api_counters` table.
     *
     * @param   ADODB_Exception $e      Database error
     * @param   callable        $action A failed action
     *
     * @return  mixed   Returns result of $action, called after creating `api_counters` table
     *
     * @throws  ADODB_Exception
     */
    private function createApiCountersTable(ADODB_Exception $e, callable $action)
    {
        if ($e->getCode() == 1146) {
            $db = \Scalr::getDb();

            $prevHeapTableSize = $db->GetRow("SELECT @@max_heap_table_size");

            if ($prevHeapTableSize) {
                if ($prevHeapTableSize < $this->maxHeapSize) {
                    $prevHeapTableSize = array_shift($prevHeapTableSize);

                    $db->Execute("SET `max_heap_table_size` = {$this->maxHeapSize}");
                } else {
                    $prevHeapTableSize = null;
                }
            }

            $db->Execute("
                    CREATE TABLE IF NOT EXISTS `api_counters` (
                      `date` DATETIME NOT NULL,
                      `api_key_id` CHAR(20) NOT NULL,
                      `requests` INT(11) UNSIGNED NULL,
                      PRIMARY KEY (`date`, `api_key_id`))
                    ENGINE = MEMORY
                    DEFAULT CHARACTER SET = utf8;
            ");

            //This is necessary for slaves.
            $db->Execute("DELETE FROM `api_counters`");

            if (isset($prevHeapTableSize)) {
                //Restore previous value
                $db->Execute("SET `max_heap_table_size` = {$prevHeapTableSize}");
            }

            $adodbStore = $this->forceAdodbStoreMysqliResult();

            $result = $action();

            $this->restoreAdodbResultStore($adodbStore);

            return $result;
        }

        throw $e;
    }

    /**
     * Handles exception, when table is full.
     * Truncates `api_counter` table.
     *
     * @param   ADODB_Exception $e      Database error
     * @param   callable        $action A failed action
     *
     * @return  mixed   Returns result of $action, called after `api_counters` truncation
     *
     * @throws  ADODB_Exception
     * @throws  ApiErrorException
     */
    private function truncateApiCounters(ADODB_Exception $e, callable $action)
    {
        if ($e->getCode() == 1114) {
            \Scalr::getDb()->Execute("DELETE FROM `api_counters` WHERE `date` < NOW() - INTERVAL 1 MINUTE");

            $adodbStore = $this->forceAdodbStoreMysqliResult();

            try {
                $result = $action();
            } catch (ADODB_Exception $e2) {
                $this->restoreAdodbResultStore($adodbStore);

                \Scalr::getContainer()->logger(__CLASS__)->error("After successful clearing `api_counters` table exception occurred: {$e2->getMessage()}");

                if ($e2->getCode() == 1114) {
                    throw new ApiErrorException(403, ErrorMessage::ERR_LIMIT_EXCEEDED, "The maximum request rate permitted by the Scalr APIs has been exceeded for your account. For best results, use an increasing or variable sleep interval between requests.");
                }

                throw $e2;
            }

            $this->restoreAdodbResultStore($adodbStore);

            return $result;
        }

        throw $e;
    }

    /**
     * Forces ADOdb use `mysqli_store_result` instead `mysqli_use_result`.
     * This workaroung bug with 'Commands out of sync' mysqli error.
     *
     * @return bool Previous value
     */
    private function forceAdodbStoreMysqliResult()
    {
        global $ADODB_COUNTRECS;

        $previous = $ADODB_COUNTRECS;

        $ADODB_COUNTRECS = true;

        return $previous;
    }

    /**
     * Sets ADOdb behavior to work with `mysqli_result`
     *
     * @param bool $storeResult Previous value to be restored
     */
    private function restoreAdodbResultStore($storeResult = false)
    {
        global $ADODB_COUNTRECS;

        $ADODB_COUNTRECS = $storeResult;
    }
}