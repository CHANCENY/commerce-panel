<?php

namespace Simp\Commerce\order;

class OrderFailedException extends \Exception
{

    /**
     * @param string $string
     */
    public function __construct(string $string)
    {
        parent::__construct($string);
    }
}