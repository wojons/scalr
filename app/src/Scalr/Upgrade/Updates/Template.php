<?= '<' . '?php' ?>

namespace Scalr\Upgrade\Updates;

use Scalr\Upgrade\SequenceInterface;
use Scalr\Upgrade\AbstractUpdate;

class Update<?= $upd_released ?> extends AbstractUpdate implements SequenceInterface
{

    protected $uuid = '<?= $upd_uuid ?>';

    protected $depends = [];

    protected $description = /* It should be set! */;

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
        throw new \Scalr\Exception\UpgradeException("Not implemented yet");
    }
}