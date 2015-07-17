<?php

class Scalr_Messaging_JsonSerializer {
    const SERIALIZE_BROADCAST = 'serializeBroadcast';

    private $msgClassProperties = array();

    static private $instance;

    /**
     * @return Scalr_Messaging_JsonSerializer
     */
    static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Scalr_Messaging_JsonSerializer();
        }
        return self::$instance;
    }

    function __construct () {
        $this->msgClassProperties = array_keys(get_class_vars('Scalr_Messaging_Msg'));
    }

    function serialize (Scalr_Messaging_Msg $msg, $options = array()) {
        $retval = new stdClass();
        $retval->name = $msg->getName();
        $retval->id = $msg->messageId;
        $retval->body = new stdClass();
        $retval->meta = new stdClass();

        $meta = (array)$msg->meta;
        unset($msg->meta);

        $this->walkSerialize($msg, $retval->body, 'underScope');
        $this->walkSerialize($meta, $retval->meta, 'underScope');

        return @json_encode($retval);
    }

    public function walkSerialize ($object, &$retval, $normalizationMethod) {
        foreach ($object as $k=>$v) {
            if ($v === null)
                $v = '';

            $valueType = gettype($v);
            $objectType = gettype($retval);

            $normalizedString = call_user_func(array($this, $normalizationMethod), $k);

            if (is_object($v) || is_array($v)) {
                if ($objectType == 'object') {
                    if (is_array($v))
                        $retval->{$normalizedString} = array();
                    else
                        $retval->{$normalizedString} = new stdClass();

                    call_user_func_array(array($this, 'walkSerialize'), array($v, &$retval->{$normalizedString}, $normalizationMethod));
                }
                else {
                    if (is_array($v))
                        $retval[$normalizedString] = array();
                    else
                        $retval[$normalizedString] = new stdClass();

                    call_user_func_array(array($this, 'walkSerialize'), array($v, &$retval[$normalizedString], $normalizationMethod));
                }
            } else {
                if (is_object($retval))
                   $retval->{$normalizedString} = $v;
                else {
                    if (!is_int($k))
                        $retval[$normalizedString] = $v;
                    else
                        $retval[] = $v;
                }
            }
        }
    }

    /**
     * @param string $xmlString
     * @return Scalr_Messaging_Msg
     */
    function unserialize ($jsonString) {
        $msg = @json_decode($jsonString);

        $className = Scalr_Messaging_Msg::getClassForName($msg->name);
        if (!class_exists($className))
            return null;

        $ref = new ReflectionClass($className);
        $retval = $ref->newInstance();
        $retval->messageId = "{$msg->id}";
        $retval->meta = (array)$msg->meta;

        $this->walkSerialize($msg->body, $retval, 'camelCase');

        return $retval;
    }

    public function underScope ($name) {
        
        if (preg_match("/^[A-Z]+$/", $name))
            return $name;
        
        $parts = preg_split("/[A-Z]/", $name, -1, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $ret = "";
        foreach ($parts as $part) {
            if ($part[1]) {
                $ret .= "_" . strtolower($name{$part[1]-1});
            }
            $ret .= $part[0];
        }
        return $ret;
    }

    public function camelCase ($name) {
        $parts = explode("_", $name);
        $first = array_shift($parts);
        return $first . join("", array_map("ucfirst", $parts));
    }
}