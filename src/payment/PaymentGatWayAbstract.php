<?php

namespace Simp\Commerce\payment;

use Simp\Commerce\order\Order;
use Simp\Commerce\order\OrderFailedException;
use Simp\Router\Router\NotFoundException;

abstract class PaymentGatWayAbstract implements PaymentGetWayInterface
{

    public array $errors;

    public static function dataValidation(array $data, string $key, ?callable $callback = null)
    {
        $result = self::getValueByKey($data, $key);
         if ($callback) {
             return $callback($result);
         }
         return !empty($result);
    }

    public static function getKeyValue(array $data, string $key, $default = null)
    {
        return self::getValueByKey($data, $key) ?? $default;
    }

    private static function getValueByKey(array $array, $key) {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        // If nested arrays, search recursively
        foreach ($array as $value) {
            if (is_array($value)) {
                $result = self::getValueByKey($value, $key);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        // Key not found
        return null;
    }

    public static function getTypeGateway(string $gateway_id)
    {
        $found = array_filter(PAYMENT_METHODS, function ($gateway) use ($gateway_id) {
           $obj = new $gateway();

           if ($obj instanceof PaymentGetWayInterface) {
               return $obj->getGatewayId() === $gateway_id;
           }
           return false;
        });

        if (empty($found)) return null;

        $gateway = reset($found);
        return new $gateway();
    }

    /**
     * @throws NotFoundException
     * @throws OrderFailedException
     */
    public function saveOrder(array $paymentData)
    {
        // create order
        $order = new Order();
        if (empty($paymentData['order'])) {
            $tmp = $order->create(self::getKeyValue($paymentData, 'cart_id'));

            if (!$tmp) {
                $this->errors[] = "Failed to create order";
                return false;
            }

            // Append order billing address
            new Order()->appendBillingAddress($tmp,[
                'full_name' => self::getKeyValue($paymentData, 'billing_full_name'),
                'email' => self::getKeyValue($paymentData, 'billing_email'),
                'address_line1' => self::getKeyValue($paymentData, 'billing_address_line1'),
                'address_line2' => self::getKeyValue($paymentData, 'billing_address_line2'),
                'city' => self::getKeyValue($paymentData, 'billing_city'),
                'state' => self::getKeyValue($paymentData, 'billing_state'),
                'postal_code' => self::getKeyValue($paymentData, 'billing_postal_code'),
                'country' => self::getKeyValue($paymentData, 'billing_country'),
                'phone' => self::getKeyValue($paymentData, 'billing_phone')
            ]);

            new Order()->appendShippingAddress($tmp,[
                'full_name' => self::getKeyValue($paymentData, 'shipping_full_name'),
                'email' => self::getKeyValue($paymentData, 'shipping_email'),
                'address_line1' => self::getKeyValue($paymentData, 'shipping_address_line1'),
                'address_line2' => self::getKeyValue($paymentData, 'shipping_address_line2'),
                'city' => self::getKeyValue($paymentData, 'shipping_city'),
                'state' => self::getKeyValue($paymentData, 'shipping_state'),
                'postal_code' => self::getKeyValue($paymentData, 'shipping_postal_code'),
                'country' => self::getKeyValue($paymentData, 'shipping_country'),
                'phone' => self::getKeyValue($paymentData, 'shipping_phone')
            ]);

            $orderNew = new Order();
            $orderNew->save($tmp);
            return $orderNew;
        }

        return $paymentData['order'];
    }
}