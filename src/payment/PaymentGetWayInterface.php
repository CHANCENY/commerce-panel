<?php

namespace Simp\Commerce\payment;

interface PaymentGetWayInterface
{
    /**
     * Retrieves the payment form as a string.
     *
     * @return string The payment form.
     */
    public function getPaymentForm(array $options = []): string;

    /**
     * Retrieves the gateway configuration details.
     *
     * @return array The gateway configuration.
     */
    public function getGateWayConfiguration(): array;

    /**
     * Retrieves the name of the gateway.
     * @return string
     */
    public function getGatewayName(): string;

    /**
     * Retrieves the logos of the gateway.
     * @return array
     */
    public function getGatewayLogos(): array;

    /**
     * Retrieves the description of the gateway.
     * @return string
     */
    public function getGatewayDescription(): string;

    /**
     * Retrieves the unique identifier for the gateway.
     * @return string
     */
    public function getGatewayId(): string;

    /**
     * Checks whether the feature or functionality is enabled.
     *
     * @return bool True if enabled, false otherwise.
     */
    public function isEnabled(): bool;

    /**
     * Processes a payment using the provided payment data.
     *
     * @param array $paymentData An associative array containing payment details.
     * @return bool True, if the payment was processed successfully, false otherwise.
     */
    public function processPayment(array $paymentData): bool;

    /**
     * Validates data based on the provided key and an optional callback function.
     *
     * @param array $data The data to validate.
     * @param string $key The key used for validation.
     * @param callable|null $callback An optional callback function to apply custom validation logic.
     */
    public static function dataValidation(array $data, string $key, ?callable $callback = null);

    /**
     * Retrieves the value associated with the provided key. If the key does not exist, returns the default value.
     *
     * @param array $data The associative array to search within.
     * @param string $key The key to look up.
     * @param mixed $default The default value to return if the key is not found. Defaults to null.
     * @return mixed The value associated with the key, or the default value.
     */
    public static function getKeyValue(array $data, string $key, $default = null);

}