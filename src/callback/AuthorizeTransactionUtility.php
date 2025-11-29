<?php

namespace Simp\Commerce\callback;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\contract\v1\GetTransactionDetailsResponse;
use net\authorize\api\controller as AnetController;

class AuthorizeTransactionUtility
{
    protected string $transactionKey;
    protected string $loginId;
    protected string $env;
    protected string $clientKey;
    protected string $signatureKey;

    public function __construct()
    {
        $this->transactionKey = $_ENV['AUTHORIZE_TRANSACTION_KEY'] ?? '';
        $this->loginId = $_ENV['AUTHORIZE_LOGIN_ID'] ?? '';
        $this->env = $_ENV['AUTHORIZE_INV'] ?? 'sandbox';
        $this->clientKey = $_ENV['AUTHORIZE_PUBLIC_KEY'] ?? '';
        $this->signatureKey = $_ENV['AUTHORIZE_SIGNATURE_KEY'] ?? '';
    }

    protected function ensureCredentials(): void
    {
        if (empty($this->transactionKey) || empty($this->loginId)) {
            throw new \RuntimeException(
                'Authorize.Net credentials missing. Please set AUTHORIZE_TRANSACTION_KEY and AUTHORIZE_LOGIN_ID.'
            );
        }
    }

    protected function buildAuth(): AnetAPI\MerchantAuthenticationType
    {
        $this->ensureCredentials();

        $auth = new AnetAPI\MerchantAuthenticationType();
        $auth->setName($this->loginId);
        $auth->setTransactionKey($this->transactionKey);
        return $auth;
    }

    /**
     * Create charge from payment nonce
     */
    public function chargeWithNonce(string $opaqueData, float $amount): array
    {
        $payment = new AnetAPI\PaymentType();
        $opaque = new AnetAPI\OpaqueDataType();
        $opaque->setDataDescriptor("COMMON.ACCEPT.INAPP.PAYMENT");
        $opaque->setDataValue($opaqueData);
        $payment->setOpaqueData($opaque);

        $transactionRequest = new AnetAPI\TransactionRequestType();
        $transactionRequest->setTransactionType("authCaptureTransaction");
        $transactionRequest->setAmount($amount);
        $transactionRequest->setPayment($payment);

        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->buildAuth());
        $request->setTransactionRequest($transactionRequest);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse($this->env === "production" ?
            \net\authorize\api\constants\ANetEnvironment::PRODUCTION :
        \net\authorize\api\constants\ANetEnvironment::SANDBOX);

        return $this->formatResponse($response);
    }

    /**
     * Refund transaction
     */
    public function refund(string $transactionId, float $amount, string $cardLast4): array
    {
        $creditCard = new AnetAPI\CreditCardType();
        $creditCard->setCardNumber($cardLast4);
        $creditCard->setExpirationDate("XXXX");

        $payment = new AnetAPI\PaymentType();
        $payment->setCreditCard($creditCard);

        $transactionRequest = new AnetAPI\TransactionRequestType();
        $transactionRequest->setTransactionType("refundTransaction");
        $transactionRequest->setAmount($amount);
        $transactionRequest->setPayment($payment);
        $transactionRequest->setRefTransId($transactionId);

        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->buildAuth());
        $request->setTransactionRequest($transactionRequest);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse(
            $this->env === 'production' ?
                \net\authorize\api\constants\ANetEnvironment::PRODUCTION :
                \net\authorize\api\constants\ANetEnvironment::SANDBOX);

        return $this->formatResponse($response);
    }

    /**
     * Void a transaction before settlement
     */
    public function void(string $transactionId): array
    {
        $transactionRequest = new AnetAPI\TransactionRequestType();
        $transactionRequest->setTransactionType("voidTransaction");
        $transactionRequest->setRefTransId($transactionId);

        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->buildAuth());
        $request->setTransactionRequest($transactionRequest);

        $controller = new AnetController\CreateTransactionController($request);
        $response = $controller->executeWithApiResponse(
            $this->env === 'production' ?
                \net\authorize\api\constants\ANetEnvironment::PRODUCTION :
                \net\authorize\api\constants\ANetEnvironment::SANDBOX);

        return $this->formatResponse($response);
    }

    /**
     * Get transaction details
     */
    public function getTransactionDetails(string $transactionId): array
    {
        $request = new AnetAPI\GetTransactionDetailsRequest();
        $request->setMerchantAuthentication($this->buildAuth());
        $request->setTransId($transactionId);

        $controller = new AnetController\GetTransactionDetailsController($request);
        $response = $controller->executeWithApiResponse(
            $this->env === 'production' ?
                \net\authorize\api\constants\ANetEnvironment::PRODUCTION :
        \net\authorize\api\constants\ANetEnvironment::SANDBOX);

        return $this->formatResponse($response);
    }

    /**
     * Format API response to array
     */
    protected function formatResponse($response): array
    {
        if ($response === null) {
            return [
                'status' => 'error',
                'message' => 'Null response from API'
            ];
        }

        if ($response->getMessages()->getResultCode() === "Ok") {
            $card = $response->getTransaction()->getPayment()->getCreditCard();

            return [
                'status' => 'success',
                'transaction_id' => $response?->getTransaction()->getTransId(),
                'auth_code' => $response?->getTransaction()->getAuthCode(),
                'raw' => $response->getTransaction()->jsonSerialize(),
                'card' =>$card->jsonSerialize()
            ];
        }

        $error = $response->getMessages()->getMessage()[0] ?? null;

        return [
            'status' => 'error',
            'code' => $error?->getCode() ?? '',
            'message' => $error?->getText() ?? 'Unknown error',
            'raw' => $response
        ];
    }
}
