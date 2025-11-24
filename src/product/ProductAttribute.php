<?php

namespace Simp\Commerce\product;

use PDO;
use Simp\Commerce\product\Price;

class ProductAttribute
{
    protected string $name;
    protected int $position;
    protected int $productId;
    protected ?int $id = null;
    protected ?Price $price = null;
    protected int $stockLevel;
    protected bool $alwaysInStock;
    protected string $description;
    protected int $defaultCartQuantity;
    protected int $maxCartQuantity;
    protected bool $shippable;
    protected ?array $sizes;
    protected ?array $dimension;

    public function __construct(
        string $name,
        int $position,
        int $productId,
        bool $alwaysInStock = true,
        int $stockLevel = 1,
        string $description = '',
        int $defaultCartQuantity = 1,
        int $maxCartQuantity = 10,
        bool $shippable = true,
        ?array $sizes = null,
        ?array $dimension = null
    ) {
        $this->name = $name;
        $this->position = $position;
        $this->productId = $productId;
        $this->id = 0;
        $this->alwaysInStock = $alwaysInStock;
        $this->stockLevel = $stockLevel;
        $this->description = $description;
        $this->defaultCartQuantity = $defaultCartQuantity;
        $this->maxCartQuantity = $maxCartQuantity;
        $this->shippable = $shippable;
        $this->sizes = $sizes;
        $this->dimension = $dimension;
    }

    public static function create(
        string $name,
        int $position,
        int $productId,
        bool $alwaysInStock = true,
        int $stockLevel = 1,
        string $description = '',
        int $defaultCartQuantity = 1,
        int $maxCartQuantity = 10,
        bool $shippable = true,
        ?array $sizes = null,
        ?array $dimension = null
    ): self {
        return new self($name, $position, $productId, $alwaysInStock, $stockLevel, $description, $defaultCartQuantity, $maxCartQuantity, $shippable, $sizes, $dimension);
    }

