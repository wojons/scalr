<?php

namespace Scalr\Util;

/**
 * PHP Template parser
 *
 * @author   Vitaliy Demidov   <vitaliy@scalr.com>
 * @since    30.09.2013
 *
 * @method   \Scalr\Util\PhpTemplate set()
 *           set(array|string $property, mixed $value = null)
 *           Sets data to template
 */
class PhpTemplate
{
    /**
     * Template data
     *
     * @var array
     */
    private $data;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->data = array();
    }

    public function __set($property, $value)
    {
        $this->data[$property] = $value;
    }

    public function __call($function, $arguments)
    {
        $cnt = count($arguments);
        if ($function == 'set') {
            if ($cnt == 1) {
                $this->data = array_replace($this->data, $arguments[0]);
                return $this;
            } elseif ($cnt > 1) {
                $this->data[(string)$arguments[0]] = $arguments[1];
                return $this;
            }
        }
        throw new \BadMethodCallException(sprintf('Method "%s" does not exist for class "%s".', $function, get_class($this)));
    }

    /**
     * Resets all the data
     *
     * @return  PhpTemplate
     */
    public function reset()
    {
        $this->data = array();

        return $this;
    }

    /**
     * Gets parsed template
     *
     * @param   string   $template  path to the template
     * @return  string   Returns parsed data
     */
    public function parse($template)
    {
        $el = error_reporting();
        @ob_start();
        if (!is_readable($template)) {
            throw new \Scalr\Exception\ScalrException("Cannot read from %s template.", $template);
        }
        foreach ($this->data as $k => $v) {
            if ($k[0] != '_') {
                @extract(array($k => $v));
            }
        }
        error_reporting(0);
        include $template;
        $data = @ob_get_contents();
        @ob_end_clean();
        error_reporting($el);
        return $data;
    }

    public function __invoke($template)
    {
        return $this->parse($template);
    }

    /**
     * Loads template and parses it with the specified data
     *
     * @param   string    $template  Path to the template
     * @param   array     $data      optional Template data
     * @return  string    Returns parsed template
     */
    public static function load($template, array $data = null)
    {
        $tpl = new self();
        if ($data !== null) {
            $tpl->set($data);
        }
        return $tpl($template);
    }
}