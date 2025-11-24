<?php

namespace Simp\Commerce\store;

use Simp\Commerce\product\Products;

/**
 * Class Store
 *
 * Represents a single store within the commerce system.
 * Provides access to configuration and metadata defined in STORE_LIST.
 */
class Store
{
    /**
     * The selected store configuration.
     *
     * @var array
     */
    protected array $store;

    /**
     * Store constructor.
     *
     * Finds and initializes the store by its ID from the STORE_LIST constant.
     *
     * @param string $storeId The unique ID of the store.
     */
    public function __construct(string $storeId)
    {
        $store = array_filter(STORE_LIST, function ($item) use ($storeId) {
            return $item['id'] === $storeId;
        });
        $this->store = reset($store);
    }

    /**
     * Get the store ID.
     *
     * @return string The store's unique identifier.
     */
    public function getStoreId(): string
    {
        return $this->store['id'];
    }

    /**
     * Get the store name.
     *
     * @return string The display name of the store.
     */
    public function getStoreName(): string
    {
        return $this->store['name'];
    }

    /**
     * Get the store's base currency.
     *
     * @return string The ISO currency code (e.g., USD, EUR).
     */
    public function getStoreCurrency(): string
    {
        return $this->store['currency'];
    }

    /**
     * Get the store's country code.
     *
     * @return string The ISO country code (e.g., US, GB).
     */
    public function getCountryCode(): string
    {
        return $this->store['country'];
    }

    /**
     * Get the store description.
     *
     * @return string|null The description text of the store.
     */
    public function getDescription()
    {
        return $this->store['description'];
    }

    /**
     * Get the store's support email address.
     *
     * @return string|null The contact email for support.
     */
    public function getSupportEmail()
    {
        return $this->store['contact_email'];
    }

    /**
     * Check whether tax is included in prices.
     *
     * @return bool True if tax is included, false otherwise.
     */
    public function isTaxIncluded(): bool
    {
        return $this->store['tax_included'];
    }

    /**
     * Get the store's default shipping zone.
     *
     * @return string The default shipping zone code (e.g., "US").
     */
    public function getDefaultShippingZone(): string
    {
        return $this->store['default_shipping_zone'];
    }

    /**
     * Get the store tax configuration.
     *
     * @return array The taxes applied by the store.
     */
    public function getTaxes(): array
    {
        return $this->store['taxes'];
    }

    /**
     * Check whether shipping is included in prices.
     *
     * @return bool True if shipping is included, false otherwise.
     */
    public function isShippingIncluded(): bool
    {
        return $this->store['shipping_included'];
    }

    /**
     * Get the store's shipping fees.
     *
     * @return mixed The defined shipping fees or structure.
     */
    public function getShippingFees()
    {
        return $this->store['shipping_fees'];
    }

    public function getStoreLogo()
    {
        return $this->store['logo'];
    }

    public function getPhone()
    {
        return $this->store['phone'];

    }

    public function __toString(): string
    {
        $products = new Products();
        return "(". $this->getStoreName() ." ". $products->countProductByStore($this->getStoreId()) .")";
    }

    public function getStoreAddress() {
        return $this->store['address'];
    }
}
