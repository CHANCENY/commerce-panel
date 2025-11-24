<?php

namespace Simp\Commerce\connection;

use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

class Connection
{
    protected string $dbname;
    protected string $dbhost;
    protected string $dbuser;
    protected string $dbpass;
    protected int $dbport;
    protected string $dbdriver;
    protected string $dbcharset;
    protected string $dbcollation;
    protected PDO $pdo;

    /**
     * Initializes the database connection using the environment variables and provided settings.
     *
     * Validates that all necessary connection parameters are present, constructs a DSN string,
     * and attempts to establish a PDO connection. Throws exceptions if validation fails or the
     * connection cannot be established.
     *
     * @return void
     *
     * @throws InvalidArgumentException if any required database connection parameter is missing.
     * @throws RuntimeException if the database connection fails.
     */
    public function __construct()
    {
        $this->dbname = $_ENV['DB_NAME'];
        $this->dbhost = $_ENV['DB_HOST'];
        $this->dbuser = $_ENV['DB_USER'];
        $this->dbpass = $_ENV['DB_PASS'];
        $this->dbport = !empty($_ENV['DB_PORT'])? $_ENV['DB_PORT'] : 3306;
        $this->dbdriver = !empty($_ENV['DB_DRIVER']) ? $_ENV['DB_DRIVER'] : 'mysql';
        $this->dbcharset = !empty($_ENV['DB_CHARSET']) ? $_ENV['DB_CHARSET'] : 'utf8';
        $this->dbcollation = !empty($_ENV['DB_COLLATION']) ? $_ENV['DB_COLLATION'] : 'utf8mb4_unicode_ci';

        // Validate connection parameters
        if (empty($this->dbname) || empty($this->dbhost) || empty($this->dbuser) || empty($this->dbpass)) {
            throw new InvalidArgumentException('Missing database connection parameters.');
        }

        $dsn = "$this->dbdriver:host=$this->dbhost;port=$this->dbport;dbname=$this->dbname;charset=$this->dbcharset";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $this->dbuser, $this->dbpass, $options);
        } catch (PDOException $e) {
            throw new RuntimeException('Failed to connect to the database: ' . $e->getMessage());
        }
    }

    /**
     * Returns the established PDO database connection.
     *
     * This method provides access to the PDO instance that was previously initialized
     * during the database connection setup.
     *
     * @return PDO The active PDO database connection instance.
     */
    public function connect(): PDO
    {
        return $this->pdo;
    }

    /**
     * Prevents the cloning of the current instance.
     *
     * This method is invoked when an attempt is made to clone the object. Cloning is explicitly
     * disallowed for this instance, and an exception will be thrown if cloning is attempted.
     *
     * @return void
     *
     * @throws RuntimeException if an attempt is made to clone the instance.
     */
    public function __clone(): void
    {
        // Prevent cloning the PDO instance
        throw new RuntimeException('Cannot clone a PDO instance.');
    }
}