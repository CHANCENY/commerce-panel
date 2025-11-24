<?php

namespace Simp\Commerce\account;
class Account
{
    protected int $uid;
    protected string $email;
    protected string $username;

    protected array $values;
    protected bool $isLoggedIn;

    public function __construct(...$values)
    {
        $this->isLoggedIn = false;
        $this->uid = 0;
        $this->email = '';
        $this->username = '';

        if (isset($values['email']) && filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
            $this->email = $values['email'];
        }

        if (isset($values['username'])) {
            $this->username = $values['username'];
        }

        if (isset($values['uid']) && filter_var($values['uid'], FILTER_VALIDATE_INT)) {
            $this->uid = $values['uid'];
        }

        if (isset($values['is_logged_in'])) {
            $this->isLoggedIn = $values['is_logged_in'];
        }

        $this->values = $values;
    }

    public function id()
    {
        return $this->uid;
    }

    public function email() {
        return $this->email;
    }

    public function username() {
        return $this->username;
    }

    public function values(): array
    {
        return $this->values;
    }

    public function get(string $key)
    {
        return $this->values[$key] ?? null;
    }

    public function isLogin()
    {
        return !empty($this->uid);
    }
}