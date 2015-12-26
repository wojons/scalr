<?php
namespace Scalr\Service\Aws;

use DateTime;
use DateTimeZone;
use Scalr\Service\Aws\Client\ClientInterface;
use SimpleXMLElement;

/**
 * AbstractApi
 *
 * @author    Vitaliy Demidov   <vitaliy@scalr.com>
 * @since     20.12.2012
 */
abstract class AbstractApi
{
    /**
     * Client
     * This property is explicitly used as reflection property
     * @var ClientInterface
     */
    protected $client;

    /**
     * Gets AWS Client
     *
     * @return  ClientInterface Retuns AWS Client
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Sets AWS Client
     *
     * @param   ClientInterface  $client  Client object
     * @return  AbstractApi
     */
    public function setClient(ClientInterface $client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     * Checks an existence of the SimpleXMLElement element.
     *
     * @param   \SimpleXMLElement $element Simple XML Element to check
     * @return  bool Returns true if element does exist.
     */
    public function exist($element)
    {
        return isset($element) && current($element) !== false;
    }

    /**
     * Extracts value from SimpleXMLElement
     *
     * @param   SimpleXMLElement    $element    Simple XML Element from is retrieved value
     * @param   string              $type       Value type
     * @param   mixed               $default    Default value returned if element is empty
     *
     * @return  mixed   Value of Simple XML Element or default, if element is empty
     */
    public function get(SimpleXMLElement $element, $type = 'string', $default = null)
    {
        if (isset($element) && current($element) !== false) {
            switch ($type) {
                case 'string':
                    return (string) $element;

                case 'int':
                    return (int) $element;

                case 'bool':
                    return $element == 'true';

                case 'DateTime':
                    return new DateTime((string) $element, new DateTimeZone('UTC'));
            }
        }

        return $default;
    }

    /**
     * Fills data from SimpleXMLElement according with rules
     *
     * @param   AbstractDataType    $dataType   Data store to fill
     * @param   SimpleXMLElement    $xml        Simple XML Element containing values
     * @param   array               $rules      Data conversion rules
     */
    public function fill(AbstractDataType $dataType, SimpleXMLElement $xml, array $rules)
    {
        foreach ($rules as $name => $rule) {
            if (is_numeric($name)) {
                $dataType->{$rule} = $this->get($xml->{ucfirst($rule)});
            } else {
                if (is_array($rule)) {
                    $element = isset($rule['element']) ? $rule['element'] : ucfirst($name);
                    $type = isset($rule['type']) ? $rule['type'] : 'string';
                } else {
                    $element = ucfirst($name);
                    $type = $rule;
                }

                if ($type[0] == '_' && method_exists($this, $type)) {
                    $dataType->{$name} = $this->{$type}($xml->{$element});
                } else {
                    $dataType->{$name} = $this->get($xml->{$element}, $type);
                }
            }
        }
    }

    /**
     * Escapes string to pass it over http request
     *
     * @param   string   $str
     * @return  string
     */
    protected static function escape($str)
    {
        return rawurlencode($str);
    }
}