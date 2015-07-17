<?php
namespace Scalr\Upgrade\Updates;

use ArrayObject;
use Exception;
use ReflectionClass;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Util\CryptoTool;
use SplFileInfo;

class Update20150116130622 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '096bcbee-1b47-46ef-9215-7a0fa63a1b0b';

    protected $depends = [];

    protected $description = "Reencrypts crypted fields with AES256";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

    /**
     * Decrypting tool
     *
     * @var CryptoTool
     */
    private $source;

    /**
     * Global Variables decrypting tool
     *
     * @var CryptoTool
     */
    private $globals;

    /**
     * Encrypting tool
     *
     * @var CryptoTool
     */
    private $target;

    /**
     * UpdateCrypto
     *
     * {@inheritdoc}
     */
    public function __construct(SplFileInfo $fileInfo, ArrayObject $collection)
    {
        parent::__construct($fileInfo, $collection);

        $this->source = \Scalr::getContainer()->crypto(MCRYPT_TRIPLEDES, MCRYPT_MODE_CFB, null, 24, 8);
        $this->globals = \Scalr::getContainer()->crypto(
            MCRYPT_RIJNDAEL_256,
            MCRYPT_MODE_CFB,
            null,
            mcrypt_get_key_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CFB),
            mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CFB)
        );

        $this->target = \Scalr::getContainer()->crypto;
    }

    /**
     * {@inheritdoc}
     * @see Scalr\Upgrade.SequenceInterface::getNumberStages()
     */
    public function getNumberStages()
    {
        return 1;
    }

    protected function isApplied1($stage)
    {
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->console->out(
            sprintf(
                "Reencrypting %s database from %s/%s to %s/%s!",
                \Scalr::getContainer()->config->get('scalr.connections.mysql.name'),
                $this->source->getCryptoAlgo(),
                $this->source->getCipherMode(),
                $this->target->getCryptoAlgo(),
                $this->target->getCipherMode()
            )
        );

        set_error_handler(function ($code, $message, $file, $line, $context) {
            \Scalr::errorHandler($code, $message, $file, $line, $context);

            if ($code == E_STRICT) {
                throw new Exception($message);
            }
        }, E_USER_ERROR | E_STRICT | E_RECOVERABLE_ERROR | E_ERROR);

        try {
            $this->db->Execute('START TRANSACTION;');

            $this->recrypt('ssh_keys', ['private_key', 'public_key']);
            $this->recrypt('services_ssl_certs', ['ssl_pkey', 'ssl_pkey_password']);
            $this->recrypt('dm_sources', ['auth_info']);
            $this->recrypt('account_user_settings', ['value'], "WHERE `name` = 'security.2fa.ggl.key'", ['user_id', 'name']);
            $this->recrypt('services_chef_servers', ['auth_key', 'v_auth_key']);

            $this->recrypt('variables', ['value'], '', ['name'], $this->globals);
            $this->recrypt('account_variables', ['value'], '', ['name', 'account_id'], $this->globals);
            $this->recrypt('client_environment_variables', ['value'], '', ['name', 'env_id'], $this->globals);
            $this->recrypt('role_variables', ['value'], '', ['name', 'role_id'], $this->globals);
            $this->recrypt('farm_variables', ['value'], '', ['name', 'farm_id'], $this->globals);
            $this->recrypt('farm_role_variables', ['value'], '', ['name', 'farm_role_id'], $this->globals);
            $this->recrypt('server_variables', ['value'], '', ['name', 'server_id'], $this->globals);

            $reflection = new ReflectionClass('Scalr_Environment');
            $method = $reflection->getMethod('getEncryptedVariables');
            $method->setAccessible(true);

            $this->recrypt('client_environment_properties', ['value'], "WHERE `name` IN ('" . implode("','", array_keys($method->invoke(null))) . "')");

            $this->db->Execute("COMMIT;");
        } catch (\Exception $e) {
            $this->rollback($e->getCode(), $e->getMessage());
            restore_error_handler();
            throw $e;
        }

        restore_error_handler();
    }

    public function rollback($code, $message) {
        $this->console->error("{$code}: {$message}");

        if($this->db) {
            $this->console->error("Changes will be rolled back!");
            $this->db->Execute("ROLLBACK;");
            $this->console->error("Changes rolled back!");
        }

        return false;
    }

    /**
     * Reencrypts specified fields
     *
     * @param string     $table  Table name
     * @param string[]   $fields Fields name
     * @param string     $where  WHERE statement for SELECT query
     * @param string[]   $pks    Primary keys names
     * @param CryptoTool $source
     *
     * @return int Returns number of affected rows
     */
    public function recrypt($table, $fields, $where = '', $pks = ['id'], CryptoTool $source = null)
    {
        if($source === null) {
            $source = $this->source;
        }

        $this->console->out("Reencrypting table '{$table}' fields:\n\t" . implode("\n\t", $fields));

        $names = '`' . implode('`, `', array_merge($pks, $fields)) . '`';

        $data = $this->db->Execute("SELECT {$names} FROM `{$table}` {$where} FOR UPDATE;");

        $params = '`' . implode('` = ?, `', $fields) . '` = ?';
        $where = '`' . implode('` = ? AND `', $pks) . '` = ?';
        $stmt = $this->db->Prepare("UPDATE `{$table}` SET {$params} WHERE {$where};");

        $affected = 0;

        foreach ($data as $entry) {
            $in = [];

            foreach ($fields as $field) {
                $in[] = $this->target->encrypt($source->_decrypt($entry[$field]));
            }

            foreach ($pks as $pk) {
                $in[] = $entry[$pk];
            }

            $this->db->Execute($stmt, $in);

            $affected += $this->db->Affected_Rows();
        }

        $this->console->out("Updated {$affected} rows!\n");

        return $affected;
    }
}