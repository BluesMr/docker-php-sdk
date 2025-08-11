<?php

declare(strict_types=1);

namespace Docker\API\Validator;

use Docker\API\Exception\InvalidParameterException;

/**
 * Parameter validation utility class
 */
class ParameterValidator
{
    /**
     * Validate that a parameter is not empty
     *
     * @param mixed $value The value to validate
     * @param string $parameterName The parameter name for error messages
     * @throws InvalidParameterException If the parameter is empty
     */
    public static function validateNotEmpty($value, string $parameterName): void
    {
        if (empty($value)) {
            throw new InvalidParameterException("Parameter '{$parameterName}' cannot be empty");
        }
    }

    /**
     * Validate that a string parameter is not empty
     *
     * @param string|null $value The value to validate
     * @param string $parameterName The parameter name for error messages
     * @throws InvalidParameterException If the parameter is empty or not a string
     */
    public static function validateString(?string $value, string $parameterName): void
    {
        if ($value === null || $value === '') {
            throw new InvalidParameterException("Parameter '{$parameterName}' must be a non-empty string");
        }
    }

    /**
     * Validate that an array parameter is valid
     *
     * @param array|null $value The value to validate
     * @param string $parameterName The parameter name for error messages
     * @param bool $allowEmpty Whether to allow empty arrays
     * @throws InvalidParameterException If the parameter is invalid
     */
    public static function validateArray(?array $value, string $parameterName, bool $allowEmpty = true): void
    {
        if ($value === null) {
            throw new InvalidParameterException("Parameter '{$parameterName}' must be an array");
        }

        if (!$allowEmpty && empty($value)) {
            throw new InvalidParameterException("Parameter '{$parameterName}' cannot be an empty array");
        }
    }

    /**
     * Validate that an integer parameter is within valid range
     *
     * @param int|null $value The value to validate
     * @param string $parameterName The parameter name for error messages
     * @param int|null $min Minimum allowed value
     * @param int|null $max Maximum allowed value
     * @throws InvalidParameterException If the parameter is out of range
     */
    public static function validateInteger(?int $value, string $parameterName, ?int $min = null, ?int $max = null): void
    {
        if ($value === null) {
            return;
        }

        if ($min !== null && $value < $min) {
            throw new InvalidParameterException("Parameter '{$parameterName}' must be at least {$min}");
        }

        if ($max !== null && $value > $max) {
            throw new InvalidParameterException("Parameter '{$parameterName}' must be at most {$max}");
        }
    }

    /**
     * Validate that a parameter is one of allowed values
     *
     * @param mixed $value The value to validate
     * @param array $allowedValues Array of allowed values
     * @param string $parameterName The parameter name for error messages
     * @throws InvalidParameterException If the parameter is not in allowed values
     */
    public static function validateEnum($value, array $allowedValues, string $parameterName): void
    {
        if ($value !== null && !in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', $allowedValues);
            throw new InvalidParameterException("Parameter '{$parameterName}' must be one of: {$allowed}");
        }
    }

    /**
     * Validate container or image ID/name format
     *
     * @param string $id The ID or name to validate
     * @param string $parameterName The parameter name for error messages
     * @throws InvalidParameterException If the ID format is invalid
     */
    public static function validateId(string $id, string $parameterName): void
    {
        self::validateString($id, $parameterName);

        // Docker ID can be short (12 chars) or full (64 chars) hex, or a name
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/', $id) && 
            !preg_match('/^[a-f0-9]{12,64}$/', $id)) {
            throw new InvalidParameterException("Parameter '{$parameterName}' must be a valid Docker ID or name");
        }
    }

    /**
     * Validate JSON string
     *
     * @param string $json The JSON string to validate
     * @param string $parameterName The parameter name for error messages
     * @throws InvalidParameterException If the JSON is invalid
     */
    public static function validateJson(string $json, string $parameterName): void
    {
        json_decode($json);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidParameterException("Parameter '{$parameterName}' must be valid JSON: " . json_last_error_msg());
        }
    }

    /**
     * Validate port format (e.g., "80/tcp", "443/udp")
     *
     * @param string $port The port string to validate
     * @param string $parameterName The parameter name for error messages
     * @throws InvalidParameterException If the port format is invalid
     */
    public static function validatePort(string $port, string $parameterName): void
    {
        if (!preg_match('/^\d+\/(tcp|udp|sctp)$/', $port)) {
            throw new InvalidParameterException("Parameter '{$parameterName}' must be in format 'port/protocol' (e.g., '80/tcp')");
        }
    }

    /**
     * Validate signal name
     *
     * @param string $signal The signal name to validate
     * @param string $parameterName The parameter name for error messages
     * @throws InvalidParameterException If the signal is invalid
     */
    public static function validateSignal(string $signal, string $parameterName): void
    {
        $validSignals = [
            'SIGABRT', 'SIGALRM', 'SIGBUS', 'SIGCHLD', 'SIGCONT', 'SIGFPE', 'SIGHUP',
            'SIGILL', 'SIGINT', 'SIGIO', 'SIGIOT', 'SIGKILL', 'SIGPIPE', 'SIGPOLL',
            'SIGPROF', 'SIGPWR', 'SIGQUIT', 'SIGSEGV', 'SIGSTKFLT', 'SIGSTOP',
            'SIGSYS', 'SIGTERM', 'SIGTRAP', 'SIGTSTP', 'SIGTTIN', 'SIGTTOU',
            'SIGURG', 'SIGUSR1', 'SIGUSR2', 'SIGVTALRM', 'SIGWINCH', 'SIGXCPU', 'SIGXFSZ'
        ];

        if (!in_array($signal, $validSignals, true) && !preg_match('/^\d+$/', $signal)) {
            throw new InvalidParameterException("Parameter '{$parameterName}' must be a valid signal name or number");
        }
    }
}