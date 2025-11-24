<?php

namespace Simp\Commerce\storage;

use PDO;

class CommerceTemporary
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = DB_CONNECTION->connect();
    }

    /**
     * Insert temporary data.
     * Accepts string, array, object.
     * Stored as JSON inside LONGBLOB.
     */
    public function create($data): int
    {
        // Always JSON encode before storing
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        $stmt = $this->db->prepare("
            INSERT INTO commerce_temporary (data) 
            VALUES (:data)
        ");

        $stmt->bindParam(':data', $encoded, PDO::PARAM_LOB);
        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }

    /**
     * Get a temporary record by ID.
     */
    public function get(int $id)
    {
        $stmt = $this->db->prepare("
            SELECT data FROM commerce_temporary WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetchColumn();

        return $row ? json_decode($row, true) : null;
    }

    /**
     * Update temporary data.
     */
    public function update(int $id, $data): bool
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        $stmt = $this->db->prepare("
            UPDATE commerce_temporary SET data = :data WHERE id = :id
        ");

        return $stmt->execute([
            'data' => $encoded,
            'id' => $id
        ]);
    }

    /**
     * Delete a temporary record.
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM commerce_temporary WHERE id = :id
        ");

        return $stmt->execute(['id' => $id]);
    }

    /**
     * Delete all temporary records.
     */
    public function clear(): bool
    {
        return $this->db->exec("TRUNCATE commerce_temporary") !== false;
    }
}
