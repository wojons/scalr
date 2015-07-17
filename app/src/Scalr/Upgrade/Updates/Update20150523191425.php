<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150523191425 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '0eb539fc-516f-44ab-ac27-bb0046ff9fdd';

    protected $depends = [];

    protected $description = "Increases size of the name column of account_teams table up to 255 characters";

    protected $ignoreChanges = true;

    protected $dbservice = 'adodb';

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
        return $this->hasTableColumnType('account_teams', 'name', 'varchar(255)');
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTableColumn('account_teams', 'name');
    }

    protected function run1($stage)
    {
        //It also changes charset to default, considering that we have not been allowing to provide
        //multibyte characters with the name property.
        $this->db->Execute("
            ALTER TABLE `account_teams`
                MODIFY `name` varchar(255) DEFAULT NULL,
                MODIFY `description` varchar(255) DEFAULT NULL
        ");
    }
}