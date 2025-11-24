<?php


/**
 * Convert amount to given currency.
 * @param int|float $amount
 * @param string $currencyCode
 * @param string|null $baseCurrency
 * @return float|int
 */
function convert(int|float $amount, string $currencyCode, ?string $baseCurrency = null): float|int
{
    $rateAmount = CURRENCY_CONVERTOR->getConversionRate($currencyCode, $baseCurrency);
    return $rateAmount * $amount;
}

/**
 * @param string $currencyCode
 * @param string|null $baseCurrency
 * @return mixed
 */
function conversion_data(string $currencyCode, ?string $baseCurrency = null): mixed
{
    return CURRENCY_CONVERTOR->getConversionObject($currencyCode, $baseCurrency);
}

/**
 * List all rates.
 * @param string $currencyCode
 * @return bool|array
 */
function get_conversions(string $currencyCode): bool|array
{
    return CURRENCY_CONVERTOR->getConversion($currencyCode);
}