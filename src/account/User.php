<?php

namespace Simp\Commerce\account;

use Simp\Commerce\connection\Mail;
use Simp\Commerce\storage\CommerceTemporary;
use Symfony\Component\HttpFoundation\Request;

class User
{
    private int $id;
    private string $name;
    private string $email;
    private ?string $phone = null;
    private string $password;
    private string $status = 'active';
    private ?string $last_login = null;
    private string $created_at;
    private string $updated_at;

    /* -----------------------------
        GETTERS & SETTERS
    ------------------------------ */
    public static function loadByName(mixed $name)
    {
        $db = DB_CONNECTION->connect();
        $stmt = $db->prepare("SELECT * FROM commerce_user WHERE name = ?");
        $stmt->execute([$name]);

        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) return null;

        return self::map($data);
    }

    public static function all(int $limit)
    {
        $db = DB_CONNECTION->connect();
        $stmt = $db->prepare("SELECT * FROM commerce_user ORDER BY id DESC LIMIT ?");
        $stmt->execute([$limit]);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return array_map([self::class, 'map'], $data);

    }

    public static function count()
    {
        $db = DB_CONNECTION->connect();
        $stmt = $db->prepare("SELECT COUNT(*) FROM commerce_user");
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getId(): int { return $this->id; }
    public function setId(int $id): self { $this->id = $id; return $this; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): self { $this->password = $password; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getLastLogin(): ?string { return $this->last_login; }
    public function setLastLogin(?string $lastLogin): self { $this->last_login = $lastLogin; return $this; }

    public function getCreatedAt(): string { return $this->created_at; }
    public function setCreatedAt(string $createdAt): self { $this->created_at = $createdAt; return $this; }

    public function getUpdatedAt(): string { return $this->updated_at; }
    public function setUpdatedAt(string $updatedAt): self { $this->updated_at = $updatedAt; return $this; }

    /* -----------------------------
        SAVE
    ------------------------------ */

    public function save()
    {
        $db = DB_CONNECTION->connect();

        if (!empty($this->id)) {
            // UPDATE
            $stmt = $db->prepare("
                UPDATE commerce_user SET 
                    name = ?, email = ?, phone = ?, password = ?, status = ?, 
                    last_login = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $this->name,
                $this->email,
                $this->phone,
                $this->password,
                $this->status,
                $this->last_login,
                $this->id
            ]);
        } else {
            // INSERT
            $stmt = $db->prepare("
                INSERT INTO commerce_user 
                    (name, email, phone, password, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $this->name,
                $this->email,
                $this->phone,
                $this->password,
                $this->status,
            ]);

            $this->id = (int)$db->lastInsertId();
        }

        return $this;
    }

    /* -----------------------------
        CREATE
    ------------------------------ */

    public static function create(array $data): self
    {
        $user = new self();

        $user->setName($data['name']);
        $user->setEmail($data['email']);
        $user->setPhone($data['phone'] ?? null);
        $user->setPassword(password_hash($data['password'], PASSWORD_BCRYPT));
        $user->setStatus($data['status'] ?? 'active');

        return $user->save();
    }

    /* -----------------------------
        TO ARRAY
    ------------------------------ */

    public function toArray(): array
    {
        return [
            "id"         => $this->id,
            "name"       => $this->name,
            "email"      => $this->email,
            "phone"      => $this->phone,
            "status"     => $this->status,
            "last_login" => $this->last_login,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at
        ];
    }

    /* -----------------------------
        LOADERS
    ------------------------------ */

    public static function load(int $id): ?self
    {
        $db = DB_CONNECTION->connect();
        $stmt = $db->prepare("SELECT * FROM commerce_user WHERE id = ?");
        $stmt->execute([$id]);

        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) return null;

        return self::map($data);
    }

    public static function loadByEmail(string $email): ?self
    {
        $db = DB_CONNECTION->connect();
        $stmt = $db->prepare("SELECT * FROM commerce_user WHERE email = ?");
        $stmt->execute([$email]);

        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$data) return null;

        return self::map($data);
    }

    private static function map(array $data): self
    {
        $u = new self();

        $u->id = (int)$data['id'];
        $u->name = $data['name'];
        $u->email = $data['email'];
        $u->phone = $data['phone'];
        $u->password = $data['password'];
        $u->status = $data['status'];
        $u->last_login = $data['last_login'];
        $u->created_at = $data['created_at'];
        $u->updated_at = $data['updated_at'];

        return $u;
    }

    /* -----------------------------
        AUTH
    ------------------------------ */

    public function login(string $password)
    {
        if (!password_verify($password, $this->password)) {
            return false;
        }

        $this->last_login = date("Y-m-d H:i:s");
        $this->save();

        // Save session or token in framework
        $_SESSION['private.account'] = $this;

        return true;
    }

    public function logout()
    {
        // optional: destroy session or token in framework
        if (isset($_SESSION['private.account'])) unset($_SESSION['private.account']);
        return true;
    }

    public function changePassword(string $oldPassword, string $newPassword)
    {
        if (!password_verify($oldPassword, $this->password)) {
            return false;
        }

        $this->password = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->save();

        return true;
    }

    /* -----------------------------
        DELETE
    ------------------------------ */

    public function delete()
    {
        if (!$this->id) return false;

        $db = DB_CONNECTION->connect();
        $stmt = $db->prepare("DELETE FROM commerce_user WHERE id = ?");
        return $stmt->execute([$this->id]);
    }

    /* -----------------------------
        EMAILS
    ------------------------------ */

    public function sendOneTimeLink(Request $request)
    {
        $temp = new CommerceTemporary();
        $tokenId = $temp->create(["user_id" => $this->id]);

        (new Mail())->send(
            $this->email,
            "One Time Login Link",
            "Your login link:",
            $request->getSchemeAndHttpHost() . "/login?token={$tokenId}",
            "User Account"
        );
    }

    public function sendPasswordResetLink(Request $request)
    {
        $temp = new CommerceTemporary();
        $id = $temp->create(["reset_for" => $this->id]);

        (new Mail())->send(
            $this->email,
            "Password Reset Request",
            "Click the link below to reset your password:",
            $request->getSchemeAndHttpHost() . "/reset-password?token={$id}",
            "Reset Password"
        );
    }

    public static function currentUser(): self|null
    {
        return $_SESSION['private.account'] ?? null;
    }

    public function isLogin()
    {
        $user = self::currentUser();

        if ($this->id === $user->id) return true;
    }
}
