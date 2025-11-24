<?php

namespace Simp\Commerce\order;

interface AddressInterface
{
    public function getFullName(): ?string;

    public function getEmail(): ?string;

    public function getPhone(): ?string;

    public function getCity(): ?string;

    public function getAddress(): ?string;

    public function getZipCode(): ?string;

    public function getCountry(): ?string;

    public function getState(): ?string;

    public function getAddressLine1(): ?string;

    public function getAddressLine2(): ?string;

    public function getCountryName(): ?string;

    public function load(int $id, string $column);

    public static function loadByOrder(int $order_id);
    public static function loadByCustomer(string $email);
    public static function loadById(int $id);
}