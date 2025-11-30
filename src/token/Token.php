<?php

namespace Simp\Commerce\token;

class Token
{
    protected array $tokens;

    public function __construct()
    {
        $this->tokens = _TOKENS_REGISTER;
    }

    public function verify(string $token): bool
    {
        $handler = $this->get($token);
        return !empty($handler) && class_implements($handler, TokenInterface::class);

    }

    public function get(string $token): ?string
    {
        return $this->tokens[$token] ?? null;
    }

    public function getAll(): array
    {
        return $this->tokens;
    }

    public function delete(string $token) {
        unset($this->tokens[$token]);
    }

    public function replace(string $token, array $values): mixed
    {
        preg_match_all('/\[(.*?)\]/', $token, $matches);

        // get all values from $matches
        $tokens = array_map(function($item) { return $item; }, $matches[1]);

        $replacement = [];
        foreach ($tokens as $key => $value) {
            if ($this->verify($value)) {
                $handler = $this->get($value);
                $object = new $handler(...$values);
                $return = $object->replace($value);
                if (!empty($return)) {
                    $replacement["[".key($return)."]"] = reset($return);
                }
            }
        }

        $mapped = array_map(function ($item){
            return "[$item]";
        }, $tokens);

        return str_replace($mapped, $replacement, $token);
    }
}