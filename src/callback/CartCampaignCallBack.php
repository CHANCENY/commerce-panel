<?php

namespace Simp\Commerce\callback;

class CartCampaignCallBack
{
    public function __call(string $name, array $arguments)
    {
        $email = new \StdClass();
        $email->subject = "Happy holidays from Simple Commerce";
        $email->body = "It seem you have items in the cart. Please check it out.<br> 
                      Thank you for shopping with us.";
        return $email;
    }
}