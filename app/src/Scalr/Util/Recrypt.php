<?php

namespace Scalr\Util;

use ArrayObject;
use PDO;
use stdClass;

/**
 * Recrypt
 * @author  N.V.
 */
class Recrypt
{

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var CryptoTool
     */
    private $source;

    /**
     * @var CryptoTool
     */
    private $target;

    /**
     * @param string     $database
     * @param CryptoTool $source
     * @param CryptoTool $target
     */
    public function __construct($database, CryptoTool $source, CryptoTool $target)
    {
        $config = \Scalr::getContainer()->config->get('scalr.connections.mysql');

        if($config['port'] == '~') {
            $config['port'] = 3306;
        }

        $this->pdo = new PDO("mysql:host={$config['host']};port={$config['port']};dbname={$database}", $config['user'], $config['password']);

        $this->source = $source;
        $this->target = $target;
    }

    /**
     * @param string   $table
     * @param string[] $fields
     * @param string   $where
     * @param string[] $pks
     *
     * @return int
     */
    public function recrypt($table, $fields, $where = '', $pks = ['id'])
    {
        print "Recrypting table '{$table}' fields:\n\t" . implode("\n\t", $fields) . "\n";

        $names = '`' . implode('`,`', array_merge($pks, $fields)) . '`';
        $out = new ObjectAccess();

        if(!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
        }

        $data = $this->pdo->query("SELECT {$names} FROM `{$table}` {$where} FOR UPDATE;");
        $data->setFetchMode(PDO::FETCH_INTO, $out);

        $params = static::makeParams($fields, ', ');
        $where = static::makeParams($pks, ' AND ');
        $stmt = $this->pdo->prepare("UPDATE `{$table}` SET {$params} WHERE {$where};");

        foreach ($fields as $field) {
            $stmt->bindParam(":{$field}", $out[$field]);
        }

        foreach ($pks as $pk) {
            $stmt->bindParam(":{$pk}", $out[$pk]);
        }

        $affected = 0;

        foreach ($data as $entry) {
            foreach ($out as $field => $value) {
                if (!in_array($field, $pks)) {
                    $out[$field] = $this->target->encrypt($this->source->decrypt($value));
                }
            }

            $stmt->execute();

            $affected += $stmt->rowCount();
        }

        if($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }

        print "Updated {$affected} rows!\n\n";

        return $affected;
    }

    /**
     * @param array  $fields
     * @param string $glue
     *
     * @return string
     */
    private static function makeParams($fields, $glue = ',')
    {
        $params = [];

        foreach ($fields as $field) {
            $params[] = "`{$field}` = :{$field}";
        }

        return implode($glue, $params);
    }
}