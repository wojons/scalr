<?php

namespace Scalr\UI\Request;

/**
 * Validator
 *
 *
 *
 * @author   Igor Vodiasov <invar@scalr.com>
 * @since    5.0 (30.06.2014)
 */

class Validator
{
    const NOEMPTY = 'notEmpty';

    protected $errors = [];

    /**
     * @param string $value
     * @param string $name
     * @param string $validator
     * @param array $options optional validator's options
     * @param string $errorMessage optional
     * @return bool
     */
    public function validate($value, $name, $validator, $options = [], $errorMessage = NULL)
    {
        $method = "validate" . ucfirst($validator);
        $resultValidator = $this->{$method}($value, $options);
        if ($resultValidator !== true) {
            $this->addError($name, $errorMessage ? $errorMessage : $resultValidator);
            return false;
        }
        return true;
    }

    /**
     * @param string|array $name
     * @param string|array $error
     */
    public function addError($name, $error)
    {
        if (is_array($name)) {
            foreach ($name as $n) {
                $this->addError($n, $error);
            }

            return;
        }

        if (! isset($this->errors[$name]))
            $this->errors[$name] = [];

        if (is_array($error))
            $this->errors[$name] = array_merge($this->errors[$name], $error);
        else
            $this->errors[$name][] = $error;
    }

    /**
     * @param bool $conj
     * @param string|array $name
     * @param string|array $error
     */
    public function addErrorIf($conj, $name, $error)
    {
        if ($conj)
            $this->addError($name, $error);
    }

    /**
     * @param \Scalr_UI_Response $response optional
     * @return bool
     */
    public function isValid(\Scalr_UI_Response $response = NULL)
    {
        $result = !count($this->errors);
        if (! $result && $response)
            $this->markResponseInvalid($response);

        return $result;
    }

    /**
     * @param \Scalr_UI_Response $response
     */
    public function markResponseInvalid(\Scalr_UI_Response $response)
    {
        $response->failure();
        $response->data($this->getErrors());
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return array('errors' => $this->errors);
    }

    /**
     * @return string
     */
    public function getErrorsMessage()
    {
        $message = '';
        foreach ($this->errors as $key => $value) {
            $message .= "Field '{$key}' has following errors: <ul>";
            foreach ($value as $error)
                $message .= "<li>{$error}</li>";
            $message .= "</ul>";
        }

        return $message;
    }

    /*
     * Validator functions
     */
    public function validateNotEmpty($value, $options = null)
    {
        return !empty($value) ?: 'This value should be provided.';
    }
}
