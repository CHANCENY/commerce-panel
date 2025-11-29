<?php

namespace Simp\Commerce\callback;

use Simp\Commerce\invoice\InvoiceFileManager;
use Simp\Commerce\template\View;

class OrderPaymentSendCallBack
{
    public function __call(string $name, array $arguments)
    {
        $body = new View()->render('p/order_payment_print.twig',[
            'payment' => $arguments['payment'],
            'store' => $arguments['store'],
        ]);

        $path = $_ENV['INVOICE_DIR']. '/payment_'. $arguments['payment']->id() .'.pdf';
        PDF->WriteHTML($body);
        PDF->Output($path, 'F');

        $email = new \StdClass();
        $email->subject = "Order payment #{$arguments['payment']->id()}";
        $email->body = $body;
        $email->attachements = [$path];
        return $email;
    }
}