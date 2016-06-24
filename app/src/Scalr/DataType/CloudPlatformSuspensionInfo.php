<?php

namespace Scalr\DataType;

use Scalr\Model\Entity\Account\EnvironmentProperty;

/**
 * CloudPlatformSuspensionInfo
 *
 * @author  Vitaliy Demidov <vitaliy@scalr.com>
 * @since   5.9 (27.06.2015)
 */
class CloudPlatformSuspensionInfo
{
    /**
     * Seconds since the time the fist error occured
     * after which platform should be suspended
     *
     * (6 hours)
     */
    const PLATFORM_SUSPEND_INTERVAL = 21600;

    /**
     * Fist error time property
     */
    const NAME_FIRST_ERROR_OCCURRED = 'suspension.first_error_occurred';

    /**
     * Last message property
     */
    const NAME_LAST_ERROR_MESSAGE = 'suspension.last_error';

    /**
     * Is platform suspended property suffix
     */
    const NAME_SUSPENDED = 'suspended';

    /**
     * The identifier of the environment
     *
     * @var   int
     */
    public $envId;

    /**
     * The name of the cloud platform
     *
     * @var string
     */
    public $platform;

    /**
     * The group of client environment properties
     *
     * @var string
     */
    public $group = '';

    public $cloud;

    /**
     * The timestamp when the first event related to platform suspension happens
     *
     * @var EnvironmentProperty
     */
    private $firstErrorOccurred;

    /**
     * Last error message related to platform suspension
     *
     * @var EnvironmentProperty
     */
    private $lastErrorMessage;

    /**
     * Whether current platform is suspended
     *
     * @var EnvironmentProperty
     */
    private $suspended;

    /**
     * The property name for the first error occurred
     *
     * @var string
     */
    private $firstErrorOccurredProp;

    /**
     * The property name for the last error message
     *
     * @var string
     */
    private $lastErrorMessageProp;

    /**
     * The property name for whether platform is suspended
     *
     * @var string
     */
    private $suspendedProp;

    /**
     * Constructor
     *
     * @param   int     $envId               The identifier of the Client's environment
     * @param   string  $platform            The name of the cloud platform
     * @param   string  $group      optional The client environment property group
     */
    public function __construct($envId, $platform, $group = null)
    {
        $this->envId = $envId;
        $this->platform = $platform;
        $this->group = $group ?: '';
        $this->cloud = null;

        $this->firstErrorOccurredProp = $platform . '.' . static::NAME_FIRST_ERROR_OCCURRED;
        $this->lastErrorMessageProp = $platform . '.' . static::NAME_LAST_ERROR_MESSAGE;
        $this->suspendedProp = $platform . '.' . static::NAME_SUSPENDED;

        $properties = EnvironmentProperty::find([
            ['envId' => $this->envId],
            ['group' => $this->group],
            ['$or'   => [
                ["name" => $this->firstErrorOccurredProp],
                ["name" => $this->lastErrorMessageProp],
                ["name" => $this->suspendedProp]
            ]],
        ]);

        foreach ($properties as $property) {
            if ($property->name == $this->firstErrorOccurredProp) {
                $this->firstErrorOccurred = $property;
            } elseif ($property->name == $this->lastErrorMessageProp) {
                $this->lastErrorMessage = $property;
            } else {
                $this->suspended = $property;
            }
        }
    }

    /**
     * Initializes environment property
     *
     * @param    string    $name  The name of the property
     * @param    string    $value optional The value of the property
     * @return   EnvironmentProperty  Returns a new environment property object
     */
    private function initProp($name, $value = null)
    {
        $property = new EnvironmentProperty();
        $property->envId = $this->envId;
        $property->name = $name;
        $property->group = $this->group;
        $property->value = $value ?: '';

        return $property;
    }

    /**
     * Whether current platform is suspended
     *
     * @return   boolean  Returns true if cloud platform is suspended
     */
    public function isSuspended()
    {
        return !empty($this->suspended->value);
    }

    /**
     * Whether current platform has suspension errors
     *
     * @return   boolean  Returns true if the cloud platform is in the progress of the suspension
     *                    due to ongoing errors
     */
    public function isPendingSuspend()
    {
        return empty($this->suspended->value) && !empty($this->firstErrorOccurred->value);
    }

    /**
     * Suspends the cloud platform
     */
    public function suspend()
    {
        if ($this->suspended instanceof EnvironmentProperty) {
            if (!$this->suspended->value) {
                $this->suspended->value = 1;
                $this->suspended->save();
            }
        } else {
            $this->suspended = $this->initProp($this->suspendedProp, 1);
            $this->suspended->save();
        }
    }

    /**
     * Registers suspension error and suspends cloud platform if the timeout is reached
     *
     * @param    string   $errorMessage  The error message
     */
    public function registerError($errorMessage)
    {
        if (!$this->firstErrorOccurred instanceof EnvironmentProperty) {
            $this->firstErrorOccurred = $this->initProp($this->firstErrorOccurredProp, time());
            $this->firstErrorOccurred->save();
        } elseif (empty($this->firstErrorOccurred->value)) {
            $this->firstErrorOccurred->value = time();
            $this->firstErrorOccurred->save();
        } elseif ($this->isSuspended()) {
            //If cloud platform is already suspended we don't keep collecting error messages
            return;
        } else {
            //Checks suspension timeout
            if (time() - $this->firstErrorOccurred->value > static::PLATFORM_SUSPEND_INTERVAL) {
                //The cloud platform should be suspended
                $this->suspend();
            }
        }

        if ($this->lastErrorMessage instanceof EnvironmentProperty) {
            $this->lastErrorMessage->value = $errorMessage;
        } else {
            $this->lastErrorMessage = $this->initProp($this->lastErrorMessageProp, $errorMessage);
        }

        $this->lastErrorMessage->save();
    }

    /**
     * Resumes cloud platform
     */
    public function resume()
    {
        foreach ([$this->suspended, $this->firstErrorOccurred, $this->lastErrorMessage] as $property) {
            if ($property instanceof EnvironmentProperty) {
               $property->delete();
            }
        }

        $this->suspended = null;
        $this->firstErrorOccurred = null;
        $this->lastErrorMessage = null;
    }

    /**
     * Checks whether it is the cloud platform suspension exception
     * @param   \Exception   $e
     * @return  boolean      Returns true if the exception is the suspension type
     */
    public static function isSuspensionException(\Exception $e)
    {
        $message = $e->getMessage();

        $ret = stristr($message, "AWS was not able to validate the provided access credentials") ||
               stristr($message, "You are not authorized to perform this operation") ||
               stristr($message, "Unable to sign AWS API request. Please, check your X.509") ||
               stristr($message, "Invalid cloud credentials") ||
               stristr($message, "missing required param: 'project'") ||
               stristr($message, "Unauthorized") ||
               stristr($message, "Username or api key is invalid") ||
               strstr($message, "The request you have made requires authentication");

        return $ret;
    }

    /**
     * Returns last error message
     * @return string Returns last error message
     */
    public function getLastErrorMessage()
    {
        $message = null;
        if ($this->lastErrorMessage instanceof EnvironmentProperty) {
            $message = $this->lastErrorMessage->value;
        }

        return $message;
    }
}