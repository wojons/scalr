<?php
namespace Scalr\Upgrade\Updates;

use Scalr\Model\Entity\Account\EnvironmentProperty;
use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;
use Scalr\Model\Entity;

class Update20150827084022 extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '03161fa7-5a08-426b-8f67-9896ddd1c4e6';

    protected $depends = [];

    protected $description = 'Decrypt ec2.account_id in client_environment_properties table';

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
        $envProps = EnvironmentProperty::find([['name' => Entity\CloudCredentialsProperty::AWS_ACCOUNT_ID]]);

        foreach ($envProps as $prop) {
            /* @var $prop EnvironmentProperty */
            if (!is_numeric($prop->value)) {
                $prop->value = \Scalr::getContainer()->crypto->decrypt($prop->value);
                $prop->save();
            }
        }

    }
}