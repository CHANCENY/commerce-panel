<?php

namespace Simp\Commerce\product;

class Products
{
    /**
     * @var \PDO Database connection
     */
    private $db;

    /**
     * Constructor
     *
     */
    public function __construct()
    {
        $this->db = DB_CONNECTION->connect();
    }

    /**
     * Get all products
     *
     * @param bool $onlyActive If true, fetch only active products
     * @return array List of products
     */
    public function all(bool $onlyActive = true): array
    {
        $query = "SELECT * FROM commerce_products";
        if ($onlyActive) {
            $query .= " WHERE is_active = 1";
        }
        $query .= " ORDER BY updated_at DESC";

        $stmt = $this->db->query($query);
        return array_map(function ($product){ return new Product($product); },$stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Get products for a single store
     *
     * @param int|string $storeId Store ID
     * @param bool $onlyActive If true, fetch only active products
     * @return array List of products
     */
    public function byStore(int|string $storeId, bool $onlyActive = true): array
    {
        $query = "SELECT * FROM commerce_products WHERE store_id = ?";
        if ($onlyActive) {
            $query .= " AND is_active = 1";
        }
        $query .= " ORDER BY updated_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$storeId]);
        return array_map(function ($product){ return new Product($product); },$stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Get products for multiple stores
     *
     * @param array $storeIds Array of store IDs
     * @param bool $onlyActive If true, fetch only active products
     * @return array List of products
     */
    public function byStores(array $storeIds, bool $onlyActive = true): array
    {
        if (empty($storeIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($storeIds), '?'));
        $query = "SELECT * FROM commerce_products WHERE store_id IN ($placeholders)";
        if ($onlyActive) {
            $query .= " AND is_active = 1";
        }
        $query .= " ORDER BY updated_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute($storeIds);
        return array_map(function ($product){ return new Product($product); },$stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Get products by category
     *
     * @param string $category Category name
     * @param bool $onlyActive If true, fetch only active products
     * @return array List of products
     */
    public function byCategory(string $category, bool $onlyActive = true): array
    {
        $query = "SELECT * FROM commerce_products WHERE category = ?";
        if ($onlyActive) {
            $query .= " AND is_active = 1";
        }
        $query .= " ORDER BY updated_at DESC";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$category]);
        return array_map(function ($product){ return new Product($product); },$stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Get a single product by SKU
     *
     * @param string $sku Product SKU
     * @param bool $onlyActive If true, fetch only active product
     * @return Product|null Product data or null if not found
     */
    public function bySku(string $sku, bool $onlyActive = true): ?Product
    {
        $query = "SELECT * FROM commerce_products WHERE sku = ?";
        if ($onlyActive) {
            $query .= " AND is_active = 1";
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute([$sku]);
        $product = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$product) {
            return null;
        }
        return new Product($product);
    }

    public function countProductByStore(string $getStoreId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM commerce_products WHERE store_id = ?");
        $stmt->execute([$getStoreId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row['COUNT(*)'];
    }
}
