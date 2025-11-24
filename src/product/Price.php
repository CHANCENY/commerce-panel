<?php

namespace Simp\Commerce\product;

use PDO;
use Simp\Commerce\conversion\Conversion;
use Simp\Commerce\store\Store;

/**
 * Represents product pricing including base amount, discounts, and taxes.
 */
class Price
{
    protected float $base_price;
    protected float $discount = 0.0;
    protected string $currency;
    protected array $taxes = [];
    protected float $total = 0.0;
    protected int $pd_attr;

    public function __construct(int $pd_attr, float $base_price, string $currency = 'USD', float $discount = 0.0, string $store = 'default')
    {
        $this->pd_attr = $pd_attr;
        $this->base_price = $base_price;
        $this->currency = $currency;
        $this->discount = $discount;

        // Apply taxes dynamically from store
        $store = new Store($store);
        foreach ($store->getTaxes() as $name=>$tax) {
            $this->addTax($name, $tax);
        }

        $this->calculateTotal();
    }

    public function addTax(string $name, float $rate): void
    {
        $amount = ($this->base_price * $rate) / 100;
        $this->taxes[] = [
            'name' => $name,
            'rate' => $rate,
            'amount' => round($amount, 2)
        ];
        $this->calculateTotal();
    }

    protected function calculateTotal(): void
    {
        $tax_total = array_sum(array_column($this->taxes, 'amount'));
        $this->total = round($this->base_price - $this->discount + $tax_total, 2);
    }

    /** ----------------- Getters ----------------- **/
    public function getBasePrice(): float { return $this->base_price; }
    public function getDiscount(): float { return $this->discount; }
    public function getTaxes(): array { return $this->taxes; }
    public function getTotal(): float { return $this->total; }
    public function getCurrency(): string { return $this->currency; }
    public function getPdAttr(): int { return $this->pd_attr; }

    /** ----------------- Non-Static DB Save ----------------- **/
    public function save(): bool
    {
        $pdo = DB_CONNECTION->connect();

        $stmt = $pdo->prepare("
            INSERT INTO `commerce_price` (`pd_attr`, `base_price`, `discount`, `currency`)
            VALUES (:pd_attr, :base_price, :discount, :currency)
            ON DUPLICATE KEY UPDATE
                `base_price` = VALUES(`base_price`),
                `discount` = VALUES(`discount`),
                `currency` = VALUES(`currency`)
        ");

        return $stmt->execute([
            'pd_attr' => $this->pd_attr,
            'base_price' => $this->base_price,
            'discount' => $this->discount,
            'currency' => $this->currency
        ]);
    }

    /** ----------------- Static Methods ----------------- **/

    public static function create(int $pd_attr, float $amount, string $currency = 'USD', string $store = 'default'): Price
    {
        $storeObj = new Store($store);
        $storeTaxes = $storeObj->getTaxes();
        $price = new self($pd_attr, $amount, $currency);

        foreach ($storeTaxes as $tax) {
            if (isset($tax['name'], $tax['rate'])) {
                $price->addTax($tax['name'], $tax['rate']);
            }
        }

        $price->save();
        return $price;
    }

    public static function load(int $pd_attr, string $store = 'default'): ?Price
    {
        $pdo = DB_CONNECTION->connect();
        $stmt = $pdo->prepare("SELECT * FROM `commerce_price` WHERE `pd_attr` = :pd_attr LIMIT 1");
        $stmt->execute(['pd_attr' => $pd_attr]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $price = new self(
            (int)$row['pd_attr'],
            (float)$row['base_price'],
            $row['currency'] ?? 'USD',
            (float)$row['discount']
        );

        // Apply store taxes dynamically
        $store = new Store($store);
        foreach ($store->getTaxes() as $tax) {
            if (isset($tax['name'], $tax['rate'])) {
                $price->addTax($tax['name'], $tax['rate']);
            }
        }

        return $price;
    }

    /**
     * Delete a price entry from the database by product attribute ID.
     */
    public static function delete(int $pd_attr): bool
    {
        $pdo = DB_CONNECTION->connect();
        $stmt = $pdo->prepare("DELETE FROM `commerce_price` WHERE `pd_attr` = :pd_attr");
        return $stmt->execute(['pd_attr' => $pd_attr]);
    }

    public function toArray(): array
    {
        return [
            'base_price' => $this->base_price,
            'discount' => $this->discount,
            'currency' => $this->currency,
            'taxes' => $this->taxes,
            'total' => $this->total
        ];
    }

    public function setBasePrice(float $base_price): void
    {
        $this->base_price = $base_price;
    }

    public function setDiscount(float $discount): void
    {
        $this->discount = $discount;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function setPdAttr(int $pd_attr): void
    {
        $this->pd_attr = $pd_attr;
    }

    public function getPriceIn($currency_code): float
    {
        $conversion = new Conversion();
        $rate = $conversion->getConversionObject($currency_code, $this->currency);
        if ($rate) {
            return round($this->base_price * $rate->rate, 2);
        }
        return $this->base_price;
    }

}
