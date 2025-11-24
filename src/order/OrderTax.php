<?php

namespace Simp\Commerce\order;

class OrderTax
{
    protected string $name;
    protected float $rate;
    protected float $amount;

    public function __construct(string $name, float $rate, float $amount)
    {
        $this->name = $name;
        $this->rate = $rate;
        $this->amount = $amount;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }


}