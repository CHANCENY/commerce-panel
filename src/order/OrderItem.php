<?php

namespace Simp\Commerce\order;

use Simp\Commerce\product\Product;
use Simp\Commerce\product\ProductAttribute;

class OrderItem
{
    protected int $id;
    protected Product $product;
    protected int $quantity;
    protected float $unit_price;
    protected ProductAttribute $productAttribute;
    protected string $name;
    protected float $total_price;
    protected int $order_id;

    protected \PDO $connection;

    /**
     * @throws \Exception
     */
    public function __construct(int $id)
    {
        $this->connection = DB_CONNECTION->connect();

        $query = "SELECT * FROM commerce_order_items WHERE id = :id";
        $stmt = $this->connection->prepare($query);
        $stmt->execute([':id' => $id]);
        $orderItem = $stmt->fetch();

        if ($orderItem) {
            $this->id = $orderItem->id;
            $this->product = Product::load($orderItem->product_id);
            $this->quantity = $orderItem->quantity;
            $this->unit_price = $orderItem->unit_price;
            $this->order_id = $orderItem->order_id;
            $this->name = $orderItem->name;
            $this->total_price = $orderItem->total_price;
            $this->productAttribute = ProductAttribute::load($orderItem->attribute_id);
        }
        else {
            throw new \Exception("Order item not found with ID: $id");
        }
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): float
    {
        return $this->unit_price;
    }

    public function getProductAttribute(): ProductAttribute
    {
        return $this->productAttribute;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTotalPrice(): float
    {
        return $this->total_price;
    }

    public function getOrderId(): int
    {
        return $this->order_id;
    }

    public function getConnection(): \PDO
    {
        return $this->connection;
    }

}