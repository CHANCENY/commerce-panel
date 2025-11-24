<?php

namespace Simp\Commerce\conversion;
use DateMalformedStringException;
use PDO;
use RuntimeException;
use Simp\Commerce\connection\Connection;

class Conversion
{
    protected string $baseCurrency;
    protected string $apiKey;
    protected string $apiUrl;

    /**
     * Initializes the Conversion class with the necessary configuration.
     */
    public function __construct()
    {
        $this->baseCurrency = CURRENCY_SET;
        $this->apiKey = $_ENV['EX_API_KEY'];
        $this->apiUrl = $_ENV['EX_BASE_URL'];

        // Validate properties
        if (empty($this->baseCurrency) || empty($this->apiKey) || empty($this->apiUrl)) {
            throw new \InvalidArgumentException('Missing API configuration.');
        }
    }

    /**
     * Updates currency conversion rates in the database by fetching data from external API.
     * It checks if an update is required based on the last update's timestamp.
     * If an update is required, it fetches the latest conversion rates for all defined currencies
     * and updates the database accordingly.
     *
     * @param Connection $connection The database connection object used to execute queries.
     *
     * @return void
     *
     * @throws RuntimeException|DateMalformedStringException If fetching conversion rates fails or invalid data is returned.
     */
    public function updateRates(Connection $connection): void
    {

        $now = new \DateTime();
        $nowTimeStamp = $now->getTimestamp();
        $nextUpdateTimeStamp = $now->modify($_ENV['CONVERSION_RATE_UPDATE'])->getTimestamp();

        $selectLast = "SELECT * FROM `commerce_currency_conversion_rates` LIMIT 1";
        $statement = $connection->connect()->prepare($selectLast);
        $statement->execute();
        $last = $statement->fetch();

        // check if next_update is in the past of $nowTimeStamp or if next_update is null
        if (!empty($last)) {

            if ($last->next_update > $nowTimeStamp) {
                return;
            }

        }

        foreach (CURRENCY_LIST as $currency) {

            $endpoint = "{$this->apiUrl}/{$this->apiKey}/latest/{$currency['code']}";
            $conversionRatings = file_get_contents($endpoint);

            if ($conversionRatings === false) {
                throw new RuntimeException('Failed to fetch conversion rates.');
            }

            $conversionRatings = json_decode($conversionRatings, true);

            if (empty($conversionRatings['conversion_rates'])) {
                throw new RuntimeException('Invalid conversion rates data.');
            }

            $delete = "DELETE FROM `commerce_currency_conversion_rates` WHERE `code` = :code";
            $statement = $connection->connect()->prepare($delete);
            $statement->bindValue(':code', $currency['code']);
            $statement->execute();

            $insert = "INSERT INTO `commerce_currency_conversion_rates` (`code`, `rate_data`, `last_update`, `next_update`) VALUES (:code, :rate, :last, :next)";
            $statement = $connection->connect()->prepare($insert);

            $json = json_encode($conversionRatings['conversion_rates']);
            $statement->bindValue(':code', $currency['code']);
            $statement->bindValue(':rate', $json);
            $statement->bindValue(':last', $nowTimeStamp);
            $statement->bindValue(':next', $nextUpdateTimeStamp);
            $statement->execute();
        }

    }

    /**
     * Get rate value
     * @param string $currencyCode
     * @param string|null $baseCurrency
     * @return float|int
     */
    public function getConversionRate(string $currencyCode, ?string $baseCurrency = null): float|int
    {
        $baseCurrency = is_null($baseCurrency) ? CURRENCY_SET : $baseCurrency;

        if ($baseCurrency === $currencyCode) {
            return 1;
        }

        // Check the length of $currentCode if is actual currency code
        if (strlen($currencyCode) > 3) {
            throw new RuntimeException('Invalid currency code.');
        }

        $connection = DB_CONNECTION;

        $selectRateAmount = "select code, JSON_EXTRACT(rate_data, '$.$currencyCode') as rate from `commerce_currency_conversion_rates` WHERE code = :code";
        $statement = $connection->connect()->prepare($selectRateAmount);
        $statement->bindValue(':code', $baseCurrency);
        $statement->execute();

        $rate = $statement->fetch();

        if (empty($rate)) {
            return 1;
        }

        return (float) $rate->rate;
    }

    /**
     * Conversion object
     * @param string $currencyCode
     * @param string|null $baseCurrency
     * @return mixed
     */
    public function getConversionObject(string $currencyCode, ?string $baseCurrency = null): mixed
    {
        $baseCurrency = is_null($baseCurrency) ? CURRENCY_SET : $baseCurrency;
        $selectRateAmount = "select code, JSON_EXTRACT(rate_data, '$.$currencyCode') as rate from `commerce_currency_conversion_rates` WHERE code = :code";
        $statement = DB_CONNECTION->connect()->prepare($selectRateAmount);
        $statement->bindValue(':code', $baseCurrency);
        $statement->execute();
        return $statement->fetch();
    }

    /**
     * Get list of rates on currency.
     * @param string $currencyCode
     * @return array|bool
     */
    public function getConversion(string $currencyCode): array|bool
    {
        $select = "SELECT * FROM `commerce_currency_conversion_rates` WHERE `code` = :code";
        $statement = DB_CONNECTION->connect()->prepare($select);
        $statement->bindValue(':code', $currencyCode);
        $statement->execute();
        $conversion = $statement->fetch();

        if (empty($conversion)) {
            return [];
        }

        return json_decode($conversion['rate_data'], true);
    }

    public function getAllRatings()
    {
        $select = "SELECT * FROM `commerce_currency_conversion_rates`";
        $statement = DB_CONNECTION->connect()->prepare($select);
        $statement->execute();
        $data = $statement->fetchAll(PDO::FETCH_ASSOC);
        foreach ($data as $key => $value) {
            $data[$key]['rate_data'] = json_decode($value['rate_data'], true);
        }
        return $data;
    }
}