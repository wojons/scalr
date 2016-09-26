<?php

namespace Scalr\LogCollector;

use FarmLogMessage;


/**
 * UserLogger
 */
class UserLogger extends AbstractLogger
{
    /**
     * Constructor. Instantiates UserLogger, prepares backend
     */
    public function __construct()
    {
        parent::__construct(\Scalr::config('scalr.logger.user'));
    }

    /**
     * {@inheritdoc}
     * @see AbstractLogger::initializeSubscribers()
     */
    protected function initializeSubscribers()
    {
        parent::initializeSubscribers();

        $this->subscribers['user.log'] = [$this, 'handlerUserLog'];
    }

    /**
     * user.log handler
     *
     * @param  FarmLogMessage|array $message Message wor writing in log
     * @param  string $severity Logging level name (from \Scalr\Logger::logLevelName)
     *
     * @return array   Returns array of the fields to log
     */
    protected function handlerUserLog($message, $severity = null)
    {
        if ($message instanceof FarmLogMessage) {
            $data = [
                '.severity'     => is_null($severity) ? null : strtolower($severity),
                '.message'      => $message->Message,
                '.farm_id'      => $message->FarmID,
                '.env_id'       => $message->envId,
                '.farm_role_id' => $message->farmRoleId,
                '.server_id'    => $message->ServerID,
            ];
        }

        return isset($data) ? $data : $message;
    }
}
