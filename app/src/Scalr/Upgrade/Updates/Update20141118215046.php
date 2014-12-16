<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20141118215046 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'd4d28849-a44d-431c-87f4-aaa2b2f7744d';

    protected $depends = [];

    protected $description = 'Normalize CentOS and RHEL images/roles version';

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
        return false;
    }

    protected function validateBefore1($stage)
    {
        return true;
    }

    protected function run1($stage)
    {
        $this->db->Execute("UPDATE images SET os='CentOS 5.X Final', os_generation='5', os_version='5.X' WHERE os_family='centos' AND os_version='5' AND os_generation IS NULL");
        $this->db->Execute("UPDATE images SET os='CentOS 6.X Final', os_generation='6', os_version='6.X' WHERE os_family='centos' AND os_version='6' AND os_generation IS NULL");
        $this->db->Execute("UPDATE images SET os='CentOS 7.X Final', os_generation='7', os_version='7.X' WHERE os_family='centos' AND os_version='7' AND os_generation IS NULL");
        
        $this->db->Execute("UPDATE images SET os_version='5.X', os='CentOS 5.X Final' WHERE os_family='centos' AND os_generation = '5' AND os_version != '5.X'");
        $this->db->Execute("UPDATE images SET os_version='6.X', os='CentOS 6.X Final' WHERE os_family='centos' AND os_generation = '6' AND os_version != '6.X'");
        $this->db->Execute("UPDATE images SET os_version='7.X', os='CentOS 7.X Final' WHERE os_family='centos' AND os_generation = '7' AND os_version != '7.X'");
        
        $this->db->Execute("UPDATE images SET os_version='5.X', os='Redhat 5.X Tikanga' WHERE os_family='redhat' AND os_generation = '5' AND os_version != '5.X'");
        $this->db->Execute("UPDATE images SET os_version='6.X', os='Redhat 6.X Santiago' WHERE os_family='redhat' AND os_generation = '6' AND os_version != '6.X'");
        $this->db->Execute("UPDATE images SET os_version='7.X', os='Redhat 7.X Maipo' WHERE os_family='redhat' AND os_generation = '7' AND os_version != '7.X'");
        
        $this->db->Execute("UPDATE images SET os_version='5.X', os='Oracle Enterprise Linux Server 5.X Tikanga' WHERE os_family='oel' AND os_generation = '5' AND os_version != '5.X'");
        $this->db->Execute("UPDATE images SET os_version='6.X', os='Oracle Enterprise Linux Server 6.X Santiago' WHERE os_family='oel' AND os_generation = '6' AND os_version != '6.X'");
        $this->db->Execute("UPDATE images SET os_version='7.X', os='Oracle Enterprise Linux Server 7.X Maipo' WHERE os_family='oel' AND os_generation = '7' AND os_version != '7.X'");
        
        
        $this->db->Execute("UPDATE roles SET os='CentOS 5.X Final', os_generation='5', os_version='5.X' WHERE os_family='centos' AND os_version='5' AND os_generation IS NULL");
        $this->db->Execute("UPDATE roles SET os='CentOS 6.X Final', os_generation='6', os_version='6.X' WHERE os_family='centos' AND os_version='6' AND os_generation IS NULL");
        $this->db->Execute("UPDATE roles SET os='CentOS 7.X Final', os_generation='7', os_version='7.X' WHERE os_family='centos' AND os_version='7' AND os_generation IS NULL");
        
        $this->db->Execute("UPDATE roles SET os_version='5.X', os='CentOS 5.X Final' WHERE os_family='centos' AND os_generation = '5' AND os_version != '5.X'");
        $this->db->Execute("UPDATE roles SET os_version='6.X', os='CentOS 6.X Final' WHERE os_family='centos' AND os_generation = '6' AND os_version != '6.X'");
        $this->db->Execute("UPDATE roles SET os_version='7.X', os='CentOS 7.X Final' WHERE os_family='centos' AND os_generation = '7' AND os_version != '7.X'");
        
        $this->db->Execute("UPDATE roles SET os_version='5.X', os='Redhat 5.X Tikanga' WHERE os_family='redhat' AND os_generation = '5' AND os_version != '5.X'");
        $this->db->Execute("UPDATE roles SET os_version='6.X', os='Redhat 6.X Santiago' WHERE os_family='redhat' AND os_generation = '6' AND os_version != '6.X'");
        $this->db->Execute("UPDATE roles SET os_version='7.X', os='Redhat 7.X Maipo' WHERE os_family='redhat' AND os_generation = '7' AND os_version != '7.X'");
        
        $this->db->Execute("UPDATE roles SET os_version='5.X', os='Oracle Enterprise Linux Server 5.X Tikanga' WHERE os_family='oel' AND os_generation = '5' AND os_version != '5.X'");
        $this->db->Execute("UPDATE roles SET os_version='6.X', os='Oracle Enterprise Linux Server 6.X Santiago' WHERE os_family='oel' AND os_generation = '6' AND os_version != '6.X'");
        $this->db->Execute("UPDATE roles SET os_version='7.X', os='Oracle Enterprise Linux Server 7.X Maipo' WHERE os_family='oel' AND os_generation = '7' AND os_version != '7.X'");
    }
}