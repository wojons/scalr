<?php
namespace Scalr\System\Zmq\Cron;

use ArrayObject;

/**
 * Task interface
 *
 * @author Vitaliy Demidov <vitaliy@scalr.com>
 * @since  5.0 (10.09.2014)
 */
interface TaskInterface
{

    /**
     * Gets the name of the task (service)
     *
     * @return  string
     */
    public function getName();

    /**
     * Gets configuration options for the task
     *
     * @return  object Returns configuration options for the task
     */
    public function config();

    /**
     * It enqueues tasks to process by worker
     *
     * It executes within the client's thread
     *
     * @return  ArrayObject   Returns the task queue
     */
    public function enqueue();

    /**
     * Implementation of the php worker that process client's request
     *
     * It executes within the worker's thread
     *
     * @param   mixed   $request  Request. It is one element from the task queue.
     * @return  mixed   Returns response
     */
    public function worker($request);

    /**
     * Action that might need to be done on worker's response.
     *
     * It executes within the client's thread.
     * This method is not necessarily to be overriden.
     *
     * @param   AbstractPayload   $response  A response from worker
     */
    public function onResponse(AbstractPayload $payload);

    /**
     * Shutdown
     *
     * It terminates all child processes
     */
    public function shutdown();

    /**
     * Executes task routine.
     *
     * It ensures that all workers are running, then starts client, which communicates to
     * workers via 0MQ using MDP API.
     *
     * It also should take care that another pid of the task is not running.
     * Task MUST be launched asynchronously in the separete process.
     *
     * This method may be overriden to force task execution in one process.
     * In this case workers config option will be ignored.
     *
     * @return  void
     */
    public function run();

    /**
     * Launches client in separate process
     *
     * This method is called by service
     *
     * @return  int Returns PID of the client
     */
    public function launch();

    /**
     * Runs another one ZMQ MDP worker for this task in the separate process
     *
     * If you want to run non-php worker this method can be overriden
     *
     * @param   string  $address  optional   An address to override the name of the service
     * @return  int     Returns PID of the worker
     */
    public function addWorker($address = null);

    /**
     * Checks whether memory limit is reached
     *
     * @return  boolean Returns FALSE if memory limit is reached
     */
    public function checkMemoryUsage();
}