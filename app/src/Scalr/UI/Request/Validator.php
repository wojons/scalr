<?php

namespace Scalr\UI\Request;
use Scalr\Model\Entity\Account\User;

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
    const NOEMPTY       = 'notEmpty';
    const URL           = 'url';
    const FLOATNUM      = 'float';
    const INTEGERNUM    = 'integer';
    const EMAIL         = "email";
    const PASSWORD      = "password";

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
        $resultValidator = call_user_func(array(get_class(), $method), $value, $options);
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
     * Get errors
     *
     * @param bool $flatten optional Whether to flatten error messages
     * @return array
     */
    public function getErrors($flatten = false)
    {
        if ($flatten === false) {
            return ['errors' => $this->errors];
        } else {
            $errors = [];
            foreach ($this->errors as $name => $err) {
                foreach ($err as $error) {
                    if (array_key_exists($name, $errors)) {
                        $errors[$name] .= "\n" . $error;
                    } else {
                        $errors[$name] = $error;
                    }
                }
            }

            return $errors;
        }
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

    /**
     * Checks if the value is not empty
     *
     * @param     string     $value    The url
     * @param     array      $options  optional   Options
     * @return    bool|string Returns true if value is valid or error message if failures
     */
    static function validateNotEmpty($value, $options = null)
    {
        return !empty($value) ?: 'This value should be provided.';
    }

    /**
     * Checks if the value is valid HTTP(S) URL
     *
     * @param     string     $value    The url
     * @param     array      $options  optional   Options
     * @return    bool|string Returns true if value is valid or error message if failures
     */
    static function validateUrl($value, $options = null)
    {
        if (($url = filter_var($value, FILTER_SANITIZE_URL)) === false) {
            return "This value should be valid URL";
        }

        //We should forbid local resources!
        return (bool)preg_match('/^https?/i', parse_url($url, PHP_URL_SCHEME)) ?: "Invalid URL scheme";
    }

    /**
     * Checks if the value is valid integer
     *
     * @param     mixed     $value
     * @param     array      $options  optional   Options
     * @return    bool|string Returns true if value is valid or error message if failures
     */
    static function validateInteger($value, $options = null)
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false ?: 'This value should be valid integer';
    }

    /**
     * Checks if the value is valid float
     *
     * @param     mixed     $value
     * @param     array      $options  optional   Options
     * @return    bool|string Returns true if value is valid or error message if failures
     */
    static function validateFloat($value, $options = null)
    {
        return filter_var($value, FILTER_VALIDATE_FLOAT) !== false ?: 'This value should be valid number';
    }

    /**
     * Validates password strength
     *
     * @param  string $value
     * @param  array  $options  optional   Options
     * @return bool|string Returns TRUE in case of success or an error message otherwise
     */
    public static function validatePassword($value, $options = [])
    {
        $len = is_array($options) && in_array('admin', $options) ? User::PASSWORD_ADMIN_LENGTH : User::PASSWORD_USER_LENGTH;

        if (strlen($value) < $len) {
            return "The password must be at least {$len} chars long.";
        }

        $sets = [
            'lowercase' => 'abcdefghjkmnpqrstuvwxyz',
            'uppercase' => 'ABCDEFGHJKMNPQRSTUVWXYZ',
            'digit' => '1234567890',
            'special symbols' => '!@#$%&*?',
        ];
        $groups = [];
        $valueArray = str_split($value);

        foreach ($sets as $setName => $set) {
            if (empty(array_intersect($valueArray, str_split($set)))) {
                $groups[] = $setName;
            }
        }

        if (!empty($groups)) {
            return "Password doesn't contain any characters from the following group(s): " . join(', ', $groups);
        }

        return true;
    }

    /**
     * Validates email address
     *
     * @param  string $value
     * @param  array  $options  optional   Options
     * @return bool|string Returns TRUE in case of success or an error message otherwise
     */
    public function validateEmail($value, $options = null)
    {
        if (empty($value)) {
            return "Email is required";
        } elseif (filter_var($value, FILTER_VALIDATE_EMAIL) !== false) {
            return true;
        }

        return "Email address is invalid";
    }
}
