<?php

namespace Simp\Commerce\payment;

use PDO;
use Simp\Commerce\callback\AuthorizeTransactionUtility;
use Simp\Commerce\order\Order;

class CommercePayment
{
    private ?int $id = null;
    private int $order_id;
    private string $payment_method;
    private ?string $transaction_id = null;
    private float $amount = 0.00;
    private string $status = 'pending';
    private string $currency = 'MWK';
    private ?string $created_at = null;
    private ?string $updated_at = null;

    private PDO $db;

    public function __construct()
    {
        $this->db = DB_CONNECTION->connect();
    }

    /* ----------------------------
       Loaders
    -----------------------------*/

    public function load(int $id): ?self
    {
        $stmt = $this->db->prepare("SELECT * FROM commerce_payment WHERE id = ?");
        $stmt->execute([$id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }

        $this->fill($data);
        return $this;
    }

    public static function loadByOrderId(int $orderId): ?CommercePayment
    {
        $stmt = DB_CONNECTION->connect()->prepare("SELECT * FROM commerce_payment WHERE order_id = ? ORDER BY id DESC");
        $stmt->execute([$orderId]);
        $payment = $stmt->fetch();
        if (!$payment) return null;
        return new self()->load($payment->id);
    }

    private function fill(array $data): void
    {
        $this->id = (int)$data['id'];
        $this->order_id = (int)$data['order_id'];
        $this->payment_method = $data['payment_method'];
        $this->transaction_id = $data['transaction_id'];
        $this->amount = (float)$data['amount'];
        $this->status = $data['status'];
        $this->currency = $data['currency'];
        $this->created_at = $data['created_at'];
        $this->updated_at = $data['updated_at'];
    }

    /* ----------------------------
       Save / Update / Delete
    -----------------------------*/

    public function save(): bool
    {
        if ($this->id === null) {
            // Insert
            $stmt = $this->db->prepare("
                INSERT INTO commerce_payment 
                (order_id, payment_method, transaction_id, amount, status, currency)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $ok = $stmt->execute([
                $this->order_id,
                $this->payment_method,
                $this->transaction_id,
                $this->amount,
                $this->status,
                $this->currency
            ]);

            if ($ok) {
                $this->id = (int)$this->db->lastInsertId();
            }

            return $ok;
        }

        // Update
        $stmt = $this->db->prepare("
            UPDATE commerce_payment SET
                order_id = ?, 
                payment_method = ?, 
                transaction_id = ?, 
                amount = ?, 
                status = ?, 
                currency = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $this->order_id,
            $this->payment_method,
            $this->transaction_id,
            $this->amount,
            $this->status,
            $this->currency,
            $this->id
        ]);
    }

    public function delete(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $stmt = $this->db->prepare("DELETE FROM commerce_payment WHERE id = ?");
        return $stmt->execute([$this->id]);
    }

    /* ----------------------------
       Getters + Setters
    -----------------------------*/

    public function isPaid(): bool
    { return $this->status === 'paid';

    }
    public function isFailed(): bool
    { return $this->status === 'failed'; }
    public function isPending(): bool
    { return $this->status === 'pending'; }
    public function isCancelled(): bool
    { return $this->status === 'cancelled'; }
    public function isRefunded(): bool
    { return $this->status === 'refunded'; }
    public function id(): ?int
    {
        return $this->id;
    }
    public function getId(): ?int { return $this->id; }
    public function getOrderId(): int { return $this->order_id; }
    public function getMethod(): string { return $this->payment_method; }
    public function getTransactionId(): ?string { return $this->transaction_id; }
    public function getAmount(): float { return $this->amount; }
    public function getStatus(): string { return $this->status; }
    public function getCurrency(): string { return $this->currency; }
    public function getCreatedAt(): ?string { return $this->created_at; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }

    public function getOrder(): Order
    {
        return new Order($this->order_id);
    }

    public function setOrderId(int $id): self { $this->order_id = $id; return $this; }
    public function setMethod(string $m): self { $this->payment_method = $m; return $this; }
    public function setTransactionId(?string $t): self { $this->transaction_id = $t; return $this; }
    public function setAmount(float $a): self { $this->amount = $a; return $this; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
    public function setCurrency(string $c): self { $this->currency = $c; return $this; }

    public static function getPaymentByOrderId(int $orderId): ?CommercePayment
    {
        $query = "SELECT id FROM commerce_payment WHERE order_id = ?";
        $stmt = DB_CONNECTION->connect()->prepare($query);
        $stmt->execute([$orderId]);
        $result = $stmt->fetch();
        if (!$result) return null;
        return new CommercePayment()->load($result->id);
    }

    public static function getPaymentByTransactionId(string $transactionId): ?CommercePayment
    {
        $query = "SELECT id FROM commerce_payment WHERE transaction_id = ?";
        $stmt = DB_CONNECTION->connect()->prepare($query);
        $stmt->execute([$transactionId]);
        $result = $stmt->fetch();
        if (!$result) return null;
        return new CommercePayment()->load($result->id);
    }

    /**
     * @param int[] $order_ids
     */
    public static function getPaymentByOrders(array $order_ids): array
    {
        $query = "SELECT id FROM commerce_payment WHERE order_id IN (".implode(',', $order_ids).")";
        $stmt = DB_CONNECTION->connect()->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll();
        return array_map(fn($result) => new CommercePayment()->load($result->id), $results);
    }

    /**
     * @param string $code
     * @return CommercePayment[]|null
     */
    public static function getPaymentByCurrency(string $code): ?array
    {
        $query = "SELECT id FROM commerce_payment WHERE currency = ?";
        $stmt = DB_CONNECTION->connect()->prepare($query);
        $stmt->execute([$code]);
        $result = $stmt->fetchAll();
        if (!$result) return null;
       return array_map(fn($result) => new CommercePayment()->load($result->id), $result);
    }

    /**
     * @param string $method
     * @return CommercePayment[]|null
     */
    public static function getPaymentByMethod(string $method): ?array
    {
        $query = "SELECT id FROM commerce_payment WHERE payment_method = ?";
        $stmt = DB_CONNECTION->connect()->prepare($query);
        $stmt->execute([$method]);
        $result = $stmt->fetchAll();
        if (!$result) return null;
        return array_map(fn($result) => new CommercePayment()->load($result->id), $result);
    }

    public static function getPaymentByStore(string $store_id)
    {
        $query = "SELECT payment.id FROM commerce_order AS ord INNER JOIN commerce_payment AS payment ON ord.id = payment.order_id WHERE ord.store_id = ?";
        $stmt = DB_CONNECTION->connect()->prepare($query);
        $stmt->execute([$store_id]);
        $results = $stmt->fetchAll();
        return array_map(fn($result) => new CommercePayment()->load($result->id), $results);
    }

    public function getTransaction()
    {
        $callback = _CALLBACK['authorize_transaction'] ?? null;
        if (!$callback) return null;
        $callbackObject = new $callback;

        return call_user_func([$callbackObject, 'getTransactionDetails'], $this->transaction_id);
    }

    public function getPaymentDetails()
    {
        $query = "SELECT * FROM commerce_payment_details WHERE payment_id = ?";
        $stmt = DB_CONNECTION->connect()->prepare($query);
        $stmt->execute([$this->id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @param int $id
     * @return CommercePayment|null
     */
    public static function loadById(int $id)
    {
        return new self()->load($id);
    }

    public function updateStatus(mixed $status)
    {
        $this->status = $status;
        return $this->save();
    }

    public function reFundPayment(float $amount)
    {
        $details = $this->getPaymentDetails();
        if (!$details) return false;

        $lastDigits = substr($details['card_number'], -4);
        $refund = new AuthorizeTransactionUtility()->refund($this->transaction_id, $amount, $lastDigits);

        dd($refund);
    }
}
