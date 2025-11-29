<?php

namespace Simp\Commerce\payment\types;

use DateTime;
use InvalidArgumentException;
use net\authorize\api\contract\v1\CreateTransactionRequest;
use net\authorize\api\contract\v1\CreditCardType;
use net\authorize\api\contract\v1\CustomerAddressType;
use net\authorize\api\contract\v1\MerchantAuthenticationType;
use net\authorize\api\contract\v1\PaymentType;
use net\authorize\api\contract\v1\TransactionRequestType;
use net\authorize\api\contract\v1\TransactionResponseType;
use net\authorize\api\controller\CreateTransactionController;
use RuntimeException;
use Simp\Commerce\callback\AuthorizeTransactionUtility;
use Simp\Commerce\conversion\Conversion;
use Simp\Commerce\order\Order;
use Simp\Commerce\order\OrderFailedException;
use Simp\Commerce\payment\CommercePayment;
use Simp\Commerce\payment\PaymentGatWayAbstract;
use Simp\Router\Router\NotFoundException;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class CreditCardPayment extends PaymentGatWayAbstract
{

    public array $errors = [];
    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function getPaymentForm(array $options = []): string
    {
        return VIEW->render('p/credit_card_payment_form.twig', $options);
    }

    public function getGateWayConfiguration(): array
    {
        return [
            "login_id" => $_ENV['AUTHORIZE_LOGIN_ID'],
            'transaction_key' => $_ENV['AUTHORIZE_TRANSACTION_KEY'],
            'public_key' => $_ENV['AUTHORIZE_PUBLIC_KEY'],
            'signature' => $_ENV['AUTHORIZE_SIGNATURE_KEY'],
            'environment' => $_ENV['AUTHORIZE_INV'] ?? 'sandbox'
        ];
    }

    public function getGatewayName(): string
    {
       return "Credit Card";
    }

    public function getGatewayLogos(): array
    {
        return [
            "https://www.authorize.net/resources/our-features/payment-types/_jcr_content/root/container/responsivegrid/contentwell/contentWell/contentwell_copy/contentWell/columncontrol/par2/contentwell_67198405/contentWell/contentwell_90767490/contentWell/contentwell_15100714/contentWell/imagecontainer/images/image.img.png/1755031971863.png",
            "https://www.authorize.net/resources/our-features/payment-types/_jcr_content/root/container/responsivegrid/contentwell/contentWell/contentwell_copy/contentWell/columncontrol/par2/contentwell_67198405/contentWell/contentwell_90767490/contentWell/contentwell_15100714/contentWell/imagecontainer/images/image_copy_596338784.img.png/1713316009955.png",
            "https://www.authorize.net/resources/our-features/payment-types/_jcr_content/root/container/responsivegrid/contentwell/contentWell/contentwell_copy/contentWell/columncontrol/par2/contentwell_67198405/contentWell/contentwell_90767490/contentWell/contentwell_15100714_905394501/contentWell/imagecontainer/images/image_1553441213.img.png/1755031944389.png",
            "https://www.authorize.net/resources/our-features/payment-types/_jcr_content/root/container/responsivegrid/contentwell/contentWell/contentwell_copy/contentWell/columncontrol/par2/contentwell_67198405/contentWell/contentwell_90767490/contentWell/contentwell_15100714/contentWell/imagecontainer/images/image_copy_1356785015.img.png/1713316002949.png",
            "https://www.authorize.net/resources/our-features/payment-types/_jcr_content/root/container/responsivegrid/contentwell/contentWell/contentwell_copy/contentWell/columncontrol/par2/contentwell_67198405/contentWell/contentwell_90767490/contentWell/contentwell_15100714_905394501/contentWell/imagecontainer/images/image_copy_135678501.img.png/1713316042855.png",
            "https://www.authorize.net/resources/our-features/payment-types/_jcr_content/root/container/responsivegrid/contentwell/contentWell/contentwell_copy/contentWell/columncontrol/par2/contentwell_67198405/contentWell/contentwell_90767490/contentWell/contentwell_15100714_905394501/contentWell/imagecontainer/images/image_copy_213784271.img.png/1713316037031.png",
        ];
    }

    public function getGatewayDescription(): string
    {
        return "Supported card Visa, MasterCard, Discover, American Express, JCB";
    }

    public function getGatewayId(): string
    {
        return "credit_card";
    }

    public function isEnabled(): bool
    {
        return $_ENV['CARD_PAYMENT'] ?? false;
    }

    /**
     * @throws OrderFailedException
     * @throws NotFoundException
     */
    public function processPayment(array $paymentData): bool
    {
        // needed keys
        $requiredKeys = [
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
            'amount',
            'currency',
            'card_name',
            'card_number',
            'exp_month',
            'exp_year',
            'cvv'
        ];

        foreach ($requiredKeys as $key) {

            if (!self::dataValidation($paymentData, $key)) {
                $this->errors[] = "Missing required data: {$key}";
            }
        }

        if (!empty($this->errors)) {
            return false;
        }

        $response = $this->doChargeCard(
            self::getKeyValue($paymentData, 'amount'),
            self::getKeyValue($paymentData, 'card_number'),
            self::getKeyValue($paymentData, 'exp_month'),
            self::getKeyValue($paymentData, 'exp_year'),
            self::getKeyValue($paymentData, 'cvv'),
            self::getKeyValue($paymentData, 'currency'),
            $paymentData
        );

        if ($response['status'] === 'success') {

            $order = $this->saveOrder($paymentData);
            $orders = !empty($order->getOrders()) ? $order->getOrders() : [$order];

            if ($orders) {

                foreach ($orders as $order) {
                    $payment = $order->getPayment();

                    if ($payment instanceof CommercePayment) {
                        $payment->setStatus('completed');
                        $payment->setTransactionId($response['transactionId']);
                        $payment->setMethod($this->getGatewayId());
                    }
                    else {
                        $payment = new CommercePayment();
                        $payment->setOrderId($order->id());
                        $payment->setAmount($response['amount']);
                        $payment->setStatus('paid');
                        $payment->setCurrency($response['currency']);
                        $payment->setMethod($this->getGatewayId());
                        $payment->setTransactionId($response['transactionId']);
                    }
                    $payment->save();

                    $utility = new AuthorizeTransactionUtility();
                    $detail = $utility->getTransactionDetails($response['transactionId']);
                    $payment = CommercePayment::getPaymentByTransactionId($response['transactionId']);

                    if ($detail && $payment) {

                        $query = "INSERT INTO commerce_payment_details (payment_id, card_number, expiration_date, card_type) VALUES (?, ?, ?,?)";
                        $stmt = DB_CONNECTION->connect()->prepare($query);
                        $stmt->execute([
                            $payment->id(),
                            $detail['card']['cardNumber'],
                            $detail['card']['expirationDate'],
                            $detail['card']['cardType'],
                        ]);
                        return true;

                    }

                }
            }

        }

        return false;
    }

    /**
     * Charges a credit card for a given amount in a specified currency.
     *
     * @param float $amount The amount to charge.
     * @param string $cardNumber The credit card number.
     * @param string $expMonth The expiration month of the credit card (format: MM).
     * @param string $expYear The expiration year of the credit card (format: YYYY).
     * @param string $cvv The CVV code of the credit card.
     * @param string $currencyCode The ISO currency code for the transaction.
     * @param array $billing Billing information, including keys such as 'billing_first_name',
     *                       'billing_last_name', 'billing_address_line1', 'billing_city',
     *                       'billing_state', 'billing_postal_code', and 'billing_country'.
     *
     * @return array An associative array containing the status of the transaction ('success' or 'error'),
     *               and additional details such as transaction ID, response code, message,
     *               amount, currency, or error details (code and message).
     *
     * @throws RuntimeException If the gateway configuration is missing or invalid.
     * @throws InvalidArgumentException If the input parameters are invalid.
     */
    public function doChargeCard($amount, $cardNumber, $expMonth, $expYear, $cvv, $currencyCode, array $billingInformation): array
    {
        $configuration = $this->getGateWayConfiguration();
        if (empty($configuration['login_id']) || empty($configuration['transaction_key']) || empty($configuration['environment'])) {
            return ['status' => 'error', 'message' => 'Gateway configuration not found'];
        }
        // Your Authorize.net credentials
        $merchantAuthentication = new MerchantAuthenticationType();
        $merchantAuthentication->setName($configuration['login_id']);
        $merchantAuthentication->setTransactionKey($configuration['transaction_key']);

        // check if currency is supported by Authorize.net
        if (!in_array(strtoupper($currencyCode), AUTHORIZE_SUPPORTED_CURRENCIES['currencies'])) {
            $default = AUTHORIZE_SUPPORTED_CURRENCIES['default'];
            $conversion = new Conversion();
            $amount = $conversion->getConversionRate($default, $currencyCode) * floatval($amount);
            $currencyCode = $default;
        }

        // Create credit card object
        $creditCard = new CreditCardType();
        $creditCard->setCardNumber($cardNumber);
        $creditCard->setExpirationDate("$expYear-$expMonth");  // yyyy-mm
        $creditCard->setCardCode($cvv);

        // Payment data
        $paymentOne = new PaymentType();
        $paymentOne->setCreditCard($creditCard);

        // Transaction Request
        $transactionRequest = new TransactionRequestType();
        $transactionRequest->setTransactionType("authCaptureTransaction");
        $transactionRequest->setAmount($amount);
        $transactionRequest->setCurrencyCode($currencyCode);
        $transactionRequest->setPayment($paymentOne);

        // Billing info
        if (!empty($billing)) {
            $billing = new CustomerAddressType();
            $spitted = explode(' ', self::getKeyValue($billingInformation, 'billing_full_name'));
            $billing->setFirstName($spitted[0] ?? '');
            $billing->setLastName(end($spitted) ?? '');
            $billing->setAddress(self::getKeyValue($billingInformation, 'billing_address_line1'));
            $billing->setCity(self::getKeyValue($billingInformation, 'billing_city'));
            $billing->setState(self::getKeyValue($billingInformation, 'billing_state'));
            $billing->setZip(self::getKeyValue($billingInformation, 'billing_postal_code'));
            $billing->setCountry(self::getKeyValue($billingInformation, 'billing_country'));
            $transactionRequest->setBillTo($billing);
        }


        // Create API request
        $request = new CreateTransactionRequest();
        $request->setMerchantAuthentication($merchantAuthentication);
        $request->setRefId("ref" . time());
        $request->setTransactionRequest($transactionRequest);

        // Execute request
        $controller = new CreateTransactionController($request);

        $response = null;
        if ($configuration['environment'] === 'production') {
            $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::PRODUCTION);
        }
        else {
            $response = $controller->executeWithApiResponse(\net\authorize\api\constants\ANetEnvironment::SANDBOX);
        }

        if ($response !== null) {
            if ($response->getMessages()->getResultCode() === "Ok") {

                /**@var TransactionResponseType $tresponse */
                $tresponse = $response->getTransactionResponse();

                if ($tresponse !== null && $tresponse->getMessages() !== null) {
                    return [
                        'status'        => 'success',
                        'transactionId' => $tresponse->getTransId(),
                        'responseCode'  => $tresponse->getResponseCode(),
                        'message'       => $tresponse->getMessages()[0]->getDescription(),
                        'amount'        => $amount,
                        'currency'      => $currencyCode,
                    ];
                }
            }

            // Handle failure
            return [
                'status' => 'error',
                'code'   => $response->getMessages()->getMessage()[0]->getCode(),
                'text'   => $response->getMessages()->getMessage()[0]->getText()
            ];
        }

        return ['status' => 'error', 'message' => 'No response received'];
    }
}