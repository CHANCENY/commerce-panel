<?php

namespace Simp\Commerce\product;

use PDO;

/**
 * Class Product
 *
 * Represents the core product entity in the commerce system.
 */
class Product
{
    protected int|string $id;
    protected string $sku;
    protected string $title;
    protected ?string $description = null;
    protected ?string $category = null;
    protected array $images = [];
    protected bool $isActive = true;
    protected string $storeId;
    protected int $createdAt;
    protected int $updatedAt;

    public function __construct(array $data)
    {
        $this->id          = $data['id'] ?? 0;
        $this->sku         = $data['sku'] ?? '';
        $this->title       = $data['title'] ?? '';
        $this->description = $data['description'] ?? null;
        $this->category    = $data['category'] ?? null;
        $this->images      = is_string( $data['images']) ? json_decode( $data['images']) : $data['images'] ?? [];
        $this->isActive    = (bool) ($data['is_active'] ?? true);
        $this->storeId     = $data['store_id'] ?? 'default';
        $this->createdAt   = $data['created_at'] ?? time();
        $this->updatedAt   = $data['updated_at'] ?? time();
    }

    /** ----------------- Getters ----------------- **/

    public function id()
    {
        return $this->id;
    }
    public function getId(): int|string { return $this->id; }
    public function getSku(): string { return $this->sku; }
    public function getTitle(): string { return $this->title; }
    public function getDescription(): ?string { return $this->description; }
    public function getCategory(): ?string { return $this->category; }
    public function getImages(): array { return $this->images; }
    public function isActive(): bool { return $this->isActive; }
    public function getStoreId(): string { return $this->storeId; }
    public function getCreatedAt(): int { return $this->createdAt; }
    public function getUpdatedAt(): int { return $this->updatedAt; }

    /** ----------------- Static Methods ----------------- **/

    /**
     * Create a new product instance and optionally save to DB.
     */
    public static function create(array $data): Product
    {
        $product = new self($data);
        $product->save();
        return $product;
    }

    /**
     * Load a product by ID from the database.
     */
    public static function load(int|string $id): ?Product
    {
        $stmt = DB_CONNECTION->connect()->prepare("SELECT * FROM `commerce_products` WHERE `id` = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        // Decode JSON images if stored as JSON
        if (isset($row['images'])) {
            $row['images'] = json_decode($row['images'], true) ?? [];
        }

        return new self($row);
    }

    /** ----------------- Non-Static Methods ----------------- **/

    /**
     * Save the product to the database (insert or update).
     */
    public function save(): bool
    {
        $this->updatedAt = time();
        $imagesJson = json_encode($this->images);

        if ($this->id) {
            // Update existing product
            $stmt = DB_CONNECTION->connect()->prepare("
                UPDATE `commerce_products` SET
                    `sku` = :sku,
                    `title` = :title,
                    `description` = :description,
                    `category` = :category,
                    `images` = :images,
                    `is_active` = :is_active,
                    `store_id` = :store_id,
                    `updated_at` = :updated_at
                WHERE `id` = :id
            ");
            return $stmt->execute([
                'sku' => $this->sku,
                'title' => $this->title,
                'description' => $this->description,
                'category' => $this->category,
                'images' => $imagesJson,
                'is_active' => $this->isActive ? 1 : 0,
                'store_id' => $this->storeId,
                'updated_at' => $this->updatedAt,
                'id' => $this->id
            ]);
        }
        else {
            // Insert new product
            $this->createdAt = time();
            $stmt = DB_CONNECTION->connect()->prepare("
                INSERT INTO `commerce_products`
                    (`sku`, `title`, `description`, `category`, `images`, `is_active`, `store_id`, `created_at`, `updated_at`)
                VALUES
                    (:sku, :title, :description, :category, :images, :is_active, :store_id, :created_at, :updated_at)
            ");
            $result = $stmt->execute([
                'sku' => $this->sku,
                'title' => $this->title,
                'description' => $this->description,
                'category' => $this->category,
                'images' => $imagesJson,
                'is_active' => $this->isActive ? 1 : 0,
                'store_id' => $this->storeId,
                'created_at' => $this->createdAt,
                'updated_at' => $this->updatedAt
            ]);

            if ($result) {
                $this->id = DB_CONNECTION->connect()->lastInsertId();
            }

            return $result;
        }
    }

    /**
     * Delete the product from database
     * @return bool
     */
    public function delete(): bool
    {
        $stmt = DB_CONNECTION->connect()->prepare("delete from `commerce_products` WHERE `id` = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    public function setId(int|string $id): void
    {
        $this->id = $id;
    }

    public function setSku(string $sku): void
    {
        $this->sku = $sku;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setCategory(?string $category): void
    {
        $this->category = $category;
    }

    public function setImages(array $images): void
    {
        $this->images = $images;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }

    public function setStoreId(string $storeId): void
    {
        $this->storeId = $storeId;
    }

    public function setCreatedAt(int $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(int $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }


}
