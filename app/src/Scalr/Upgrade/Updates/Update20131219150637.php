<?php

namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20131219150637 extends AbstractUpdate implements SequenceInterface
{
    protected $uuid = '41759aa1-d64a-4fb0-99c8-75dda031b3dd';

    protected $depends = array(
        '7b9bb8ed-02b6-46d8-8b06-438612541e3c'
    );

    protected $description = 'Encrypt pkey and pkey_password fields';

    protected $ignoreChanges = true;

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
        return $this->hasTableColumn('services_ssl_certs', 'ssl_simple_pkey');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTableColumn('services_ssl_certs', 'ssl_pkey');
    }

    protected function run1($stage)
    {
        $crypto = \Scalr::getContainer()->crypto;

        $this->console->out('Creating field ssl_simple_pkey');
        $this->db->Execute(
            'ALTER TABLE services_ssl_certs ADD `ssl_simple_pkey` text'
        );

        $this->console->out('Encrypting values');
        $values = $this->db->GetAll('SELECT * FROM services_ssl_certs');
        foreach ($values as $value) {
            if ($value['ssl_pkey']) {
                $this->db->Execute('UPDATE services_ssl_certs SET ssl_simple_pkey = ?, ssl_pkey = ? WHERE id = ?', array(
                    $value['ssl_pkey'],
                    $crypto->encrypt($value['ssl_pkey']),
                    $value['id']
                ));
            }
        }
    }
}