    public static function load(int $attrId): ?self
    {
        $stmt = DB_CONNECTION->connect()->prepare("SELECT * FROM commerce_product_attributes WHERE id = ? LIMIT 1");
        $stmt->execute([$attrId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        $attr = new self(
            $data['name'],
            $data['position'],
            $data['product_id'],
            (bool)$data['always_in_stock'],
            (int)$data['stock_level'],
            $data['description'] ?? '',
            (int)$data['default_cart_quantity'],
            (int)$data['max_cart_quantity'],
            (bool)$data['shippable'],
            $data['sizes'] ? json_decode($data['sizes'], true) : null,
            $data['dimension'] ? json_decode($data['dimension'], true) : null
        );

        $attr->setId($data['id']);
        $attr->loadPrice();

        return $attr;
    }

    public function loadPrice(): void
    {
        if (!$this->id) return;

        $stmt = DB_CONNECTION->connect()->prepare("SELECT * FROM commerce_price WHERE pd_attr = ? LIMIT 1");
        $stmt->execute([$this->id]);
        $data = $stmt->fetch(PDO::FETCH_OBJ);

        if ($data) {
            $product = DB_CONNECTION->connect()->prepare("SELECT store_id FROM commerce_products WHERE id = ? LIMIT 1");
            $product->execute([$this->productId]);
            $product = $product->fetch(PDO::FETCH_OBJ);
            $this->price = new Price($this->id, $data->base_price, $data->currency, $data->discount, $product->store_id);
        }
    }

    public function getPrice(): ?Price
    {
        return $this->price;
    }

    public function save(): bool
    {
        $db = DB_CONNECTION->connect();

        if ($this->id) {
            $stmt = $db->prepare(
                "UPDATE commerce_product_attributes SET name=?, position=?, product_id=?, always_in_stock=?, stock_level=?, description=?, default_cart_quantity=?, max_cart_quantity=?, shippable=?, sizes=?, dimension=? WHERE id=?"
            );
            return $stmt->execute([
                $this->name,
                $this->position,
                $this->productId,
                $this->alwaysInStock ? 1 : 0,
                $this->stockLevel,
                $this->description,
                $this->defaultCartQuantity,
                $this->maxCartQuantity,
                $this->shippable ? 1 : 0,
                $this->sizes ? json_encode($this->sizes) : null,
                $this->dimension ? json_encode($this->dimension) : null,
                $this->id
            ]);
        } else {
            $stmt = $db->prepare(
                "INSERT INTO commerce_product_attributes (name, position, product_id, always_in_stock, stock_level, description, default_cart_quantity, max_cart_quantity, shippable, sizes, dimension)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $success = $stmt->execute([
                $this->name,
                $this->position,
                $this->productId,
                $this->alwaysInStock ? 1 : 0,
                $this->stockLevel,
                $this->description,
                $this->defaultCartQuantity,
                $this->maxCartQuantity,
                $this->shippable ? 1 : 0,
                $this->sizes ? json_encode($this->sizes) : null,
                $this->dimension ? json_encode($this->dimension) : null
            ]);
            if ($success) {
                $this->id = (int)$db->lastInsertId();
            }
            return $success;
        }
    }

    // === Getters/Setters ===
    public function id()
    {
        return $this->id;
    }
    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void { $this->name = $name; }
    public function getPosition(): int { return $this->position; }
    public function setPosition(int $position): void { $this->position = $position; }
    public function getProductId(): int { return $this->productId; }
    public function setProductId(int $productId): void { $this->productId = $productId; }

    public function getStockLevel(): int
    {
        return $this->stockLevel;
    }

    public function isAlwaysInStock(): bool
    {
        return $this->alwaysInStock;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getDefaultCartQuantity(): int
    {
        return $this->defaultCartQuantity;
    }

    public function getMaxCartQuantity(): int
    {
        return $this->maxCartQuantity;
    }

    public function isShippable(): bool
    {
        return $this->shippable;
    }

    public function getSizes(): ?array
    {
        return $this->sizes;
    }

    public function getDimension(): ?array
    {
        return $this->dimension;
    }

    public function getProductStoreId()
    {
        $stmt = DB_CONNECTION->connect()->prepare("SELECT store_id FROM commerce_products WHERE id = ? LIMIT 1");
        $stmt->execute([$this->productId]);
        $res = $stmt->fetch(PDO::FETCH_OBJ);
        return $res->store_id ?? '';
    }

    public function setId(mixed $id): void
    {
        $this->id = $id;
        $this->loadPrice();
    }

    public function setPrice(?\Simp\Commerce\product\Price $price): void
    {
        $this->price = $price;
    }

    public function setStockLevel(int $stockLevel): void
    {
        $this->stockLevel = $stockLevel;
    }

    public function setAlwaysInStock(bool $alwaysInStock): void
    {
        $this->alwaysInStock = $alwaysInStock;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function setDefaultCartQuantity(int $defaultCartQuantity): void
    {
        $this->defaultCartQuantity = $defaultCartQuantity;
    }

    public function setMaxCartQuantity(int $maxCartQuantity): void
    {
        $this->maxCartQuantity = $maxCartQuantity;
    }

    public function setShippable(bool $shippable): void
    {
        $this->shippable = $shippable;
    }

    public function setSizes(?array $sizes): void
    {
        $this->sizes = $sizes;
    }

    public function setDimension(?array $dimension): void
    {
        $this->dimension = $dimension;
    }

    public function getProduct(): ?\Simp\Commerce\product\Product
    {
        return \Simp\Commerce\product\Product::load($this->productId);
    }

    public function getProductSku(): string
    {
        return $this->getProduct()->getSku();

    }

    public function getProductTitle(): string {
        return $this->getProduct()->getTitle();
    }

    public function delete(): bool
    {
        $stmt = DB_CONNECTION->connect()->prepare("DELETE FROM commerce_product_attributes WHERE id = ?");
        return $stmt->execute([$this->id]);
    }

    public function duplicate(): ProductAttribute
    {
        $newAttr = new self($this->name, $this->position, $this->productId, $this->alwaysInStock, $this->stockLevel, $this->description, $this->defaultCartQuantity, $this->maxCartQuantity, $this->shippable, $this->sizes, $this->dimension);
        $newAttr->save();

        $price = $this->getPrice();
        $newPrice = new Price($newAttr->id(),$price->getBasePrice(), $price->getCurrency(),$price->getDiscount(), $this->getProductStoreId());
        $newPrice->save();
        return $newAttr;
    }
}
