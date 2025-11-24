<?php

namespace Simp\Commerce\product;

use PDO;

class ProductAttributes
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = DB_CONNECTION->connect();
    }

    /**
     * List all product attributes.
     */
    public function all(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM commerce_product_attributes ORDER BY product_id, position ASC"
        );
        return array_map(function ($attr) {
            $attrr = new ProductAttribute($attr->name,$attr->position, $attr->product_id, $attr->always_in_stock, $attr->stock_level, $attr->description);
            $attrr->setId($attr->id);
            return $attrr;
        },$stmt->fetchAll());
    }

    /**
     * List attributes by a specific product ID.
     */
    public function byProduct(int $productId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM commerce_product_attributes WHERE product_id = :product_id ORDER BY position ASC"
        );
        $stmt->execute([':product_id' => $productId]);
        return array_map(function ($attr) {
            $attrr = new ProductAttribute($attr->name,$attr->position, $attr->product_id, $attr->always_in_stock, $attr->stock_level, $attr->description);
            $attrr->setId($attr->id);
            return $attrr;
        },$stmt->fetchAll());
    }

    /**
     * List attributes grouped by product.
     */
    public function groupedByProduct(): array
    {
        $stmt = $this->db->query(
            "SELECT * FROM commerce_product_attributes ORDER BY product_id, position ASC"
        );
        $rows = $stmt->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $attrr = new ProductAttribute($row->name,$row->position, $row->product_id, $row->always_in_stock, $row->stock_level, $row->description);
            $attrr->setId($row['id']);
            $grouped[$row['product_id']][] = $attrr;
        }

        return $grouped;
    }

    /**
     * Find a single attribute by its ID.
     */
    public function find(int $id): ProductAttribute
    {
        $stmt = $this->db->prepare("SELECT * FROM commerce_product_attributes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        $attrr = new ProductAttribute($result->name,$result->position, $result->product_id, $result->always_in_stock, $result->stock_level, $result->description);
        $attrr->setId($result->id);
        return $attrr;
    }

    /**
     * Count how many attributes exist for each product.
     */
    public function countByProduct(): array
    {
        $stmt = $this->db->query(
            "SELECT product_id, COUNT(*) AS total 
             FROM commerce_product_attributes 
             GROUP BY product_id"
        );
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getByProducts(array $products): array
    {
        if (empty($products)) {
            return []; // nothing to query
        }

        $productIds = implode(',', array_map('intval', $products));
        $stmt = $this->db->prepare("SELECT * FROM commerce_product_attributes WHERE product_id IN ($productIds)");
        $stmt->execute();
        $results = $stmt->fetchAll();

        return array_map(function ($result) {
            $attr = new ProductAttribute($result->name,
                $result->position,
                $result->product_id,
                $result->always_in_stock,
                $result->stock_level,
                $result->description,
                $result->default_cart_quantity,
                $result->max_cart_quantity,
                $result->shippable,
                json_decode($result->sizes, true),
                json_decode($result->dimension, true)
            );
            $attr->setId($result->id);
            $attr->loadPrice();
            return $attr;
        },$results);
    }

}
