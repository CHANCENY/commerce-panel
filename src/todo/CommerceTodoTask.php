<?php

namespace Simp\Commerce\todo;

class CommerceTodoTask
{
    private \PDO $connection;

    public function __construct()
    {
        $this->connection = DB_CONNECTION->connect();
    }

    public function getTasks(): array
    {

        $query = "SELECT * FROM commerce_todo_task ORDER BY start_date DESC";
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function addTask(string $title, int $start_date, int $end_date, int $status = 1): bool {
        $query = "INSERT INTO commerce_todo_task (title, start_date, end_date, status) VALUES (:title, :start_date, :end_date, :status)";
        $stmt = $this->connection->prepare($query);
        return $stmt->execute([':title' => $title, ':start_date' => $start_date, ':end_date' => $end_date, ':status' => $status]);
    }

    public function deleteTask(int $id): bool {
        $query = "DELETE FROM commerce_todo_task WHERE id = :id";
        $stmt = $this->connection->prepare($query);
        return $stmt->execute([':id' => $id]);
    }

}