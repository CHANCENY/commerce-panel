<?php

namespace Simp\Commerce\order;

use PDO;

class BillingAddress implements AddressInterface
{
    protected array $values;

    public function getFullName(): ?string
    {
        return $this->values['full_name'] ?? null;
    }

    public function getEmail(): ?string
    {
        return $this->values['email'] ?? null;
    }

    public function getPhone(): ?string
    {
        return $this->values['phone'] ?? null;
    }

    public function getCity(): ?string
    {
        return $this->values['city'] ?? null;
    }

    public function getAddress(): ?string
    {
        return <<<ADDR
{$this->getAddressLine1()}<br>
{$this->getAddressLine2()}<br>
{$this->getCity()}, {$this->getState()} {$this->getZipCode()}<br>
{$this->getCountryName()}
ADDR;

    }

    public function getZipCode(): ?string
    {
        return $this->values['zip_code'] ?? null;
    }

    public function getCountry(): ?string
    {
        return $this->values['country'] ?? null;
    }

    public function getState(): ?string
    {
        return $this->values['state'] ?? null;
    }

    public function getAddressLine1(): ?string
    {
        return $this->values['address_line1'] ?? null;
    }

    public function getAddressLine2(): ?string
    {
        return $this->values['address_line2'] ?? null;
    }

    public function getCountryName(): ?string
    {
        $found = array_filter(COUNTRIES, function($country) {
            return strtolower($country['code']) === strtolower($this->getCountry());
        });
        return reset($found)['name'] ?? null;
    }

    public function load(int $id, string $column)
    {
        $query = "SELECT * FROM `commerce_billing_address` WHERE `{$column}` = :id LIMIT 1";
        $stmt = DB_CONNECTION->connect()->prepare($query);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->values = $row;
        return $this;
    }

    public static function loadByOrder(int $order_id)
    {
        return new BillingAddress()->load($order_id, 'order_id');
    }

    public static function loadByCustomer(string $email)
    {
        return new BillingAddress()->load($email, 'email');
    }

    public static function loadById(int $id)
    {
        return new BillingAddress()->load($id, 'id');
    }
}