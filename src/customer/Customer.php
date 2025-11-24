<?php

namespace Simp\Commerce\customer;

use PDO;
use Exception;

class Customer
{
    private PDO $db;
    private CustomerAddress $addressHandler;

    public function __construct()
    {
        $this->db = DB_CONNECTION->connect();
        $this->addressHandler = new CustomerAddress();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO commerce_customer (first_name, last_name, email, phone)
            VALUES (:first_name, :last_name, :email, :phone)
        ");

        $stmt->execute([
            ':first_name'    => $data['first_name'] ?? '',
            ':last_name'     => $data['last_name'] ?? '',
            ':email'         => $data['email'],
            ':phone'         => $data['phone'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function load(int|string $identifier): ?array
    {
        if (is_int($identifier)) {
            $stmt = $this->db->prepare("SELECT * FROM commerce_customer WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $identifier]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM commerce_customer WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $identifier]);
        }

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        if (empty($fields)) return false;

        $sql = "UPDATE commerce_customer SET " . implode(', ', $fields) . ", updated_at = UNIX_TIMESTAMP() WHERE id = :id";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM commerce_customer WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }


    public function all(string $status = null): array
    {
        if ($status) {
            $stmt = $this->db->prepare("SELECT * FROM commerce_customer WHERE status = :status ORDER BY created_at DESC");
            $stmt->execute([':status' => $status]);
        } else {
            $stmt = $this->db->query("SELECT * FROM commerce_customer ORDER BY created_at DESC");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* -------------------------------------------------------------------
       Address Relationship Methods
    ------------------------------------------------------------------- */

    /**
     * Get all addresses belonging to this customer
     */
    public function getAddresses(int $customerId): array
    {
        return $this->addressHandler->getByCustomer($customerId);
    }

    /**
     * Get default billing address
     */
    public function getBillingAddress(int $customerId): ?array
    {
        return $this->addressHandler->getDefault($customerId, 'billing');
    }

    /**
     * Get default shipping address
     */
    public function getShippingAddress(int $customerId): ?array
    {
        return $this->addressHandler->getDefault($customerId, 'shipping');
    }

    /**
     * Set default billing or shipping address
     */
    public function setDefaultAddress(int $customerId, int $addressId, string $type = 'billing'): bool
    {
        return $this->addressHandler->setDefault($customerId, $addressId, $type);
    }

    /**
     * Add a new address for this customer
     */
    public function addAddress(array $data): int
    {
        return $this->addressHandler->create($data);
    }

}
