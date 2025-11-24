<?php

namespace Simp\Commerce\callback;

use Simp\Commerce\invoice\InvoiceFileManager;
use Simp\Commerce\template\View;

class OrderConfirmationCallBack
{
    public function __call(string $name, array $arguments)
    {
        $body = new View()->render('p/order_invoice.twig',[
            'order' => $arguments['order'],
            'store' => $arguments['store'],
        ]);

        $attachment = InvoiceFileManager::saveInvoice(
            $arguments['order']->id(),
            $arguments['store'],
            'p/order_invoice.twig',
            true
        );

        $email = new \StdClass();
        $email->subject = "Order Invoice #{$arguments['order']->id()}";
        $email->body = $body;
        $email->attachments = [$attachment];
        return $email;
    }
}