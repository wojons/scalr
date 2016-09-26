<?php

namespace Scalr\Util;

use ADODB_mysqli;
use Scalr\Upgrade\Console;

/**
 * Re-encryption tool
 *
 * @author  N.V.
 */
class Recrypt
{

    /**
     * Database connection
     *
     * @var ADODB_mysqli
     */
    private $db;

    /**
     * Scheme
     *
     * @var string
     */
    private $scheme;

    /**
     * Previous encryption
     *
     * @var CryptoTool
     */
    private $source;

    /**
     * New encryption
     *
     * @var CryptoTool
     */
    private $target;

    /**
     * Recrypt
     *
     * @param string     $scheme    Database scheme
     * @param CryptoTool $source    Current encryption
     * @param CryptoTool $target    New encryption
     * @param Console    $console   Console handler
     */
    public function __construct($scheme, CryptoTool $source, CryptoTool $target, Console $console)
    {
        $this->db = \Scalr::getDb();

        $this->scheme = $scheme;

        $this->source = $source;
        $this->target = $target;

        $this->console = $console;
    }

    /**
     * Starts re-encryption
     *
     * @return $this
     */
    public function begin()
    {
        $this->db->BeginTrans();

        return $this;
    }

    /**
     * Commit changes
     *
     * @return $this
     */
    public function commit()
    {
        $this->db->CommitTrans();

        return $this;
    }

    /**
     * Reencrypts specified fields
     *
     * @param string     $table           Table name
     * @param string[]   $fields          Fields name
     * @param string     $where  optional WHERE statement for SELECT query
     * @param string[]   $pks    optional Primary keys names
     * @param CryptoTool $source optional Alternative source CryptoTool
     *
     * @return int Returns number of affected rows
     */
    public function recrypt($table, $fields, $where = '', $pks = ['id'], CryptoTool $source = null)
    {
        if (empty($this->db->transCnt)) {
            $this->begin();
        }

        if ($source === null) {
            $source = $this->source;
        }

        $this->console->out("Reencrypting table `{$this->scheme}`.`{$table}` fields:\n\t" . implode("\n\t", $fields));

        $names = '`' . implode('`, `', array_merge($pks, $fields)) . '`';

        $data = $this->db->Execute("SELECT {$names} FROM `{$this->scheme}`.`{$table}` {$where} FOR UPDATE;");

        $params = '`' . implode('` = ?, `', $fields) . '` = ?';
        $where = '`' . implode('` = ? AND `', $pks) . '` = ?';
        $stmt = $this->db->Prepare("UPDATE `{$this->scheme}`.`{$table}` SET {$params} WHERE {$where};");

        $affected = 0;

        foreach ($data as $entry) {
            $in = [];

            foreach ($fields as $field) {
                $in[] = $this->target->encrypt($source->decrypt($entry[$field]));
            }

            foreach ($pks as $pk) {
                $in[] = $entry[$pk];
            }

            $this->db->Execute($stmt, $in);

            $affected += $this->db->Affected_Rows();
        }

        $this->console->out("Updated {$affected} rows!");

        return $affected;
    }

    /**
     * Gets current scheme name
     *
     * @return string
     */
    public function getScheme()
    {
        return $this->scheme;
    }

    /**
     * Sets scheme name
     *
     * @param $scheme
     *
     * @return $this
     */
    public function setScheme($scheme)
    {
        $this->scheme = $scheme;

        return $this;
    }

    /**
     * Gets source CryptoTool
     *
     * @return CryptoTool
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Gets target CryptoTool
     *
     * @return CryptoTool
     */
    public function getTarget()
    {
        return $this->target;
    }
}