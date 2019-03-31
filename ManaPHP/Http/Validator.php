<?php
namespace ManaPHP\Http;

use ManaPHP\Component;
use ManaPHP\Http\Validator\Exception as FilterException;

/**
 * Class ManaPHP\Http\Validator
 *
 * @package validator
 *
 * @property-read \ManaPHP\Security\SecintInterface       $secint
 * @property-read \ManaPHP\Security\HtmlPurifierInterface $htmlPurifier
 */
class Validator extends Component implements ValidatorInterface
{
    /**
     * @var array
     */
    protected $_validators = [];

    /**
     * @var array|string
     */
    protected $_messages = 'en';

    /**
     * @var array
     */
    protected $_attributes = [];

    /**
     * @var string
     */
    protected $_defaultMessage = 'The :attribute format is invalid.';

    /**
     * @var bool
     */
    protected $_xssByReplace = true;

    /**
     * Filter constructor.
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        foreach (get_class_methods($this) as $method) {
            if (strpos($method, '_validate_') === 0) {
                $this->_validators[substr($method, 10)] = [$this, $method];
            }
        }

        if (isset($options['messages'])) {
            $this->_messages = $options['messages'];
        }

        if (isset($options['defaultMessage'])) {
            $this->_defaultMessage = $options['defaultMessage'];
        }

        if (isset($options['xssByReplace'])) {
            $this->_xssByReplace = $options['xssByReplace'];
        }
    }

    /**
     * @param string                $attribute
     * @param array                 $rules
     * @param string|int|bool|array $value
     *
     * @return mixed
     * @throws \ManaPHP\Http\Validator\Exception
     */
    public function validate($attribute, $rules, $value)
    {
        if (is_int($value)) {
            $value = (string)$value;
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif ($value === null) {
            $value = '';
        }

        foreach ($rules as $k => $v) {
            if (is_int($k)) {
                $value = $this->_validate($attribute, $v, [], $value);
            } else {
                $value = $this->_validate($attribute, $k, is_array($v) ? $v : explode('-', $v), $value);
            }
        }

        return $value;
    }

    /**
     * @param string $attribute
     * @param string $rule
     * @param array  $parameters
     * @param mixed  $value
     *
     * @return mixed
     * @throws \ManaPHP\Http\Validator\Exception
     */
    protected function _validate($attribute, $rule, $parameters, $value)
    {
        $srcValue = $value;

        if (isset($this->_validators[$rule])) {
            /** @noinspection VariableFunctionsUsageInspection */
            $value = call_user_func_array($this->_validators[$rule], [$value, $parameters]);
        } elseif (function_exists($rule)) {
            $value = call_user_func_array($rule, array_merge([$value], $parameters));
        } else {
            throw new FilterException(['`:name` rule is not be recognized', 'name' => $rule]);
        }

        if ($value === null) {
            if (is_string($this->_messages)) {
                if (strpos($this->_messages, '.') === false) {
                    $file = '@manaphp/Http/Validator/Messages/' . $this->_messages . '.php';
                } else {
                    $file = $this->_messages;
                }

                if (!$this->filesystem->fileExists($file)) {
                    throw new FilterException(['`:file` rule message template file is not exists', 'file' => $file]);
                }

                /** @noinspection PhpIncludeInspection */
                $this->_messages = require $this->alias->resolve($file);
            }

            if (isset($this->_messages[$rule])) {
                $message = [$this->_messages[$rule]];
            } else {
                $message = [$this->_defaultMessage];
            }

            $message['rule'] = $rule;
            $message['attribute'] = isset($this->_attributes[$attribute]) ? $this->_attributes[$attribute] : $attribute;
            $message['value'] = $srcValue;
            foreach ($parameters as $k => $parameter) {
                $message['parameters[' . $k . ']'] = $parameter;
            }

            throw new FilterException($message);
        }

        return $value;
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    protected function _validate_required($value)
    {
        return $value === '' ? null : $value;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function _validate_xss($value)
    {
        if ($value === '') {
            return $value;
        } else {
            return $this->htmlPurifier->purify($value);
        }
    }

    /**
     * @param string $value
     *
     * @return bool|null
     */
    protected function _validate_bool($value)
    {
        $trueValues = ['1', 'true'];
        $falseValues = ['0', 'false'];

        if (in_array($value, $trueValues, true)) {
            return true;
        } elseif (in_array($value, $falseValues, true)) {
            return false;
        } else {
            return null;
        }
    }

    /**
     * @param string|int $value
     *
     * @return int|null
     */
    protected function _validate_int($value)
    {
        if (is_int($value)) {
            return $value;
        }

        return preg_match('#^[+-]?\d+$#', $value) ? (int)$value : null;
    }

    /**
     * @param string|float|int $value
     *
     * @return float|null
     */
    protected function _validate_float($value)
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_FLOAT) !== false
            && preg_match('#^[+-]?[\d\.]+$#', $value) === 1
        ) {
            return (float)$value;
        } else {
            return null;
        }
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return int|null
     */
    protected function _validate_date($value, $parameters)
    {
        $timestamp = is_numeric($value) ? $value : strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        if (isset($parameters[0])) {
            if ($parameters[0] === 'start') {
                return date('Y-m-d', $timestamp) . ' 00:00:00';
            } elseif ($parameters[0] === 'end') {
                return date('Y-m-d', $timestamp) . ' 23:59:59';
            } else {
                return date($parameters[0], $timestamp);
            }
        } else {
            return $value;
        }
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return int|null
     */
    protected function _validate_timestamp($value, $parameters)
    {
        $timestamp = is_numeric($value) ? (int)$value : strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        if (isset($parameters[0])) {
            if ($parameters[0] === 'start') {
                /** @noinspection SummerTimeUnsafeTimeManipulationInspection */
                return (int)($timestamp / 86400) * 86400;
            } elseif ($parameters[0] === 'end') {
                /** @noinspection SummerTimeUnsafeTimeManipulationInspection */
                return (int)($timestamp / 86400) * 86400 + 86399;
            } else {
                return strtotime(date($parameters[0], $timestamp));
            }
        }

        return $timestamp;
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return int|null
     */
    protected function _validate_range($value, $parameters)
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $value = $this->_validate_float($value);

        return $value === null || $value < $parameters[0] || $value > $parameters[1] ? null : $value;
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return float|null
     */
    protected function _validate_min($value, $parameters)
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $value = $this->_validate_float($value);

        return $value === null || $value < $parameters[0] ? null : $value;
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return float|null
     */
    protected function _validate_max($value, $parameters)
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $value = $this->_validate_float($value);

        return $value === null || $value > $parameters[0] ? null : $value;
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return string|null
     */
    protected function _validate_minLength($value, $parameters)
    {
        return $this->_strlen($value) >= $parameters[0] ? $value : null;
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return string|null
     */
    protected function _validate_maxLength($value, $parameters)
    {
        return $this->_strlen($value) <= $parameters[0] ? $value : null;
    }

    /**
     * @param string $value
     *
     * @return int
     */
    protected function _strlen($value)
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return string|null
     */
    protected function _validate_length($value, $parameters)
    {
        $length = $this->_strlen($value);
        if (count($parameters) === 1) {
            $parameters = explode('-', $parameters[0]);
        }
        return $length >= $parameters[0] && $length <= $parameters[1] ? $value : null;
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return string|null
     */
    protected function _validate_equal($value, $parameters)
    {
        /** @noinspection TypeUnsafeComparisonInspection */
        return $value == $parameters[0] ? $value : null;
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return string|null
     */
    protected function _validate_regex($value, $parameters)
    {
        return ($value === '' || preg_match($parameters[0], $value)) ? $value : null;
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    protected function _validate_alpha($value)
    {
        /** @noinspection NotOptimalRegularExpressionsInspection */
        return preg_match('#^[a-zA-Z]*$#', $value) ? $value : null;
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    protected function _validate_digit($value)
    {
        /** @noinspection NotOptimalRegularExpressionsInspection */
        return preg_match('#^\d*$#', $value) ? $value : null;
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    protected function _validate_alnum($value)
    {
        /** @noinspection NotOptimalRegularExpressionsInspection */
        return preg_match('#^[a-zA-Z0-9]*$#', $value) ? $value : null;
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function _validate_lower($value)
    {
        return strtolower($value);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function _validate_upper($value)
    {
        return strtoupper($value);
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    protected function _validate_account($value)
    {
        return preg_match('#^[a-z][a-z_\d]{1,15}$#', $value) ? strtolower($value) : null;
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    protected function _validate_password($value)
    {
        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    protected function _validate_email($value)
    {
        $value = trim($value);

        return ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false) ? strtolower($value) : null;
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    protected function _validate_url($value)
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (filter_var($value, FILTER_VALIDATE_URL) !== false) {
            $parts = explode('://', $value, 2);
            $scheme = strtolower($parts[0]);
            $path = $parts[1];
            if ($scheme !== 'http' && $scheme !== 'https') {
                return null;
            } else {
                return $scheme . '://' . $path;
            }
        } else {
            return null;
        }
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    protected function _validate_mobile($value)
    {
        $value = trim($value);

        return ($value === '' || preg_match('#^1[3-8]\d{9}$#', $value)) ? $value : null;
    }

    /**
     * @param string $value
     *
     * @return string|null
     */
    protected function _validate_ip($value)
    {
        $value = trim($value);

        return ($value === '' || filter_var($value, FILTER_VALIDATE_IP) !== false) ? $value : null;
    }

    /**
     * @param string $value
     * @param array  $parameters
     *
     * @return int|null
     */
    protected function _validate_secint($value, $parameters)
    {
        $v = $this->secint->decode($value, isset($parameters[0]) ? $parameters[0] : '');
        if ($v === false) {
            return null;
        } else {
            return $v;
        }
    }

    /**
     * @param string $value
     *
     * @return string
     */
    public function _validate_escape($value)
    {
        return htmlspecialchars($value);
    }
}