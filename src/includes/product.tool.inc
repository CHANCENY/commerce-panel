<?php

use Simp\Commerce\product\Product;

function product_create(array $data): bool
{
    // validate
    $columns_required = [
        "sku",
        "title",
        "description",
        "category",
        "images",
        "is_active",
        "store_id",
        "created_at"
    ];

    foreach ($columns_required as $column) {
        if ($column !== 'is_active' && !isset($data[$column])) {
            throw new ArgumentCountError("Required keys ".implode(', ', $columns_required). " to create product.");
        }
        elseif ($column === 'is_active' && !in_array($data[$column], [true, false])) {
            throw new \http\Exception\InvalidArgumentException("is_active can be true or false.");
        }
    }

    return Product::create($data)->save();
}

function product_update(array $data): bool {
    $product = new Product($data);
    return $product->save();
}

function product_delete(int $pid): bool {
    return Product::load($pid)->delete();
}