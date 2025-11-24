<?php

namespace Simp\Commerce\callback;

class OrderStatusChangeEmailCallBack
{
    public function __call(string $name, array $arguments)
    {
        $email = new \StdClass();

        $order = $arguments['order'] ?? null;
        $status = $arguments['status'] ?? null;

        $email->subject = "Order Status Changed";
        $email->body = "Your order status has been changed to '{$status}'. Order ID: {$order->id()}";
        return $email;
    }
}