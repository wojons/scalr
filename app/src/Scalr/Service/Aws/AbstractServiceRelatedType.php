<?php
namespace Scalr\Service\Aws;

/**
 * Amazon CloudWatch web service interface
 *
 * @author    Vitaliy Demidov   <zend@i.ua>
 * @since     24.10.2012
 */
abstract class AbstractServiceRelatedType
{
	/**
	 * Holds an services instances.
	 *
	 * @var array
	 */
	private $services;

	/**
	 * Gets the list of supported service interfaces.
	 *
	 * This method is supposed to be overridden.
	 *
	 * @return array Returns the list of supported service interfaces.
	 *               Array should look like array(Aws::SERVICE_INTERFACE_ELB, ... )
	 */
	abstract public function getServiceNames();

	/**
	 * Gets the service interface instance
	 *
	 * @param  string   $name   Service Name
	 * @throws \RuntimeException
	 */
	public function __get($serviceName)
	{
		if (in_array($serviceName, $this->getServiceNames())) {
			return isset($this->services[$serviceName]) ? $this->services[$serviceName] : null;
		}
		throw new \RuntimeException(sprintf('Unknown property %s for the class %s', $serviceName, get_class($this)));
	}

	/**
	 * Gets or sets an service interface instance
	 *
	 * @param   string    $name
	 * @param   mixed     $arguments
	 * @throws  \InvalidArgumentException
	 * @throws  \RuntimeException
	 */
	public function __call($name, $arguments)
	{
		if (preg_match('/^(get|set)(.+)$/', $name, $token)) {
			$serviceName = lcfirst($token[2]);
			if (in_array($serviceName, $this->getServiceNames())) {
				if ($token[1] == 'get') {
					return isset($this->services[$serviceName]) ? $this->services[$serviceName] : null;
				} else {
					if (count($arguments) > 1) {
						throw new \InvalidArgumentException(sprintf('One argument is accepted here.'));
					}
					//Set is expected to be here
					$this->services[$serviceName] = $arguments[0];
					return $this;
				}
			}
		}
		throw new \RuntimeException(sprintf('Unknown method %s for the class %s', $name, get_class($this)));
	}
}