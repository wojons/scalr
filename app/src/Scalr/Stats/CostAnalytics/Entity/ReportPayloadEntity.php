<?php
namespace Scalr\Stats\CostAnalytics\Entity;

use Scalr\Model\AbstractEntity;
use \DateTime, \DateTimeZone;

/**
 * ReportPayloadEntity
 *
 * @author   Vlad Dobrovolskiy  <v.dobrovolskiy@scalr.com>
 * @since    5.0
 * @Entity
 * @Table(name="report_payloads",service="cadb")
 */
class ReportPayloadEntity extends AbstractEntity
{

    /**
     * Report identifier
     *
     * @Id
     * @Column(type="uuid")
     * @var string
     */
    public $uuid;

    /**
     * The date and time Y-m-d H:00:00
     *
     * @Column(type="UTCDatetime")
     * @var DateTime
     */
    public $created;

    /**
     * Secret hash (SHA1)
     *
     * @Column(type="binary")
     * @var string
     */
    public $secret;

    /**
     * The payload
     *
     * @Column(type="string")
     * @var string
     */
    public $payload;

    /**
     * Initializes ReportPayloadEntity object
     *
     * @param  array                $data    Data array of params for uuid (Notification type, subject type, subject id)
     * @param  array                $payload Payload
     * @param  string|DateTime|null $start   optional Start date of the report (Y-m-d)
     * @return ReportPayloadEntity  Returns  ReportPayloadEntity object
     */
    public static function init(array $data, $payload, $start = null)
    {
        $obj = new self;

        $obj->created = $start instanceof DateTime ? clone $start : new DateTime(($start ?: "now"), new DateTimeZone('UTC'));

        $dataToHash = implode('|', $data) . '|' . $obj->created->format('Y-m-d');

        $obj->uuid = $obj->type('uuid')->toPhp(substr(hash('sha1', $dataToHash, true), 0, 16));

        $obj->secret = $obj->type('secret')->toPhp(hash('sha1', \Scalr::GenerateRandomKey(40), true));

        $baseUrl = rtrim(\Scalr::getContainer()->config('scalr.endpoint.scheme') . "://" . \Scalr::getContainer()->config('scalr.endpoint.host') , '/');

        $payload['reportUrl'] = $baseUrl . '#/public/report?uuid=' . $obj->uuid . '&secretHash=' . bin2hex((string)$obj->secret);

        $obj->payload = json_encode($payload);

        return $obj;
    }
}