<?php

namespace Simp\Commerce\payment;

use PDO;

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

    public static function loadByOrderId(PDO $db, int $orderId): array
    {
        $stmt = $db->prepare("SELECT * FROM commerce_payment WHERE order_id = ? ORDER BY id DESC");
        $stmt->execute([$orderId]);

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $payment = new self($db);
            $payment->fill($row);
            $results[] = $payment;
        }

        return $results;
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

    public function getId(): ?int { return $this->id; }
    public function getOrderId(): int { return $this->order_id; }
    public function getMethod(): string { return $this->payment_method; }
    public function getTransactionId(): ?string { return $this->transaction_id; }
    public function getAmount(): float { return $this->amount; }
    public function getStatus(): string { return $this->status; }
    public function getCurrency(): string { return $this->currency; }
    public function getCreatedAt(): ?string { return $this->created_at; }
    public function getUpdatedAt(): ?string { return $this->updated_at; }

    public function setOrderId(int $id): self { $this->order_id = $id; return $this; }
    public function setMethod(string $m): self { $this->payment_method = $m; return $this; }
    public function setTransactionId(?string $t): self { $this->transaction_id = $t; return $this; }
    public function setAmount(float $a): self { $this->amount = $a; return $this; }
    public function setStatus(string $s): self { $this->status = $s; return $this; }
    public function setCurrency(string $c): self { $this->currency = $c; return $this; }
}
