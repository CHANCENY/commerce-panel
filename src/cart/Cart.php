<?php

namespace Simp\Commerce\cart;

use PDO;
use PDOException;
use Simp\Commerce\product\Product;
use Simp\Commerce\product\ProductAttribute;
use Simp\Commerce\store\Store;

class Cart
{
    private PDO $connection;

    public function __construct()
    {
        $this->connection = DB_CONNECTION->connect();
    }

    /**
     * Create a new cart for a user or guest (session-based)
     */
    public function createCart(?int $userId = null, ?string $sessionId = null, string $currencyCode = 'USD')
    {
        try {
            $stmt = $this->connection->prepare("
                INSERT INTO commerce_carts (user_id, session_id, currency_code)
                VALUES (:user_id, :session_id, :currency_code)
            ");
            $stmt->execute([
                ':user_id' => $userId,
                ':session_id' => $sessionId,
                ':currency_code' => $currencyCode
            ]);
            return (int)$this->connection->lastInsertId();
        } catch (PDOException $e) {
            error_log('Cart creation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Load a cart by ID, user ID, or session ID
     */
    public function load(array $filters)
    {
        try {
            $conditions = [];
            $params = [];

            if (isset($filters['id'])) {
                $conditions[] = 'id = :id';
                $params[':id'] = $filters['id'];
            }
            if (isset($filters['user_id'])) {
                $conditions[] = 'user_id = :user_id';
                $params[':user_id'] = $filters['user_id'];
            }
            if (isset($filters['session_id'])) {
                $conditions[] = 'session_id = :session_id';
                $params[':session_id'] = $filters['session_id'];
            }

            if (empty($conditions)) {
                throw new \InvalidArgumentException('At least one filter (id, user_id, or session_id) must be provided.');
            }

            $sql = 'SELECT * FROM commerce_carts WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1';
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);

            $cart = $stmt->fetch(PDO::FETCH_OBJ);
            if ($cart) {
                $cart->items = $this->getItems($cart->id);

                // add subtotal to cart object
                $cart->subtotal = array_sum(array_column($cart->items, 'total_price'));
            }
            return $cart ?: false;
        } catch (PDOException $e) {
            error_log('Cart load failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add an item to the cart or increase quantity if it already exists
     */
    public function addItem(int $cartId, int $productId, ?int $attributeId, int $quantity, float $unitPrice)
    {
        try {
            $check = $this->connection->prepare("
                SELECT id, quantity FROM commerce_cart_items
                WHERE cart_id = :cart_id AND product_id = :product_id
                AND (attribute_id = :attribute_id1 OR (:attribute_id2 IS NULL AND attribute_id IS NULL))
                LIMIT 1
            ");
            $check->execute([
                ':cart_id' => $cartId,
                ':product_id' => $productId,
                ':attribute_id1' => $attributeId,
                ':attribute_id2' => $attributeId
            ]);

            $existing = $check->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $newQty = $existing['quantity'] + $quantity;
                $update = $this->connection->prepare("
                    UPDATE commerce_cart_items SET quantity = :qty WHERE id = :id
                ");
                $update->execute([':qty' => $newQty, ':id' => $existing['id']]);
            } else {
                $insert = $this->connection->prepare("
                    INSERT INTO commerce_cart_items (cart_id, product_id, attribute_id, quantity, unit_price)
                    VALUES (:cart_id, :product_id, :attribute_id, :quantity, :unit_price)
                ");
                $insert->execute([
                    ':cart_id' => $cartId,
                    ':product_id' => $productId,
                    ':attribute_id' => $attributeId,
                    ':quantity' => $quantity,
                    ':unit_price' => $unitPrice
                ]);
            }

            $this->calculateTotals($cartId);
            return true;
        } catch (PDOException $e) {
            error_log('Add item failed: ' . $e->getMessage());
            dd($e->__toString());
            return false;
        }
    }

    /**
     * Remove an item from the cart
     */
    public function removeItem(int $cartItemId)
    {
        try {
            $stmt = $this->connection->prepare("SELECT cart_id FROM commerce_cart_items WHERE id = :id");
            $stmt->execute([':id' => $cartItemId]);
            $cart = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cart) {
                return false;
            }

            $delete = $this->connection->prepare("DELETE FROM commerce_cart_items WHERE id = :id");
            $delete->execute([':id' => $cartItemId]);

            $this->calculateTotals($cart['cart_id']);
            return true;
        } catch (PDOException $e) {
            error_log('Remove item failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Calculate and update total for the cart
     */
    public function calculateTotals(int $cartId)
    {
        try {
            $stmt = $this->connection->prepare("
                SELECT SUM(quantity * unit_price) AS total
                FROM commerce_cart_items WHERE cart_id = :cart_id
            ");
            $stmt->execute([':cart_id' => $cartId]);
            $total = (float)($stmt->fetchColumn() ?? 0);

            $update = $this->connection->prepare("
                UPDATE commerce_carts SET total = :total, updated_at = CURRENT_TIMESTAMP
                WHERE id = :cart_id
            ");
            $update->execute([':total' => $total, ':cart_id' => $cartId]);
            return true;
        } catch (PDOException $e) {
            error_log('Cart total update failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve all items in the cart
     */
    private function getItems(int $cartId): array
    {
        $stmt = $this->connection->prepare("
            SELECT * FROM commerce_cart_items WHERE cart_id = :cart_id
        ");
        $stmt->execute([':cart_id' => $cartId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getAllCarts(): array
    {
        $stmt = $this->connection->prepare("SELECT * FROM commerce_carts");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function delete(int $id)
    {
        $stmt = $this->connection->prepare("DELETE FROM commerce_carts WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function clearCart(int $cartId)
    {
        $stmt = $this->connection->prepare("DELETE FROM commerce_cart_items WHERE cart_id = :cart_id");
        return $stmt->execute([':cart_id' => $cartId]);
    }

    public function removeEmptyCarts()
    {
        $stmt = $this->connection->prepare("DELETE FROM commerce_carts WHERE total = 0");
        return $stmt->execute();
    }

    public function removeItemByProduct(int $productId)
    {
        $stmt = $this->connection->prepare("DELETE FROM commerce_cart_items WHERE product_id = :product_id");
        return $stmt->execute([':product_id' => $productId]);
    }

    public function removeItemByAttribute(int $attributeId)
    {
        $stmt = $this->connection->prepare("DELETE FROM commerce_cart_items WHERE attribute_id = :attribute_id");
        return $stmt->execute([':attribute_id' => $attributeId]);
    }

    public function getCartTaxes(int $cartId): array
    {
        $products = $this->getItems($cartId);

        $taxes = [];

        // Collect all taxes
        foreach ($products as $productItem) {
            $product = ProductAttribute::load($productItem->product_id);

            foreach ($product->getPrice()->getTaxes() as $tax) {

                // Create a unique key (name + rate)
                $key = $tax['name'] . '_' . $tax['rate'];

                // If not exists, initialize
                if (!isset($taxes[$key])) {
                    $taxes[$key] = $tax;
                } else {
                    // Combine / add tax amount
                    $taxes[$key]['amount'] += $tax['amount'];
                }
            }
        }

        // Return a clean indexed array
        return array_values($taxes);
    }

    public function getAllTotalAmount(int $cart_id)
    {
        $cart = $this->load(['id' => $cart_id]);
        $taxes = $this->getCartTaxes($cart_id);

        //TODO: include shipment if any
        $total = 0;
        foreach ($taxes as $item) {
            $total += $item['amount'];
        }
        return $total + $cart->total;
    }

    public function addNote($id, mixed $note)
    {
        $stmt = $this->connection->prepare("UPDATE commerce_carts SET note_data = :note WHERE id = :id");
        return $stmt->execute([':note' => $note, ':id' => $id]);
    }

}
