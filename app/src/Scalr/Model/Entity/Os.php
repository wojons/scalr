<?php
namespace Scalr\Model\Entity;

use Scalr\Model\AbstractEntity;

/**
 * Operating system entity
 *
 * @author   Igor Savchenko  <igor@scalr.com>
 * @since    5.3 (03.02.2015)
 *
 * @Entity
 * @Table(name="os")
 */
class Os extends AbstractEntity
{
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    const ID_REGEXP = '[[:alnum:]-]+';
    const UNKNOWN_OS = 'unknown-os';

    /**
     * ID
     *
     * @Id
     * @Column(type="string")
     * @var string
     */
    public $id;

    /**
     * OS's name
     *
     * @Column(type="string")
     * @var string
     */
    public $name;

    /**
     * Operating system family (centos, ubuntu, etc)
     *
     * @Column(type="string")
     * @var string
     */
    public $family;

    /**
     * Operating system generation
     *
     * @Column(type="string")
     * @var string
     */
    public $generation;

    /**
     * Operating system version
     *
     * @Column(type="string")
     * @var string
     */
    public $version;

    /**
     * Is system OS
     *
     * @Column(type="integer")
     * @var integer
     */
    public $isSystem;

    /**
     * Os status (active, inactive)
     *
     * @Column(type="string")
     * @var string
     */
    public $status;

    /**
     * Time when the record is created
     *
     * @Column(type="datetime")
     * @var \DateTime
     */
    public $created;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->created = new \DateTime();
    }

    /**
     * Fetches identifiers of the OSes which satisfy specified criteria
     *
     * @param   string $family      The family
     * @param   string $generation  optional The generation
     * @param   string $version     optional The version
     * @return  array  Returns array of the identifiers of the OSes which satisfy specified criteria
     */
    public static function findIdsBy($family, $generation = null, $version = null)
    {
        $criteria = [['family' => $family]];

        if ($generation)
            $criteria[] = ['generation' => $generation];

        if ($version)
            $criteria[] = ['version' => $version];

        $os = Os::find($criteria);
        $osIds = [];
        foreach ($os as $i) {
            /* @var $i Os */
            array_push($osIds, $i->id);
        }

        return $osIds;
    }

    /**
     * @return array|false
     */
    public function getUsed()
    {
        $used = [];
        $used['rolesCount'] = $this->db()->GetOne('SELECT COUNT(*) FROM roles WHERE os_id = ?', [$this->id]);
        $used['imagesCount'] = $this->db()->GetOne('SELECT COUNT(*) FROM images WHERE os_id = ?', [$this->id]);

        return $used['rolesCount'] == 0 && $used['imagesCount'] == 0 ? false : $used;
    }

}