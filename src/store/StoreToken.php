<?php

namespace Simp\Commerce\store;

use Simp\Commerce\token\TokenInterface;

class StoreToken implements TokenInterface
{

    protected Store $store;
    public function __construct(...$values)
    {
        foreach ($values as $value) {

            if ($value instanceof Store) {
                $this->store = $value;
            }
        }
    }

    public function replace(string $token): mixed
    {
        $return = $this->store->$token() ?? null;
        return [$token => $return];
    }
}