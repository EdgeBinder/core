<?php

declare(strict_types=1);

namespace EdgeBinder\Exception;

/**
 * Exception thrown when adapter operations fail.
 * 
 * This exception provides specific factory methods for common adapter-related
 * error scenarios, making error handling more consistent and informative.
 * 
 * Example usage:
 * ```php
 * // When adapter type is not found
 * throw AdapterException::factoryNotFound('unknown_adapter', ['weaviate', 'janus']);
 * 
 * // When adapter creation fails
 * throw AdapterException::creationFailed('janus', 'Connection timeout', $previousException);
 * 
 * // When trying to register duplicate adapter
 * throw AdapterException::alreadyRegistered('janus');
 * ```
 */
class AdapterException extends EdgeBinderException
{
    /**
     * Create exception for when an adapter factory is not found.
     * 
     * @param string   $adapterType     The requested adapter type that was not found
     * @param string[] $availableTypes  Array of available adapter types for helpful error message
     * 
     * @return self Exception instance with descriptive message
     */
    public static function factoryNotFound(string $adapterType, array $availableTypes = []): self
    {
        $message = "Adapter factory for type '{$adapterType}' not found.";
        
        if (!empty($availableTypes)) {
            $message .= " Available types: " . implode(', ', $availableTypes);
        } else {
            $message .= " No adapters are currently registered.";
        }
        
        return new self($message);
    }
    
    /**
     * Create exception for when adapter creation fails.
     * 
     * @param string          $adapterType The adapter type that failed to be created
     * @param string          $reason      Human-readable reason for the failure
     * @param \Throwable|null $previous    Previous exception that caused this failure
     * 
     * @return self Exception instance with descriptive message and context
     */
    public static function creationFailed(string $adapterType, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            "Failed to create adapter of type '{$adapterType}': {$reason}",
            0,
            $previous
        );
    }
    
    /**
     * Create exception for when trying to register an adapter type that already exists.
     * 
     * @param string $adapterType The adapter type that is already registered
     * 
     * @return self Exception instance with descriptive message
     */
    public static function alreadyRegistered(string $adapterType): self
    {
        return new self("Adapter type '{$adapterType}' is already registered");
    }
    
    /**
     * Create exception for invalid configuration.
     * 
     * @param string $adapterType The adapter type with invalid configuration
     * @param string $reason      Specific reason why configuration is invalid
     * 
     * @return self Exception instance with descriptive message
     */
    public static function invalidConfiguration(string $adapterType, string $reason): self
    {
        return new self("Invalid configuration for adapter type '{$adapterType}': {$reason}");
    }
    
    /**
     * Create exception for missing required configuration keys.
     * 
     * @param string   $adapterType  The adapter type with missing configuration
     * @param string[] $missingKeys  Array of missing configuration keys
     * 
     * @return self Exception instance with descriptive message
     */
    public static function missingConfiguration(string $adapterType, array $missingKeys): self
    {
        $keys = implode(', ', $missingKeys);
        return new self("Missing required configuration for adapter type '{$adapterType}': {$keys}");
    }
}
