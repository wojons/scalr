<?php
namespace Scalr\Stats\CostAnalytics;

use Scalr\Stats\CostAnalytics\Entity\PriceHistoryEntity;
use Scalr\Stats\CostAnalytics\Entity\PriceEntity;
use Scalr\Model\Collections\ArrayCollection;
use Scalr\Modules\PlatformFactory;
use \SERVER_PLATFORMS;
use Scalr\Model\Entity\CloudLocation;

/**
 * Cloud prices
 *
 * @author   Vitaliy Demidov  <vitaliy@scalr.com>
 * @since    5.0 (05.02.2014)
 */
class Prices
{

    /**
     * Analytics database connection
     *
     * @var \ADODB_mysqli
     */
    protected $cadb;

    /**
     * Constructor
     *
     * @param \ADODB_mysqli $cadb
     */
    public function __construct($cadb)
    {
        $this->cadb = $cadb;
    }

    /**
     * Gets price
     *
     * @param    PriceHistoryEntity|string $price
     *           The identifier of the price or PriceHistoryEntity object.
     *           If you provide with object either priceId or both platform and cloudLocation
     *           properties must be set for it. Identifier must be provided as UUID without hyphens.
     *
     * @return   PriceHistoryEntity|bool
     *           Returns price history entity that contains current prices on success or false if nothing found.
     *
     * @throws   \InvalidArgumentException
     */
    public function get($price)
    {
        if (!($price instanceof PriceHistoryEntity)) {
            $obj = new PriceHistoryEntity();
            $obj->priceId = (string)$price;
            $price = $obj;
        }

        if ($price->priceId) {
            $ret = PriceHistoryEntity::findPk($price->priceId);
        } else if ($price->platform !== null && $price->cloudLocation !== null) {
            if ($price->url === null) {
                $price->url = '';
            }
            if ($price->applied === null) {
                $price->applied = new \DateTime('now', new \DateTimeZone('UTC'));
            } else if (!($price->applied instanceof \DateTime)) {
                $price->applied = new \DateTime((string)$price->applied, new \DateTimeZone('UTC'));
            }
            if ($price->accountId === null) {
                $price->accountId = 0;
            }

            $ret = PriceHistoryEntity::findOne([
                ['platform'      => $price->platform],
                ['url'           => $price->url],
                ['cloudLocation' => $price->cloudLocation],
                ['accountId'     => $price->accountId],
                ['applied'       => ['$lte' => $price->applied->format('Y-m-d')]],
            ], ['applied' => false]);
        } else {
            throw new \InvalidArgumentException(sprintf(
                "Either priceId or both platform and cloudLocation properties must be set for PriceHistoryEntity."
            ));
        }

        if (isset($ret)) {
            foreach (get_object_vars($ret) as $k => $v) {
                $price->$k = $v;
            }
            return $price;
        }

        return false;
    }

