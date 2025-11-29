<?php

namespace Simp\Commerce\payment\types;

use DateTime;
use Simp\Commerce\order\Order;
use Simp\Commerce\order\OrderFailedException;
use Simp\Commerce\payment\CommercePayment;
use Simp\Commerce\payment\PaymentGatWayAbstract;
use Simp\Router\Router\NotFoundException;

class LaterPayment extends PaymentGatWayAbstract
{

    public array $errors = [];
    public function getPaymentForm(array $options = []): string
    {
        return VIEW->render('p/later_payment_form.twig', $options);
    }

    public function getGateWayConfiguration(): array
    {
        return [];
    }

    public function getGatewayName(): string
    {
        return "Payment later";
    }

    public function getGatewayLogos(): array
    {
        return [];
    }

    public function getGatewayDescription(): string
    {
        return "Pay later on your order confirmation";
    }

    public function getGatewayId(): string
    {
        return "later";
    }

    public function isEnabled(): bool
    {
        return $_ENV['LATER_PAYMENT'] ?? false;
    }

    /**
     * @throws NotFoundException
     * @throws OrderFailedException
     * @throws \DateMalformedStringException
     */
    public function processPayment(array $paymentData): bool
    {
        // needed keys
        $requiredKeys = [
            'cart_id',
            'billing_full_name',
            'billing_email',
            'billing_address_line1',
            'billing_address_line2',
            'billing_city',
            'billing_state',
            'billing_postal_code',
            'billing_country',
            'shipping_full_name',
            'shipping_email',
            'shipping_address_line1',
            'shipping_address_line2',
            'shipping_city',
            'shipping_state',
            'shipping_postal_code',
            'shipping_country',
            'pay_later_agree',
            'pay_later_notes',
            'amount',
            'currency',
            'pay_later_notes',
            'pay_later_agree',
            'expected_payment_date'

        ];

        foreach ($requiredKeys as $key) {

            if (!self::dataValidation($paymentData, $key)) {
                $this->errors[] = "Missing required data: {$key}";
            }
        }

        if (empty($this->errors)) {

            $result = $this->saveOrder($paymentData);

            $orders = $result->getOrders();

            if ($orders) {

                $results = [];
                foreach ($orders as $order) {
                    $payment = new CommercePayment();

                    $payment->setOrderId($order->id());
                    $payment->setAmount(self::getKeyValue($paymentData, 'amount'));
                    $payment->setStatus('pending');
                    $payment->setCurrency(self::getKeyValue($paymentData, 'currency'));
                    $payment->setMethod($this->getGatewayId());
                    $payment->setTransactionId(time());
                    if ($payment->save()) {

                        $query = "INSERT INTO commerce_pay_later (order_id, expected_date, pay_later_agree, pay_later_notes) VALUES (:order_id,:expected_date,:agree, :notes)";
                        $stmt = DB_CONNECTION->connect()->prepare($query);

                        $results[] = $stmt->execute([
                            ':order_id' => $order->id(),
                            ':expected_date' => new DateTime(self::getKeyValue($paymentData, 'expected_payment_date'))->getTimestamp(),
                            ':agree' => self::getKeyValue($paymentData, 'pay_later_agree') === "1" ? 1 : 0,
                            ':notes' => self::getKeyValue($paymentData, 'pay_later_notes')
                        ]);
                    }

                }

                return in_array(true, $results);
            }

        }

        return false;
    }
}