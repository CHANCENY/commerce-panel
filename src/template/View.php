<?php

namespace Simp\Commerce\template;

use DateTime;
use Simp\Commerce\assets\CommerceAsset;
use Simp\Commerce\cart\Cart;
use Simp\Commerce\commerce_panel\CommerceSummary;
use Simp\Commerce\conversion\Conversion;
use Simp\Commerce\customer\Customer;
use Simp\Commerce\product\Price;
use Simp\Commerce\product\Product;
use Simp\Commerce\product\ProductAttribute;
use Simp\Commerce\token\Token;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extra\String\StringExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class View
{

    protected Environment $twig;

    protected string $templateRendererString;

    public function __construct()
    {
        // twig settings

        $request = Request::createFromGlobals();

        // defaults options for twig
        $defaults = [
            'request' => $request,
            'host' => $request->getHttpHost(),
            'path' => $request->getPathInfo(),
            'scheme' => $request->getScheme(),
            'base_url' => $request->getBaseUrl(),
            'is_secure' => $request->isSecure(),
            'is_ajax' => $request->isXmlHttpRequest(),
            'domain' => $request->getSchemeAndHttpHost(),
            'current_url' => $request->getUri(),
            'current_url_path' => $request->getPathInfo(),
            'current_url_query' => $request->getQueryString(),
            'requesting_uri' => $request->getRequestUri(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
            'referer' => $request->headers->get('referer'),
            'is_login' => CURRENT_USER->isLogin(),
            'user_id' => CURRENT_USER->id(),
            'user_name' => CURRENT_USER->username(),
            'user_email' => CURRENT_USER->email(),
            'page_description' => $options['page_description'] ?? '',
            'page_keywords' => $options['page_keywords'] ?? '',
            'page_author' => $options['page_author'] ?? '',
            'page_copyright' => $options['page_copyright'] ?? '',
            'page_robots' => $options['page_robots'] ?? '',
            'page_canonical' => $options['page_canonical'] ?? $request->getUri(),
        ];

        $loader = new \Twig\Loader\FilesystemLoader(TEMPLATE_ROOT);
        $twig = new \Twig\Environment($loader, [
            'cache' => false,
            'debug' => false,
        ]);
        $twig->addGlobal('options', $defaults);
        $twig->addGlobal('current_user', CURRENT_USER);
        $twig->addGlobal('request', $request);
        $twig->addGlobal('user', CURRENT_USER);
        $twig->addGlobal('STORE_LIST', STORE_LIST);
        $twig->addGlobal('PRODUCT_CATEGORIES', PRODUCT_CATEGORIES);
        $twig->addGlobal("CURRENCIES", CURRENCY_LIST);
        $twig->addGlobal("CURRENCY_SET", CURRENCY_SET);
        $twig->addGlobal("SIZES", SIZES);

        $store_products_list = [];
        $count = 0;
        $batch = uniqid();
        foreach (STORE_LIST as $key=>$store) {
            if ($count < 6) {
                $store_products_list['batch_'.$batch][] = [
                    'link' => "/store/{$store['id']}/products",
                    'name' => $store['name'],
                ];
                $count++;
            }
            else {
                $count = 0;
                $batch = uniqid();
            }
        }
        $twig->addGlobal("STORE_PRODUCTS_LIST", $store_products_list);

        $create_products = [];
        foreach (STORE_LIST as $key=>$store) {
            $create_products[] = [
                'link' => "/store/{$store['id']}/create-product",
                'name' => "Create for ". $store['name'],
            ];
        }
        $twig->addGlobal("CREATE_PRODUCTS", $create_products);

        $attributes = [];
        foreach (STORE_LIST as $key=>$store) {

            if ($count < 6) {
                $attributes['batch_'.$batch][] = [
                    'link' => "/store/{$store['id']}/variations",
                    'name' => $store['name']. " Variations List",
                ];
                $count++;
            }
            else {
                $count = 0;
                $batch = uniqid();
            }
        }
        $twig->addGlobal("ATTRIBUTES", $attributes);
        $twig->addGlobal("DELIVERY_TYPE",DELIVERY_TYPE);
        $twig->addGlobal('_MENUS', _MENUS);

        $store_orders = [];
        foreach (STORE_LIST as $key=>$store) {
            $store_orders[] = [
                'link' => "/store/{$store['id']}/orders",
                'name' => $store['name']. " Orders",
            ];
        }
        $commerceAssets = new CommerceAsset();
        $twig->addGlobal("STORE_ORDERS", $store_orders);
        $twig->addGlobal("CSS", $commerceAssets->getContentCss());
        $twig->addGlobal("JS", $commerceAssets->getContentJs());

        $payments = [];
        foreach (STORE_LIST as $key=>$store) {
            $payments[] = [
                'link' => "/store/{$store['id']}/payments",
                'name' => $store['name']. " Payments",
            ];
        }
        $twig->addGlobal("PAYMENTS", $payments);

        $twig->addExtension(new \Twig\Extension\DebugExtension());
        $twig->addExtension(new \Twig\Extension\StringLoaderExtension());

        $twig->addFunction(new TwigFunction('url', [$this, 'url']));
        $twig->addFunction(new TwigFunction('show', [$this, 'show']));
        $twig->addFunction(new TwigFunction('country', [$this, 'country']));
        $twig->addFilter(new \Twig\TwigFilter('currency_symbol', function ($currency = 'USD') {
            $found = array_filter(CURRENCY_LIST, function($item) use ($currency) {
                return strtolower($item['code']) === strtolower($currency);
            });
            return reset($found)['symbol'] ?? $currency;
        }));
        $twig->addFilter(new \Twig\TwigFilter('currency_name', function ($currency = 'USD') {

            $found = array_filter(CURRENCY_LIST, function($item) use ($currency) {
                return strtolower($item['code']) === strtolower($currency);
            });
            return reset($found)['name'] ?? $currency;
        }));
        $twig->addFilter(new \Twig\TwigFilter('encode', function ($string) {
            return base64_encode($string);
        }));
        $twig->addFunction(new TwigFunction('in_array', function ($array, $key) {
            if (!is_array($array)) {
                return false;
            }
            return in_array($key, $array);
        }));
        $twig->addFilter(new \Twig\TwigFilter('customer_email', function ($value) {
            $customer = new Customer();
            $thisCustomer = $customer->load($value);
            return $thisCustomer['email'] ?? $value;
        }));
        $twig->addFilter(new \Twig\TwigFilter('customer_name', function ($value) {
            $customer = new Customer();
            $thisCustomer = $customer->load($value);
            return $thisCustomer['first_name'] . " ". $thisCustomer['last_name'];
        }));
        $twig->addFilter(new \Twig\TwigFilter('customer_phone', function ($value) {
            $customer = new Customer();
            $thisCustomer = $customer->load($value);
            return $thisCustomer['phone'] ?? $value;
        }));
        $twig->addFilter(new \Twig\TwigFilter('customer_address', function ($value) {
            $customer = new Customer();
            $thisCustomer = $customer->load($value);
            return [
                'address_line1' => $thisCustomer['address_line1'] ?? '',
                'address_line2' => $thisCustomer['address_line2'] ?? '',
                'city' => $thisCustomer['city'] ?? '',
                'state' => $thisCustomer['state'] ?? '',
                'postal_code' => $thisCustomer['postal_code'] ?? '',
                'country' => $thisCustomer['country'] ?? '',
            ];
        }));
        $twig->addFunction(new TwigFunction('currency_format', function (string|int $amount, string $currency = 'USD') {
            $found = array_filter(CURRENCY_LIST, function($item) use ($currency) {
                return strtolower($item['code']) === strtolower($currency);
            });
            return  (reset($found)['symbol'] ?? $currency) . " ". number_format($amount, 2, '.', ',') ;
        }));

        $twig->addFunction(new TwigFunction('get_product', function ($product_id) {
            return Product::load($product_id);
        }));
        $twig->addFunction(new TwigFunction('get_attribute', function ($attr) {
            return ProductAttribute::load($attr);
        }));
        $twig->addFunction(new TwigFunction('get_amount_formatted', function (string|int $amount, string $currency = 'USD', string $base_currency= "") {
            $baseCurrency = $base_currency ?: CURRENCY_SET;

            $conversion = new Conversion();
            $objectConversion = $conversion->getConversionObject($currency, $baseCurrency);
            $found = array_filter(CURRENCY_LIST, function($item) use ($objectConversion) {
                return strtolower($item['code']) === strtolower($objectConversion->code);
            });
            $found = reset($found);
            return  ($found['symbol'] ?? $currency) . number_format($amount * $objectConversion->rate, 2, '.', ',') ;

        }));
        $twig->addFilter(new \Twig\TwigFilter('numerical', function ($string) {
            return preg_replace('/[^0-9]/', '', $string);
        }));
        $twig->addFunction(new TwigFunction('get_cart_taxes', function ($cart_id) {
            return new Cart()->getCartTaxes($cart_id);
        }));
        $twig->addFunction(new TwigFunction('cart_total',function ($cart_id){
            return new Cart()->getAllTotalAmount($cart_id);
        }));
        $twig->addFilter(new TwigFilter('sales_percentage', function (int|float $amount, string $status) {
            // Get last month
            $now = new DateTime();
            $now->modify('last month');

            // Fetch last month's summary for the given status
            $summary = new CommerceSummary();
            $data = $summary->getSummaryOf($now->format('Y'), $now->format('m'), $status);

            $lastMonthTotal = $data['grand_total'] ?? 0;

            if ($lastMonthTotal == 0) {
                return '+100%';
            }

            // Calculate percentage change
            $change = (($amount - $lastMonthTotal) / $lastMonthTotal) * 100;

            // Add + or - sign
            $sign = $change >= 0 ? '+' : '';

            // Round to 1 decimal place and return
            return $sign . round($change, 1) . '%';
        }));
        $twig->addFilter(new TwigFilter('sales_last_month', function (string $status) {
            // Get last month
            $now = new DateTime();
            $now->modify('last month');

            // Fetch last month's summary for the given status
            $summary = new CommerceSummary();
            $data = $summary->getSummaryOf($now->format('Y'), $now->format('m'), $status);
            if (empty($data['grand_total'])) {
                $data['grand_total'] = 0;
            }
            return $data;

        }));
        $twig->addFunction(new TwigFunction('last_year', function (string $status) {
            // Get last month
            $now = new DateTime();
            $now->modify('last year');

            // Fetch last month's summary for the given status
            $summary = new CommerceSummary();
            $data = $summary->getSummaryYearOf($now->format('Y'), $status);
            if (empty($data['grand_total'])) {
                $data['grand_total'] = 0;
            }
            return $data;

        }));
        $twig->addFunction(new TwigFunction('token_replace', function ($token, $values) {
            return new Token()->replace($token, $values);
        }));


        $twig->addExtension(new StringExtension());

        $this->twig = $twig;
    }

    public function __toString(): string
    {
        return $this->templateRendererString;
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    public function render(string $templatePath, array $options = []): string
    {
        return $this->twig->render($templatePath, $options);
    }

    public function show(...$args)
    {
        dump(...$args);
    }

    public function country($code)
    {
        $country = array_filter(COUNTRIES, function($country) use ($code) {
            return strtolower($country['code']) === strtolower($code);
        });
        return reset($country);
    }

}