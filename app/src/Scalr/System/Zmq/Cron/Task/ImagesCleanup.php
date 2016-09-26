<?php
namespace Scalr\System\Zmq\Cron\Task;

use ArrayObject, Exception, DateTime, DateTimeZone, stdClass;
use Scalr\System\Zmq\Cron\AbstractTask;
use Scalr\Model\Entity\Image;

/**
 * ImagesCleanup
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0.1 (02.12.2014)
 */
class ImagesCleanup extends AbstractTask
{
    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::enqueue()
     */
    public function enqueue()
    {
        /* @var $image Image */
        foreach (Image::find([['status' => Image::STATUS_DELETE]]) as $image) {
            try {
                /* @var $image Image */
                $image->deleteCloudImage();
                $image->delete();
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'The resource could not be found') !== false ||
                    strpos($e->getMessage(), 'The requested URL / was not found on this server.') !== false ||
                    strpos($e->getMessage(), 'Not Found') !== false ||
                    strpos($e->getMessage(), 'was not found') !== false ||
                    strpos($e->getMessage(), 'OpenStack error. Image not found.') !== false) {
                    $image->delete();
                } else {
                    $image->status = Image::STATUS_FAILED;
                    $image->statusError = $e->getMessage();
                    $image->save();
                }
            }
        }

        //Workers do not need to be used
        return new ArrayObject([]);
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\TaskInterface::worker()
     */
    public function worker($request)
    {
        return $request;
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\System\Zmq\Cron\AbstractTask::config()
     */
    public function config()
    {
        $config = parent::config();

        if ($config->daemon) {
            //Report a warning to log
            trigger_error(sprintf("Demonized mode is not allowed for '%s' job.", $this->name), E_USER_WARNING);

            //Forces normal mode
            $config->daemon = false;
        }

        if ($config->workers != 1) {
            //It cannot be performed through ZMQ MDP as execution time exceeds heartbeat
            trigger_error(sprintf("It is allowed only one worker for the '%s' job.", $this->name), E_USER_WARNING);
            $config->workers = 1;
        }

        return $config;
    }
}