<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update20150618135317 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = 'ad115a13-8ab8-471a-8ba0-6ce07287ab01';

    protected $depends = [];

    protected $description = "Initializes os database table";

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
        return $this->db->GetOne("SELECT EXISTS (SELECT 1 FROM os)") == 1;
    }

    protected function validateBefore1($stage)
    {
        return $this->hasTable('os');
    }

    protected function run1($stage)
    {
        $this->db->Execute("
            INSERT INTO `os` (id, name, family, generation, version, status, is_system, created) VALUES
            ('amazon-2013-03','Amazon Linux 2013.03','amazon','2013.03','2013.03','active',1,'0000-00-00 00:00:00'),
            ('amazon-2014-03','Amazon Linux 2014.03','amazon','2014.03','2014.03','active',1,'0000-00-00 00:00:00'),
            ('amazon-2014-09','Amazon Linux 2014.09','amazon','2014.09','2014.09','active',1,'0000-00-00 00:00:00'),
            ('amazon-2015-03','Amazon Linux 2015.03','amazon','2015.03','2015.03','active',1,'0000-00-00 00:00:00'),
            ('centos-5-x','CentOS 5.X Final','centos','5','5.X','active',1,'0000-00-00 00:00:00'),
            ('centos-6-x','CentOS 6.X Final','centos','6','6.X','active',1,'0000-00-00 00:00:00'),
            ('centos-7-x','CentOS 7.X Final','centos','7','7.X','active',1,'0000-00-00 00:00:00'),
            ('debian-5-x','Debian 5.X Lenny','debian','5','5.X','active',1,'0000-00-00 00:00:00'),
            ('debian-6-x','Debian 6.X Squeeze','debian','6','6.X','active',1,'0000-00-00 00:00:00'),
            ('debian-7-x','Debian 7.X Wheezy','debian','7','7.X','active',1,'0000-00-00 00:00:00'),
            ('debian-8-x','Debian 8.X Jessie','debian','8','8.X','active',0,'2015-06-03 09:51:51'),
            ('oracle-5-x','Oracle Enterprise Linux Server 5.X Tikanga','oel','5','5.X','active',1,'0000-00-00 00:00:00'),
            ('oracle-6-x','Oracle Enterprise Linux Server 6.X Santiago','oel','6','6.X','active',1,'0000-00-00 00:00:00'),
            ('redhat-5-x','Redhat 5.X Tikanga','redhat','5','5.X','active',1,'0000-00-00 00:00:00'),
            ('redhat-6-x','Redhat 6.X Santiago','redhat','6','6.X','active',1,'0000-00-00 00:00:00'),
            ('redhat-7-x','Redhat 7.X Maipo','redhat','7','7.X','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-10-04','Ubuntu 10.04 Lucid','ubuntu','10.04','10.04','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-10-10','Ubuntu 10.10 Maverick','ubuntu','10.10','10.10','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-11-04','Ubuntu 11.04 Natty','ubuntu','11.04','11.04','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-11-10','Ubuntu 11.10 Oneiric','ubuntu','11.10','11.10','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-12-04','Ubuntu 12.04 Precise','ubuntu','12.04','12.04','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-12-10','Ubuntu 12.10 Quantal','ubuntu','12.10','12.10','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-13-04','Ubuntu 13.04 Raring','ubuntu','13.04','13.04','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-13-10','Ubuntu 13.10 Saucy','ubuntu','13.10','13.10','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-14-04','Ubuntu 14.04 Trusty','ubuntu','14.04','14.04','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-14-10','Ubuntu 14.10 Utopic','ubuntu','14.10','14.10','active',1,'0000-00-00 00:00:00'),
            ('ubuntu-8-04','Ubuntu 8.04 Hardy','ubuntu','8.04','8.04','active',1,'0000-00-00 00:00:00'),
            ('unknown-os','Unknown','unknown','unknown','unknown','active',1,'0000-00-00 00:00:00'),
            ('windows-2003','Windows 2003','windows','2003','','active',1,'0000-00-00 00:00:00'),
            ('windows-2008','Windows 2008','windows','2008','','active',1,'0000-00-00 00:00:00'),
            ('windows-2012','Windows 2012','windows','2012','','active',1,'0000-00-00 00:00:00')
        ");
    }
}