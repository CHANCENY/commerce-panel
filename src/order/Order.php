<?php

namespace Simp\Commerce\order;

use Exception;
use PDO;
use Simp\Commerce\cart\Cart;
use Simp\Commerce\connection\Mail;
use Simp\Commerce\conversion\Conversion;
use Simp\Commerce\customer\Customer;
use Simp\Commerce\payment\CommercePayment;
use Simp\Commerce\product\Product;
use Simp\Commerce\product\ProductAttribute;
use Simp\Commerce\storage\CommerceTemporary;
use Simp\Commerce\store\Store;
use Simp\Router\Router\NotFoundException;

class Order
{
    protected PDO $connection;

    // properties id,user_id,status,currency,subtotal,tax_total,discount_total,shipping_total,grand_total,created_at,updated_at
    protected int $id;
    protected int $user_id;
    protected string $status;
    protected string $currency;
    protected float $subtotal;
    protected float $tax_total;
    protected float $discount_total;
    protected float $shipping_total;
    protected float $grand_total;
    protected int $created_at;
    protected int $updated_at;

    protected array $items;
    protected string $storeId;

    protected AddressInterface $shipping;

    protected AddressInterface $billing;

    protected array $orderTax;
    private string $name;

    /**
     * @var Order[]
     */
    private array $orders = [];

