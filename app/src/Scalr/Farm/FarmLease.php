<?php
namespace Scalr\Farm;
use Scalr_Util_CryptoTool;

class FarmLease
{
    protected $farm;

    const STATUS_PENDING = 'pending';
    const STATUS_APPROVE = 'approve';
    const STATUS_DECLINE = 'decline';
    const STATUS_CANCEL = 'cancel';

    /**
     * @var \ADODB_mysqli
     */
    protected $db;

    public static function getKey()
    {
        return Scalr_Util_CryptoTool::sault(8);
    }

    public function __construct(\DBFarm $dbFarm)
    {
        $this->db = \Scalr::getDb();
        $this->farm = $dbFarm;
    }

    public function addRequest($days, $comment, $userId)
    {
        $this->db->Execute('INSERT INTO `farm_lease_requests` (`farm_id`, `request_days`, `request_time`, `request_comment`, `request_user_id`, `status`) VALUES(?,?,NOW(),?,?,?)', array(
            $this->farm->ID, $days, $comment, $userId, self::STATUS_PENDING
        ));
    }

    public function getLastRequest()
    {
        return $this->db->GetRow('SELECT fl.*, u.email AS request_user_email FROM `farm_lease_requests` fl
            LEFT JOIN account_users u ON fl.request_user_id = u.id
            WHERE farm_id = ? ORDER BY id DESC LIMIT 1', array($this->farm->ID));
    }

    public function cancelLastRequest()
    {
        $last = $this->getLastRequest();
        if ($last && $last['status'] == self::STATUS_PENDING) {
            $this->db->Execute('UPDATE farm_lease_requests SET status = ? WHERE id = ?', array(self::STATUS_CANCEL, $last['id']));
            return true;
        } else
            return false;
    }
}
