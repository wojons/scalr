<?php
namespace Scalr\System\Zmq\Cron;

use Scalr\DataType\Iterator\AbstractFilter;
use Iterator;
use DateTime;
use InvalidArgumentException;
use Exception;

/**
 * Scheduled service iterator
 *
 * List of the enabled services which have to be started at specified time
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0 (15.09.2014)
 */
class ScheduledServiceIterator extends AbstractFilter
{
    /**
     * Scheduled time in system timestamp
     *
     * @var  DateTime
     */
    private $start;

    /**
	 * Constructor
	 */
    public function __construct(DateTime $start)
    {
        $this->start = $start;

        if (!($start instanceof DateTime)) {
            throw new InvalidArgumentException(sprintf(
                "First argument must be instance of the DateTime. '%s' given",
                (is_object($start) ? get_class($start) : gettype($start))
            ));
        }

        parent::__construct(new ServiceIterator());
    }

    /**
     * {@inheritdoc}
     * @see \Scalr\DataType\Iterator\AbstractFilter::accept()
     */
    public function accept()
    {
        try {
            $config = $this->current()->config();

            if ($config->enabled && empty($config->cronExpression)) {
                throw new Exception(sprintf("Missing scalr.crontab.services.%s.time option in config", $this->current()->getName()));
            }
        } catch (Exception $e) {
            //Prevents crashing the service if there are no valid config for the task
            trigger_error($e->getMessage(), E_USER_WARNING);
            return false;
        }

        return $config->enabled && $config->cronExpression->isDue($this->start);
    }
}