    public function __construct(?int $id = null)
    {
        $this->connection = DB_CONNECTION->connect();

        if ($id) {
            // load order details from a database
            $stmt = $this->connection->prepare("SELECT * FROM commerce_order WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $order = $stmt->fetch();

            if ($order) {
                $this->id = $order->id;
                $this->user_id = $order->user_id;
                $this->status = $order->status;
                $this->currency = $order->currency;
                $this->subtotal = $order->subtotal;
                $this->tax_total = $order->tax_total;
                $this->discount_total = $order->discount_total;
                $this->shipping_total = $order->shipping_total;
                $this->grand_total = $order->grand_total;
                $this->created_at = strtotime($order->created_at);
                $this->updated_at = strtotime($order->updated_at);
                $this->storeId = $order->store_id;

                $items = "SELECT id FROM commerce_order_items WHERE order_id = :id";
                $stmt = $this->connection->prepare($items);
                $stmt->execute([':id' => $id]);

                $this->items = array_map(/**
                 * @throws Exception
                 */ function ($item) { return new OrderItem($item->id); },$stmt->fetchAll());

                $this->billing = BillingAddress::loadByOrder($this->id);
                $this->shipping = Shipping::loadByOrder($this->id);

                $queryTax = "SELECT name, rate, amount FROM commerce_order_taxes WHERE order_id = :id";
                $stmt = $this->connection->prepare($queryTax);
                $stmt->execute([':id' => $id]);
                $this->orderTax = array_map(function ($tax) { return new OrderTax(...$tax); }, $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
        }
        else {
            // initialize all with default values
            $this->id = 0;
            $this->user_id = 0;
            $this->status = 'pending';
            $this->currency = 'USD';
            $this->subtotal = 0.00;
            $this->tax_total = 0.00;
            $this->discount_total = 0.00;
            $this->shipping_total = 0.00;
            $this->grand_total = 0.00;
            $this->created_at = time();
            $this->updated_at = time();
            $this->storeId = 'default';
        }

    }

    /**
     * Create an order from a cart
     * @throws NotFoundException
     */
    public function create($cart_id): int|bool {

        $cart = new Cart()->load(['id'=>$cart_id]);

        if (empty($cart)) {
            throw new NotFoundException("Cart not found");
        }

        $conversion = new Conversion();
        $conversionRate = $conversion->getConversionRate($cart->currency_code);
        // it's likely that the cart has items from different stores, so we need to filter them and group them by store
        $orders = array();
        foreach ($cart->items as $item) {
            $product = Product::load($item->product_id);
            $productAttribute = ProductAttribute::load($item->attribute_id);

            $taxes = $productAttribute->getPrice()->getTaxes();
            $existingOrder = $orders[$product->getStoreId()] ?? [
                'subtotal' => $conversionRate * $item->unit_price * $item->quantity,
                'discount_total' => $conversionRate * $productAttribute->getPrice()->getDiscount(),
                // TODO: shipping_total should be calculated based on the store shipping settings
                'shipping_total' => 0,
                'tax_total' => 0,
                'taxes' => [],
                'order_items' => [
                    $productAttribute->id() => [
                        'product_id' => $product->id(),
                        'attribute_id' => $productAttribute->id(),
                        'name' => $product->getTitle() . " - " . $productAttribute->getName(),
                        'unit_price' => $conversionRate * $item->unit_price,
                        'quantity' => $item->quantity,
                        'total' => $conversionRate * $item->total_price
                    ]
                ]
            ];

            $processTaxes = [];
            foreach ($taxes as $tax) {
                $processTaxes[$tax['name']] = [
                    'rate' => $tax['rate'],
                    'amount' => $conversionRate * $tax['rate'],
                    'name' => $tax['name']
                ];
            }

            if (empty($orders)) {
                $orders[$product->getStoreId()] = $existingOrder;
            }
            else {

                $foundOrder = $orders[$product->getStoreId()];

                // and values to foundOrder from $item
                $foundOrder['subtotal'] += $conversionRate * $item->unit_price * $item->quantity;
                $foundOrder['discount_total'] += $conversionRate * $productAttribute->getPrice()->getDiscount();
                $foundOrder['tax_total'] = 0;
                $foundOrder['order_items'][$productAttribute->id()] = [
                    'product_id' => $product->id(),
                    'attribute_id' => $productAttribute->id(),
                    'name' => $product->getTitle() . " - " . $productAttribute->getName(),
                    'unit_price' => $conversionRate * $item->unit_price,
                    'quantity' => $item->quantity,
                    'total' => $conversionRate * $item->total_price
                ];

                $orders[$product->getStoreId()] = $foundOrder;
            }

            $orders[$product->getStoreId()]['taxes'] = array_merge($orders[$product->getStoreId()]['taxes'], $processTaxes);
            // sum amounts of taxes for each order
            $taxTotal = 0;
            foreach ($orders[$product->getStoreId()]['taxes'] as $tax) {
                $taxTotal += $tax['amount'];
            }
            $orders[$product->getStoreId()]['tax_total'] = $taxTotal;

        }

        $orders['cart'] = [
            'id' => $cart_id,
            'user_id' => $cart->user_id,
            'currency' => $cart->currency_code,
            'note' => $cart->note_data
        ];
        return new CommerceTemporary()->create($orders);
    }

    /**
     * @throws NotFoundException
     */
    public function appendBillingAddress(int $tmp_id, array $billingAddress)
    {
        $order = new CommerceTemporary()->get($tmp_id);

        if (empty($order)) {
            throw new NotFoundException("Order not found");
        }

        $order['billing_address'] = $billingAddress;
        new CommerceTemporary()->update($tmp_id, $order);
        return $this;
    }

    /**
     * @throws NotFoundException
     */
    public function appendShippingAddress(int $tmp_id, array $shippingAddress)
    {
        $order = new CommerceTemporary()->get($tmp_id);

        if (empty($order)) {
            throw new NotFoundException("Order not found");
        }

        $order['shipping_address'] = $shippingAddress;
        new CommerceTemporary()->update($tmp_id, $order);
        return $this;
    }

    /**
     * @throws OrderFailedException
     */
    public function save(int $tmp_id)
    {
        $orders = new CommerceTemporary()->get($tmp_id);
        $stores = STORE_LIST;

        $orders_id = [];
        $bccMails = [];

        foreach ($stores as $store) {
            $store_id = $store['id'];

            $items = $orders[$store_id]['order_items'] ?? [];

            if (!empty($items)) {

                $bccMails[] = $store['contact_email'] ?? '';
                // columns = store_id,user_id,status,currency,subtotal,tax_total,discount_total,shipping_total
                $query_order = "INSERT INTO commerce_order (`store_id`,`user_id`,`status`,`currency`,`subtotal`,`tax_total`,`discount_total`,`shipping_total`) VALUES (:store_id,:user_id,:status,:currency,:subtotal,:tax_total,:discount_total,:shipping_total)";
                $stmt_order = $this->connection->prepare($query_order);
                $result = $stmt_order->execute([
                    ':store_id' => $store_id,
                    ':user_id' => $orders['cart']['user_id'],
                    ':status' => "placed",
                    ':currency' => $orders['cart']['currency'],
                    ':subtotal' => $orders[$store_id]['subtotal'],
                    ':tax_total' => $orders[$store_id]['tax_total'],
                    ':discount_total' => $orders[$store_id]['discount_total'],
                    ':shipping_total' => $orders[$store_id]['shipping_total'],
                ]);

                if (!$result) {
                    throw new OrderFailedException("Order failed to save");
                }

                $id = $this->connection->lastInsertId();
                $orders_id[] = $id;

                // save taxes applied to order
                foreach ($orders[$store_id]['taxes'] as $tax) {
                    $query_tax = "INSERT INTO commerce_order_taxes (`order_id`,`name`,`rate`, `amount`) VALUES (:order_id,:name,:rate, :amount)";
                    $stmt_tax = $this->connection->prepare($query_tax);
                    $stmt_tax->execute([
                        ':order_id' => $id,
                        ':name' => $tax['name'],
                        ':rate' => $tax['rate'],
                        ':amount' => $tax['amount'],
                    ]);
                }

                foreach ($items as $item) {
                    //column order_id,product_id,attribute_id,name,unit_price,quantity,total_price
                    $query_item = "INSERT INTO commerce_order_items (`order_id`, `product_id`, `attribute_id`, `name`, `unit_price`, `quantity`) VALUES (:order_id,:product_id,:attribute_id,:name,:unit_price,:quantity)";
                    $stmt_item = $this->connection->prepare($query_item);
                    $stmt_item->execute([
                        ':order_id' => $id,
                        ':product_id' => $item['product_id'],
                        ':attribute_id' => $item['attribute_id'],
                        ':name' => $item['name'],
                        ':unit_price' => $item['unit_price'],
                        ':quantity' => $item['quantity'],
                    ]);
                }

            }
        }

        // attach the billing_address and shipping_address to the orders

        if (!empty($orders['billing_address'])) {
            foreach ($orders_id as $order_id) {

                // columns order_id,full_name,phone,email,address_line1,address_line2,city,state,postal_code,country
                $billling_query = "INSERT INTO commerce_billing_address (`order_id`, `full_name`, `phone`, `email`, `address_line1`, `address_line2`, `city`,`state`, `postal_code`, `country`) VALUES (:order_id,:full_name,:phone,:email,:address_line1,:address_line2,:city,:state,:postal_code,:country)";
                $billling_stmt = $this->connection->prepare($billling_query);
                $billling_stmt->execute([
                    ':order_id' => $order_id,
                    ':full_name' => $orders['billing_address']['full_name'],
                    ':phone' => $orders['billing_address']['phone'],
                    ':email' => $orders['billing_address']['email'],
                    ':address_line1' => $orders['billing_address']['address_line1'],
                    ':address_line2' => $orders['billing_address']['address_line2'] ?? null,
                    ':city' => $orders['billing_address']['city'],
                    ':state' => $orders['billing_address']['state'],
                    ':postal_code' => $orders['billing_address']['postal_code'],
                    ':country' => $orders['billing_address']['country'],
                ]);
            }
        }

        if (!empty($orders['shipping_address'])) {
            foreach ($orders_id as $order_id) {
                $shipping_query = "INSERT INTO commerce_shipping_address (`order_id`, `full_name`, `phone`, `email`, `address_line1`, `address_line2`, `city`,`state`, `postal_code`, `country`) VALUES (:order_id,:full_name,:phone,:email,:address_line1,:address_line2,:city,:state,:postal_code,:country)";
                $shipping_stat = $this->connection->prepare($shipping_query);
                $shipping_stat->execute([
                    ':order_id' => $order_id,
                    ':full_name' => $orders['billing_address']['full_name'],
                    ':phone' => $orders['billing_address']['phone'],
                    ':email' => $orders['billing_address']['email'],
                    ':address_line1' => $orders['billing_address']['address_line1'],
                    ':address_line2' => $orders['billing_address']['address_line2'] ?? null,
                    ':city' => $orders['billing_address']['city'],
                    ':state' => $orders['billing_address']['state'],
                    ':postal_code' => $orders['billing_address']['postal_code'],
                    ':country' => $orders['billing_address']['country'],
                ]);
            }
        }

        // delete cart
        new Cart()->delete($orders['cart']['id']);

        $customer = new Customer();
        $cust = $customer->load($orders['cart']['user_id']);

        $this->orders = array_map(function ($order_id) { return new Order($order_id); }, $orders_id);

        // call the callback function
        $callback = _CALLBACK['order_confirmation'];
        if (!empty($callback)) {

            foreach ($this->orders as $order) {
                $callbackObject = new $callback;
                $emailBody = $callbackObject->sendConfirmation(
                    order: $order,
                    customer: $cust,
                    store: new Store($order->getStoreId())
                );
                $bccMails = array_filter($bccMails);
                new Mail()->send(
                    $cust['email'],
                    $emailBody->subject,
                    $emailBody->body,
                    toName: ucfirst($cust['first_name'] ?? '') . " " . ucfirst($cust['last_name'] ?? ''),
                    attachments: $emailBody->attachments ?? [],
                    bcc: $bccMails
                );
            }
        }


        return new CommerceTemporary()->delete($tmp_id);
    }

    public function getOrdersByStore(string $store_id): array
    {
        $query = "SELECT id FROM commerce_order WHERE store_id = :store_id ORDER BY created_at DESC";
        $stmt = $this->connection->prepare($query);
        $stmt->execute([
            ':store_id' => $store_id
        ]);
        return array_map(function ($order){  return new Order($order->id); },$stmt->fetchAll());

    }

    public function getAllOrders(): array
    {
        $query = "SELECT id FROM commerce_order ORDER BY created_at DESC";
        $stmt = $this->connection->prepare($query);
        $stmt->execute();
        return array_map(function ($order){  return new Order($order->id); },$stmt->fetchAll());
    }

    public function id()
    {
        return $this->id;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->user_id;
    }

    public function getCustomer()
    {
        return new Customer()->load($this->user_id);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function getTaxTotal(): float
    {
        return $this->tax_total;
    }

    public function getDiscountTotal(): float
    {
        return $this->discount_total;
    }

    public function getShippingTotal(): float
    {
        return $this->shipping_total;
    }

    public function getGrandTotal(): float
    {
        return $this->grand_total;
    }

    public function getCreatedAt(): int
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): int
    {
        return $this->updated_at;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getShipping(): AddressInterface
    {
        return $this->shipping;
    }

    public function getBilling(): AddressInterface
    {
        return $this->billing;
    }

    public function getOrderTax(): array
    {
        return $this->orderTax;
    }

    public function deleteOrder(): bool
    {
        $query = "DELETE FROM commerce_order WHERE id = :id";
        $stmt = $this->connection->prepare($query);
        return $stmt->execute([':id' => $this->id]);
    }

    public function sendOrderConfirmation(): bool
    {
        return true;
    }

    /**
     * @throws OrderFailedException
     */
    public function updateStatus(mixed $status)
    {
        $this->status = $status;
        $query = "UPDATE commerce_order SET status = :status WHERE id = :id";
        $stmt = $this->connection->prepare($query);
        return $stmt->execute([':status' => $this->status, ':id' => $this->id]);
    }

    public function getStoreId()
    {
        return $this->storeId;
    }

    public function getStore(): Store
    {
        return new Store($this->storeId);
    }

    /**
     * Retrieves the list of orders.
     *
     * @return array The array containing all the orders.
     */
    public function getOrders(): array
    {

        return $this->orders ?? [];
    }

    public function getPayment(): ?CommercePayment
    {
        return CommercePayment::loadByOrderId($this->id);
    }
}