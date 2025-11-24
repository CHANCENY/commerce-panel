<?php

namespace Simp\Commerce\customer;

use PDO;

class CustomerAddress
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB_CONNECTION->connect();
    }

    /**
     * Create a new address for a customer
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO commerce_customer_addresses (
                customer_id, label, first_name, last_name, company, phone, email,
                address_line1, address_line2, city, state, postal_code, country,
                is_default_billing, is_default_shipping, created_at, updated_at
            ) VALUES (
                :customer_id, :label, :first_name, :last_name, :company, :phone, :email,
                :address_line1, :address_line2, :city, :state, :postal_code, :country,
                :is_default_billing, :is_default_shipping, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':customer_id'        => $data['customer_id'],
            ':label'              => $data['label'] ?? null,
            ':first_name'         => $data['first_name'],
            ':last_name'          => $data['last_name'],
            ':company'            => $data['company'] ?? null,
            ':phone'              => $data['phone'] ?? null,
            ':email'              => $data['email'] ?? null,
            ':address_line1'      => $data['address_line1'],
            ':address_line2'      => $data['address_line2'] ?? null,
            ':city'               => $data['city'],
            ':state'              => $data['state'] ?? null,
            ':postal_code'        => $data['postal_code'] ?? null,
            ':country'            => $data['country'],
            ':is_default_billing' => $data['is_default_billing'] ?? 0,
            ':is_default_shipping'=> $data['is_default_shipping'] ?? 0,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Load a specific address by ID
     */
    public function load(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM commerce_customer_addresses WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Get all addresses for a customer
     */
    public function getByCustomer(int $customerId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM commerce_customer_addresses WHERE customer_id = :customer_id ORDER BY created_at DESC");
        $stmt->execute([':customer_id' => $customerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update an address
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        if (empty($fields)) return false;

        $sql = "UPDATE commerce_customer_addresses SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Delete an address
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM commerce_customer_addresses WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get the default billing or shipping address
     */
    public function getDefault(int $customerId, string $type = 'billing'): ?array
    {
        $column = $type === 'shipping' ? 'is_default_shipping' : 'is_default_billing';
        $stmt = $this->db->prepare("SELECT * FROM commerce_customer_addresses WHERE customer_id = :customer_id AND $column = 1 LIMIT 1");
        $stmt->execute([':customer_id' => $customerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Set a default billing or shipping address
     */
    public function setDefault(int $customerId, int $addressId, string $type = 'billing'): bool
    {
        $billingColumn = $type === 'shipping' ? 'is_default_shipping' : 'is_default_billing';

        // Reset defaults
        $this->db->prepare("UPDATE commerce_customer_addresses SET $billingColumn = 0 WHERE customer_id = :customer_id")
            ->execute([':customer_id' => $customerId]);

        // Set new default
        $stmt = $this->db->prepare("UPDATE commerce_customer_addresses SET $billingColumn = 1 WHERE id = :address_id AND customer_id = :customer_id");
        return $stmt->execute([':address_id' => $addressId, ':customer_id' => $customerId]);
    }
}
