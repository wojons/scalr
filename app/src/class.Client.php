<?php

use Scalr\Model\AbstractEntity;
use Scalr\Model\Collections\EntityIterator;
use Scalr\Model\Entity;

class Client
{
    public $ID;
    public $IsActive;
    public $Fullname;
    public $AddDate;
    public $DueDate;
    public $IsBilled;
    public $Organization;
    public $Country;
    public $State;
    public $City;
    public $ZipCode;
    public $Address1;
    public $Address2;
    public $Phone;
    public $Fax;
    public $Comments;

    /**
     * @var \ADODB_mysqli
     */
    private $DB;

    private static $ClientsCache = [];

    private static $FieldPropertyMap = [
        'id'       => 'ID',
        'isactive' => 'IsActive',
        'fullname' => 'Fullname',
        'dtadded'  => 'AddDate',
        'dtdue'    => 'DueDate',
        'isbilled' => 'IsBilled',
        'org'      => 'Organization',
        'country'  => 'Country',
        'state'    => 'State',
        'city'     => 'City',
        'zipcode'  => 'ZipCode',
        'address1' => 'Address1',
        'address2' => 'Address2',
        'phone'    => 'Phone',
        'fax'      => 'Fax',
        'comments' => 'Comments'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->DB = \Scalr::getDb();
    }

    /**
     * Load Client Object by ID
     *
     * @param integer $id
     * @return Client
     */
    public static function Load($id)
    {
        if (!isset(self::$ClientsCache[$id])) {
            $db = \Scalr::getDb();

            $clientinfo = $db->GetRow("SELECT * FROM clients WHERE id=?", [$id]);
            if (empty($clientinfo)) {
                throw new Exception(sprintf(_("Client ID#%s not found in database"), $id));
            }

            $client = new Client();

            foreach(self::$FieldPropertyMap as $k => $v) {
                if (isset($clientinfo[$k])) {
                    $client->{$v} = $clientinfo[$k];
                }
            }

            self::$ClientsCache[$id] = $client;
        }

        return self::$ClientsCache[$id];
    }

    /**
     * Load Client Object by E-mail
     * @param string $email
     * @return Client $Client
     */
    public static function LoadByEmail($email)
    {
        $db = \Scalr::getDb();

        $clientid = $db->GetOne("SELECT id FROM clients WHERE email=? LIMIT 1", array($email));
        if (!$clientid)
            throw new Exception(sprintf(_("Client with email=%s not found in database"), $email));

        return self::Load($clientid);
    }

    /**
     * Returns client setting value by name
     *
     * @param string $name
     * @return mixed $value
     */
    public function GetSettingValue($name)
    {
        return $this->DB->GetOne("SELECT value FROM client_settings WHERE clientid=? AND `key`=? LIMIT 1",
            array($this->ID, $name)
        );
    }

    /**
     * Set client setting
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function SetSettingValue($name, $value)
    {
        //UNIQUE KEY `NewIndex1` (`clientid`,`key`)
        $this->DB->Execute("
            INSERT client_settings
            SET `clientid`=?,
                `key`=?,
                `value`=?
            ON DUPLICATE KEY UPDATE
                `value`=?
        ", array(
            $this->ID, $name, $value, $value
        ));
    }

    public function ClearSettings ($filter)
    {
        $this->DB->Execute(
            "DELETE FROM client_settings WHERE `key` LIKE '{$filter}' AND clientid = ?",
            array($this->ID)
        );
    }

    /**
     * Gets cloud credentials for listed clouds
     *
     * @param   string[]    $clouds             optional Clouds list
     * @param   array       $credentialsFilter  optional Criteria to filter by CloudCredentials properties
     * @param   array       $propertiesFilter   optional Criteria to filter by CloudCredentialsProperties
     *
     * @return  EntityIterator|Entity\CloudCredentials[]
     */
    public function cloudCredentialsList(array $clouds = null, array $credentialsFilter = [], array $propertiesFilter = [])
    {
        if (!is_array($clouds)) {
            $clouds = (array) $clouds;
        }

        $cloudCredentials = new Entity\CloudCredentials();
        $cloudCredProps = new Entity\CloudCredentialsProperty();

        $criteria = $credentialsFilter;
        $from[] = empty($criteria[AbstractEntity::STMT_FROM]) ? " {$cloudCredentials->table()} " : $criteria[AbstractEntity::STMT_FROM];
        $where = empty($criteria[AbstractEntity::STMT_WHERE]) ? [] : [$criteria[AbstractEntity::STMT_WHERE]];

        $criteria[] = ['accountId' => $this->ID];

        if (!empty($clouds)) {
            $clouds = implode(", ", array_map(function ($cloud) use ($cloudCredentials) {
                return $cloudCredentials->qstr('cloud', $cloud);
            }, $clouds));

            $where[] = "{$cloudCredentials->columnCloud()} IN ({$clouds})";
        }

        if (!empty($propertiesFilter)) {
            foreach ($propertiesFilter as $property => $propCriteria) {
                $alias = "ccp_" . trim($cloudCredentials->db()->qstr($property), "'");

                $from[] = "
                    LEFT JOIN {$cloudCredProps->table($alias)} ON
                        {$cloudCredentials->columnId()} = {$cloudCredProps->columnCloudCredentialsId($alias)} AND
                        {$cloudCredProps->columnName($alias)} = {$cloudCredProps->qstr('name', $property)}
                ";

                $built = $cloudCredProps->_buildQuery($propCriteria, 'AND', $alias);

                if (!empty($built['where'])) {
                    $where[] = $built['where'];
                }
            }
        }

        $criteria[AbstractEntity::STMT_FROM] = implode("\n", $from);

        if (!empty($where)) {
            $criteria[AbstractEntity::STMT_WHERE] = "(" . implode(") AND (", $where) . ")";
        }

        return Entity\CloudCredentials::find($criteria);
    }
}