    /**
     * Saves price
     *
     * @param  PriceHistoryEntity $price The PriceHistoryEntity with details set.
     * @throws \InvalidArgumentException
     * @throws \ADODB_Exception
     */
    public function save(PriceHistoryEntity $price)
    {
        if (!isset($price->platform) || !isset($price->cloudLocation)) {
            throw new \InvalidArgumentException(sprintf(
                "Both platform and cloudLocation properties must be set"
            ));
        }

        if (!isset($price->applied)) {
            $price->applied = new \DateTime('now', new \DateTimeZone('UTC'));
        } else if (!($price->applied instanceof \DateTime)) {
            $price->applied = new \DateTime($price->applied, new \DateTimeZone('UTC'));
        }

        if (!$price->priceId) {
            //Trying to find if the price already exists on this day
            $found = PriceHistoryEntity::findOne([
                ['platform'      => $price->platform],
                ['url'           => ($price->url ?: '')],
                ['cloudLocation' => $price->cloudLocation],
                ['accountId'     => ($price->accountId ?: 0)],
                ['applied'       => $price->applied->format('Y-m-d')],
            ]);
            if ($found) {
                $price->priceId = $found->priceId;
            }
        }

        if (!$price->priceId) {
            $bNew = true;
            $price->priceId = \Scalr::GenerateUID();
        } else {
            $bNew = false;
        }

        $sId = "`price_id` = " . $price->qstr('priceId');

        $this->cadb->BeginTrans();

        try {
            $this->cadb->Execute("
                " . ($bNew ? "INSERT" : "UPDATE") . " " . $price->table() . "
                SET " . ($bNew ? $sId . "," : "") . "
                    platform = ?,
                    url = ?,
                    cloud_location = ?,
                    account_id = ?,
                    applied = ?,
                    deny_override = ?
                " . ($bNew ? "" : "WHERE " . $sId . " LIMIT 1") . "
            ", [
                $price->platform,
                ($price->url ?: ''),
                $price->cloudLocation,
                $price->accountId ?: 0,
                $price->applied->format('Y-m-d'),
                ($price->denyOverride ? 1 : 0),
            ]);

            //Removes previous values
            if (!$bNew) {
                $this->cadb->Execute("DELETE FROM `prices` WHERE price_id = " . $price->qstr('priceId'));
            }

            $stmt = "";

            foreach ($price->getDetails() as $priceEntity) {
                if ($priceEntity instanceof PriceEntity) {
                    $stmt .= ", (" . $price->qstr('priceId') . ", "
                           . $priceEntity->qstr('instanceType') . ", "
                           . $priceEntity->qstr('os') . ", "
                           . $priceEntity->qstr('name') . ", "
                           . $priceEntity->qstr('cost') . ")";
                } else {
                    throw new \InvalidArgumentException(sprintf(
                        "Details should contain collection of the PriceEntity objects. %s given.",
                        gettype($priceEntity)
                    ));
                }
            }

            if ($stmt !== '') {
                $this->cadb->Execute("REPLACE `prices` (`price_id`,`instance_type`, `os`, `name`, `cost`) VALUES " . ltrim($stmt, ','));
            }

            $this->cadb->CommitTrans();
        } catch (\Exception $e) {
            $this->cadb->RollbackTrans();

            throw $e;
        }
    }

    /**
     * Gets the collection of the prices for the specified price_id
     *
     * @param   PriceHistoryEntity|string  $price
     *          The PriceHistoryEntity or identifier of the price.
     *          Identifier must be provided as UUID without hyphens.
     * @return  \ArrayObject        Returns all prices which are associated with this price history ID
     */
    public function getDetails($price)
    {
        if ($price instanceof PriceHistoryEntity) {
            if (!$price->priceId) {
                throw new \InvalidArgumentException("Identifier of the price must be set.");
            }
            $priceId = $price->priceId;
        } else {
            $priceId = (string) $price;
        }

        return PriceEntity::findByPriceId($priceId);
    }


    /**
     * Gets the history of the changes of the specified price
     *
     * @param   string       $platform      The cloud platform
     * @param   string       $cloudLocation The cloud location
     * @param   string       $url           optional The keystone url for the private cloud
     * @param   string       $accountId     optional The identifier of the account for overridden price
     * @return  \ArrayObject Returns collection of the PriceHistoryEntity objects
     */
    public function getHistory($platform, $cloudLocation, $url = null, $accountId = null)
    {
        $accountId = $accountId ?: 0;
        $url = $url ?: '';

        return PriceHistoryEntity::find([
            ['platform'      => $platform],
            ['cloudLocation' => $cloudLocation],
            ['url'           => $url],
            ['accountId'     => $accountId],
        ], ['applied' => true]);
    }

    /**
     * Gets actual prices on specified date
     *
     * @param   string    $platform      The name of the cloud platform
     * @param   string    $cloudLocation The location of the cloud
     * @param   string    $url           optional The keystone url for the private clouds
     * @param   \DateTime $applied       optional The date in UTC
     * @param   int       $accountId     optional ID of the account (global level by default)
     */
    public function getActualPrices($platform, $cloudLocation, $url = null, \DateTime $applied = null, $accountId = null)
    {
        $ret = new ArrayCollection();

        if (!($applied instanceof \DateTime)) {
            $applied = new \DateTime('now', new \DateTimeZone('UTC'));
        }

        $accountId = $accountId ?: 0;
        $url = $url ?: '';

        $res = $this->cadb->Execute("
            SELECT ph.cloud_location, ph.applied, ph.deny_override,
                   p.instance_type, p.os, p.price_id, p.cost
            FROM price_history ph
            JOIN prices p ON p.price_id = ph.price_id
            LEFT JOIN price_history ph2 ON ph2.platform = ph.platform
                AND ph2.cloud_location = ph.cloud_location
                AND ph2.account_id = ph.account_id
                AND ph2.url = ph.url
                AND ph2.applied > ph.applied AND ph2.applied <= ?
            LEFT JOIN prices p2 ON p2.price_id = ph2.price_id
                AND p2.instance_type = p.instance_type
                AND p2.os = p.os
            WHERE ph.account_id = ? AND p2.price_id IS NULL
            AND ph.platform = ?
            AND ph.cloud_location = ?
            AND ph.url = ?
            AND ph.applied <= ?
        ", [
            $applied->format('Y-m-d'),
            $accountId,
            $platform,
            $cloudLocation,
            $url,
            $applied->format('Y-m-d')
        ]);

        $ph = [];
        while ($rec = $res->FetchRow()) {
            $item = new PriceEntity();
            $item->load($rec);

            if (!isset($ph[$item->priceId])) {
                $rec['platform'] = $platform;
                $rec['url'] = $url;

                $ph[$item->priceId] = new PriceHistoryEntity();
                $ph[$item->priceId]->load($rec);
            }

            $item->setPriceHistory($ph[$item->priceId]);

            $ret->append($item);
        }

        return $ret;
    }

    /**
     * Normalizes an url from environment settings to use for price_history table
     *
     * @param   string    $url  Original url
     * @return  string    Returns normalized url to use in price_history database table
     */
    public function normalizeUrl($url)
    {
        return CloudLocation::normalizeUrl($url);
    }

    /**
     * Gets all known cloud locations for the specified platform.
     *
     * These locations are retrieved from prices table, not from
     * the environment settings.
     *
     * @param   string    $platform  The cloud platform
     * @return  array     Returns array looks like array(url1 => array(cloudLocation1, cloudLocation2, ...), url2 => ...);
     */
    public function getCloudLocations($platform)
    {
        $ret = [];

        $res = $this->cadb->Execute("
            SELECT DISTINCT `url`, `cloud_location` FROM `price_history`
            WHERE `platform` = ?
        ", [$platform]);

        while ($rec = $res->FetchRow()) {
            $url = is_null($rec['url']) ? '' : $rec['url'];
            $ret[$url][] = $rec['cloud_location'];
        }

        return $ret;
    }

    /**
     * Checks whether there is some price for specified platform and url
     *
     * @param    string    $platform      Cloud platform
     * @param    string    $url           The endpoint url
     * @param    string    $cloudLocation optional The cloud location
     * @return   bool      Returns TRUE if there is some price for specified platform and url or FALSE otherwise
     */
    public function hasPriceForUrl($platform, $url, $cloudLocation = null)
    {
        $res = $this->cadb->getOne("
            SELECT EXISTS(
                SELECT 1 FROM `price_history`
                WHERE `platform` = ? AND url = ?
                " . (!empty($cloudLocation) ? "AND cloud_location = " . $this->cadb->qstr($cloudLocation) : "") . "
            ) AS `val`
        ", [
            $platform,
            (empty($url) ? '' : $this->normalizeUrl($url))
        ]);

        return !!$res;
    }

    /**
     * Gets array of supported clouds
     *
     * @return array
     */
    public function getSupportedClouds()
    {
        $allowedClouds = (array) \Scalr::config('scalr.allowed_clouds');

        return array_values(array_intersect($allowedClouds, array_merge([
                SERVER_PLATFORMS::EC2,
            ],
            array_diff(PlatformFactory::getOpenstackBasedPlatforms(), [\SERVER_PLATFORMS::CONTRAIL]),
            PlatformFactory::getCloudstackBasedPlatforms()
        )));
    }

    /**
     * Gets array of unsupported clouds
     *
     * @return array
     */
    public function getUnsupportedClouds()
    {
        return array_diff(array_keys(SERVER_PLATFORMS::GetList()), $this->getSupportedClouds());
    }

}