<?php

use Scalr\Model\Entity\Image;

/**
 * @deprecated It has been deprecated since 02.12.2014 because of implementing a new Scalr service.
 * @see        \Scalr\System\Zmq\Cron\Task\ImagesCleanup
 */
class RolesQueueProcess implements \Scalr\System\Pcntl\ProcessInterface
{
    public $ThreadArgs;
    public $ProcessDescription = "Roles queue (images queue)";
    public $Logger;
    public $IsDaemon;

    // TODO: in new cron system rewrite as cron image delete

    public function __construct()
    {
        // Get Logger instance
        $this->Logger = Logger::getLogger(__CLASS__);
    }

    public function OnStartForking()
    {
        $db = \Scalr::getDb();

        foreach (Image::find([['status' => Image::STATUS_DELETE]]) as $image) {
            try {
                /* @var $image Image */
                $image->deleteCloudImage();
                $image->delete();
            } catch (Exception $e) {
                $flag = false;

                if (strpos($e->getMessage(), 'The resource could not be found') !== FALSE) {
                    $flag = true;
                } else if (strpos($e->getMessage(), 'The requested URL / was not found on this server.') !== FALSE) {
                    $flag = true;
                } else if (strpos($e->getMessage(), 'Not Found') !== FALSE) {
                    $flag = true;
                } else if (strpos($e->getMessage(), 'was not found') !== FALSE) {
                    $flag = true;
                } else if (strpos($e->getMessage(), 'OpenStack error. Image not found.') !== FALSE) {
                    $flag = true;
                }

                if ($flag) {
                    $image->delete();
                } else {
                    $image->status = Image::STATUS_FAILED;
                    $image->statusError = $e->getMessage();
                    $image->save();
                }
            }
        }
    }

    public function OnEndForking()
    {

    }

    public function StartThread($farminfo)
    {

    }
}
