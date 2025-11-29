<?php

namespace Simp\Commerce\commerce_panel;

use DateTime;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Simp\Commerce\account\User;
use Simp\Commerce\cart\Cart;
use Simp\Commerce\connection\Mail;
use Simp\Commerce\conversion\Conversion;
use Simp\Commerce\customer\Customer;
use Simp\Commerce\invoice\InvoiceFileManager;
use Simp\Commerce\order\Order;
use Simp\Commerce\payment\CommercePayment;
use Simp\Commerce\payment\PaymentGatWayAbstract;
use Simp\Commerce\payment\PaymentGetWayInterface;
use Simp\Commerce\product\Price;
use Simp\Commerce\product\Product;
use Simp\Commerce\product\ProductAttribute;
use Simp\Commerce\product\ProductAttributes;
use Simp\Commerce\product\Products;
use Simp\Commerce\store\Store;
use Simp\Commerce\template\View;
use Simp\Commerce\todo\CommerceTodoTask;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class CommercePanelController
{
    protected View $view;

    public function __construct()
    {
        $this->view = VIEW;
    }

    public function index(...$args): Response
    {
        $summary = new CommerceSummary();
        $orders = $summary->orders();

        $now = new DateTime();
        $month = $now->format('m');
        $year = $now->format('Y');

        $thisMonthSummary = $summary->groupInPairs($summary->getThisMonthSummary($year, $month));

        $now->modify('last year');

        $quickSummary = $summary->getQuickSummaryOfPast($now->format('Y'), 'completed');

        $todoList = new CommerceTodoTask()->getTasks();

        return new Response($this->view->render('index.twig',[
            'orders' => $orders,
            'thisMonthSummaries' => $thisMonthSummary,
            'quickSummaries' => $quickSummary,
            'todoList' => $todoList,
        ]));
    }

    public function storeProducts(...$args): Response
    {
        extract($args);
        $products = new Products();
        $all = $products->byStore($request->query->get('store_id'), false);
        $store = new Store($request->query->get('store_id'));

        return new Response($this->view->render('p/store_products.twig', [
            'products' => $all,
            'store' => $store,
        ]));
    }

    public function storeEditProduct(...$args): Response
    {
        extract($args);
        $store = new Store($request->query->get('store_id'));
        $product = Product::load($request->query->get('pro_id'));

        if ($request->getMethod() === 'POST') {
            $product_data = $request->request->all();
            $images = json_decode($product_data['img_hd'], true);
            $files = array_map(function ($file) {
                return $file['path'];
            }, $images);
            $data = [
                'id' => $product->id(),
                'title' => $product_data['title'],
                'description' => $product_data['description'],
                'category' => $product_data['category'],
                'images' => $files,
                'sku' => $product_data['sku'],
                'is_active' => isset($product_data['status']) && $product_data['status'] === '1' ? 1 : 0,
                'store_id' => $product->getStoreId(),
                'created_at' => $product->getCreatedAt(),
            ];

            $product = new Product($data);
            if ($product->save()) {
                $message = [
                    'type' => 'Success',
                    'content' => 'Product updated successfully',
                    'class' => 'success'
                ];
            } else {
                $message = [
                    'type' => 'Error',
                    'content' => 'Something went wrong',
                    'class' => 'danger'
                ];
            }
        }

        $images = [];
        foreach ($product->getImages() as $image) {
            $info = pathinfo($image);
            $exten = mime_content_type($_ENV['UPLOAD_DIR'] . '/' . $info['basename']);
            $images[] = [
                'path' => $image,
                'size' => filesize($_ENV['UPLOAD_DIR'] . '/' . $info['basename']),
                'name' => $info['basename'],
                'type' => $exten,
            ];
        }
        $product->setImages($images);
        return new Response($this->view->render('p/store_edit_product.twig', [
            'store' => $store,
            'product' => $product,
            'categories' => PRODUCT_CATEGORIES,
            'message' => $message ?? [],
        ]));
    }

    public function storeFileUploads(...$args): JsonResponse
    {
        extract($args);

        $files = $request->files->all();

        $storage_path = $_ENV['UPLOAD_DIR'];

        if (!is_dir($storage_path)) {
            mkdir($storage_path, 0777, true);
        }


        $links = [];
        $files = reset($files);
        foreach ($files as $file) {
            /**@var $file UploadedFile */

            // 10 mb or less
            $max_size = 10 * 1024 * 1024;
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 'application/json'];

            if ($file->isValid() && $file->getSize() <= $max_size && in_array($file->getMimeType(), $allowed_types)) {
                $result = $file->move($storage_path, $file->getClientOriginalName());
                $links[] = [
                    'name' => $result->getFilename(),
                    'size' => $result->getSize(),
                    'type' => $result->getMimeType(),
                    'path' => $_ENV['FILE_WEB_ACCESS'] . trim($result->getFilename(), '/')
                ];
            }
        }
        return new JsonResponse(['files' => $links]);
    }

    public function storeFileDelete(...$args): JsonResponse
    {
        extract($args);
        $file = $request->query->get('file');
        $storage_path = $_ENV['UPLOAD_DIR'];
        $path = $storage_path . '/' . $file;
        if (file_exists($path)) {
            unlink($path);
        }
        return new JsonResponse(['success' => true]);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function storeView(...$args): Response
    {
        extract($args);
        $store = new Store($request->query->get('store_id'));
        return new Response($this->view->render('p/store_view.twig', [
            'store' => $store,
        ]));
    }

    public function storeCreateProduct(...$args): Response
    {
        extract($args);
        $store = new Store($request->query->get('store_id'));

        if ($request->getMethod() === 'POST') {
            $product_data = $request->request->all();

            $product = new Product([
                'title' => $product_data['title'],
                'description' => $product_data['description'],
                'category' => $product_data['category'],
                'sku' => $product_data['sku'],
                'is_active' => isset($product_data['status']) && $product_data['status'] === '1' ? 1 : 0,
                'images' => array_map(function ($file) {
                    return $file['path'];
                }, json_decode($product_data['img_hd'], true)),
            ]);
            if ($product->save()) {
                $message = [
                    'type' => 'Success',
                    'content' => 'Product created successfully',
                    'class' => 'success'
                ];
                $images = [];
                foreach ($product->getImages() as $image) {
                    $info = pathinfo($image);
                    $exten = mime_content_type($_ENV['UPLOAD_DIR'] . '/' . $info['basename']);
                    $images[] = [
                        'path' => $image,
                        'size' => filesize($_ENV['UPLOAD_DIR'] . '/' . $info['basename']),
                        'name' => $info['basename'],
                        'type' => $exten,
                    ];
                }
                $product->setImages($images);
            } else {
                $message = [
                    'type' => 'Error',
                    'content' => 'Something went wrong',
                    'class' => 'danger'
                ];
            }
        }
        return new Response($this->view->render('p/store_create_product.twig', [
            'store' => $store,
            'categories' => PRODUCT_CATEGORIES,
            'message' => $message ?? [],
            'product' => $product ?? [],
        ]));
    }

    public function storeProductVariations(...$args): Response
    {
        extract($args);

        $product = Product::load($request->query->get('pro_id'));
        $store = new Store($request->query->get('store_id'));
        $productAttributes = new ProductAttributes();
        $attributes = $productAttributes->byProduct($product->id());

        $ratings = new Conversion();
        $ratings = $ratings->getAllRatings();
        return new Response($this->view->render('p/store_product_variations.twig', [
            'store' => $store,
            'product' => $product,
            'attributes' => $attributes,
            'ratings' => $ratings,
        ]));
    }

    public function storeCreateProductVariation(...$args): Response
    {
        extract($args);
        $product = Product::load($request->query->get('pro_id'));
        $store = new Store($request->query->get('store_id'));

        if ($request->getMethod() === 'POST') {
            $attribute_data = $request->request->all();
            $product_attribute = new ProductAttribute(
                $attribute_data['title'],
                is_numeric($attribute_data['position']) ? intval($attribute_data['position']) : 0,
                $product->id(),
                !empty($attribute_data['always_in_stock']),
                $attribute_data['stock_level'],
                $attribute_data['description'],
                $attribute_data['default_cart_quantity'],
                $attribute_data['max_cart_quantity'],
                !empty($attribute_data['shippable']),
                $attribute_data['sizes'] ?? [],
                $attribute_data['dimension'] ?? [],
            );
            if ($product_attribute->save()) {

                $price = new Price(
                    $product_attribute->id(),
                    $attribute_data['price'],
                    $attribute_data['currency'],
                    $attribute_data['discount'],
                    $store->getStoreId()
                );
                $price->save();

                $message = [
                    'type' => 'Success',
                    'content' => 'Product variation created successfully',
                    'class' => 'success'
                ];
            } else {
                $message = [
                    'type' => 'Error',
                    'content' => 'Something went wrong',
                    'class' => 'danger'
                ];
            }
        }

        return new Response($this->view->render('p/store_create_product_variation.twig', [
            'store' => $store,
            'product' => $product,
            'attribute' => $product_attribute ?? [],
            'message' => $message ?? [],
        ]));

    }

    public function storeEditProductVariation(...$args): Response
    {
        extract($args);
        $product = Product::load($request->query->get('pro_id'));
        $store = new Store($request->query->get('store_id'));
        $product_attribute = ProductAttribute::load($request->query->get('attr_id'));

        if ($request->getMethod() === 'POST') {
            $attribute_data = $request->request->all();

            $product_attribute->setName($request->request->get('title', $product_attribute->getName()));
            $product_attribute->setPosition($request->request->get('position', $product_attribute->getPosition()));
            $product_attribute->setProductId($product_attribute->getProductId());
            $product_attribute->setAlwaysInStock(!empty($attribute_data['always_in_stock']));
            $product_attribute->setStockLevel($attribute_data['stock_level']);
            $product_attribute->setDescription($attribute_data['description']);
            $product_attribute->setDefaultCartQuantity((int)$request->request->get('default_cart_quantity', $product_attribute->getDefaultCartQuantity()));
            $product_attribute->setMaxCartQuantity((int)$request->request->get('max_cart_quantity', $product_attribute->getMaxCartQuantity()));
            $product_attribute->setShippable(!empty($attribute_data['shippable']));
            $product_attribute->setSizes($attribute_data['sizes'] ?? []);
            $product_attribute->setDimension($attribute_data['dimension'] ?? []);
            $flag[] = $product_attribute->save();

            $price = $product_attribute->getPrice();
            $price->setBasePrice($attribute_data['price']);
            $price->setDiscount($attribute_data['discount']);
            $price->setCurrency($attribute_data['currency']);
            $flag[] = $price->save();

            if (!in_array(false, $flag)) {
                $message = [
                    'type' => 'Success',
                    'content' => 'Product variation updated successfully',
                    'class' => 'success'
                ];
            } else {
                $message = [
                    'type' => 'Error',
                    'content' => 'Something went wrong',
                    'class' => 'danger'
                ];
            }

        }

        return new Response($this->view->render('p/store_edit_product_variation.twig', [
            'store' => $store,
            'product' => $product,
            'attribute' => $product_attribute,
            'message' => $message ?? [],
        ]));
    }

    public function storeDeleteProduct(...$args): Response|RedirectResponse
    {
        extract($args);
        $product = Product::load($request->query->get('pro_id'));
        $st = $request->query->get('store_id');
        $action = $request->query->get('action');
        if ($action === 'delete') {
            $product->delete();
            return new RedirectResponse('/store/' . $st . '/products');
        } elseif (empty($action)) {
            return new Response($this->view->render('p/confirm_deletion.twig', [
                'title' => $product->getTitle(),
                'action' => $request->getUri() . "?" . http_build_query(['action' => 'delete']),
            ]));
        }

        return new Response($this->view->render('p/deletion_error.twig', []));
    }

    public function storeDeleteProductVariation(...$args)
    {
        extract($args);
        $product = ProductAttribute::load($request->query->get('attr_id'));
        $st = $request->query->get('store_id');
        $action = $request->query->get('action');
        if ($action === 'delete') {
            $product->delete();
            return new RedirectResponse('/store/' . $st . '/product/' . $product->getProductId() . '/variations');
        } elseif (empty($action)) {
            return new Response($this->view->render('p/confirm_deletion.twig', [
                'title' => $product->getName(),
                'action' => $request->getUri() . "?" . http_build_query(['action' => 'delete']),
            ]));
        }

        return new Response($this->view->render('p/deletion_error.twig', []));
    }

    public function storeVariationsList(...$args)
    {
        extract($args);

        $store = new Store($request->query->get('store_id'));
        $productAttributes = new ProductAttributes();
        $product = new Products();
        $products = array_map(fn($p) => $p->id(), $product->byStore($store->getStoreId()));
        $attributes = $productAttributes->getByProducts($products);

        $ratings = new Conversion();
        $ratings = $ratings->getAllRatings();
        return new Response($this->view->render('p/store_variations.twig', [
            'store' => $store,
            'attributes' => $attributes,
            'ratings' => $ratings,
        ]));
    }

    public function storeDuplicateProductVariation(...$args)
    {
        extract($args);
        $productAttribute = ProductAttribute::load($request->query->get('attr_id'));

        $productAttribute?->duplicate();

        return new RedirectResponse("/store/" . $request->get('store_id') . "/product/" . $request->get('pro_id') . "/variations");
    }

    public function cartListCampaign(...$args)
    {
        extract($args);

        $cart = new Cart();

        $all_carts = $cart->getAllCarts();

        return new Response($this->view->render('p/carts_list.twig', [
            'carts' => $all_carts,
        ]));

    }

    public function cartSendCampaign(...$args)
    {
        extract($args);
        $cart_id = $request->query->get('cart_id');
        $action = $request->query->get('action');
        $cart = new Cart();
        $data = $cart->load(['id'=>$cart_id]);

        if ($action === 'send') {

            $callback = _CALLBACK['cart_campaign'];
            if (!empty($callback)) {
                $callbackObject = new $callback;
                $emailBody = $callbackObject->sendCampaign(cart: $data);

                $cust =  new Customer()->load($data->user_id);
                new Mail()->send(
                    $cust['email'],
                    $emailBody->subject,
                    $emailBody->body,
                    toName: ucfirst($cust['first_name'] ?? '') . " " . ucfirst($cust['last_name'] ?? ''),
                );
            }

            return new RedirectResponse('/carts/campaign/list');
        } elseif (empty($action)) {
            return new Response($this->view->render('p/confirm_sending_campaign.twig', [
                'title' => "Send Campaign: to ". new Customer()->load($data->user_id)['email'] . "?",
                'action' => $request->getUri() . "?" . http_build_query(['action' => 'send']),
            ]));
        }
    }

    public function cartDelete(...$args)
    {
        extract($args);
        $action = $request->query->get('action');
        if ($action === 'delete') {
            new Cart()->delete($request->query->get('cart_id'));
            return new RedirectResponse('/carts/campaign/list');
        } elseif (empty($action)) {
            return new Response($this->view->render('p/confirm_deletion.twig', [
                'title' => "this cart",
                'action' => $request->getUri() . "?" . http_build_query(['action' => 'delete']),
            ]));
        }

        return new Response($this->view->render('p/deletion_error.twig', []));

    }

    public function cartItemAppend(...$args)
    {
        extract($args);

        $store = array_column(STORE_LIST,'id');
        $productAttributes = new ProductAttributes();
        $product = new Products();
        $products = array_map(fn($p) => $p->id(), $product->byStores($store));
        $attributes = $productAttributes->getByProducts($products);

        if($request->query->has('attr')) {
            $productAttribute = ProductAttribute::load($request->query->get('attr'));
            $cart_data = new Cart()->load(['id'=>$request->query->get('cart_id')]);
            $cart = new Cart();

            $cart->addItem(
              $request->query->get('cart_id'),
              $productAttribute->getProductId(),
              $productAttribute->id(),
              $productAttribute->getDefaultCartQuantity(),
              $productAttribute->getPrice()->getPriceIn($cart_data->currency_code)
            );
            return new RedirectResponse('/store/carts/'.$cart_data->id.'/item/add');
        }

        $ratings = new Conversion();
        $ratings = $ratings->getAllRatings();
        return new Response($this->view->render('p/store_variations_add_item.twig', [
            'attributes' => $attributes,
            'ratings' => $ratings,
            'message' => $message ?? [],
        ]));
    }

    public function cartView(...$args)
    {
        extract($args);
        $cart = new Cart();
        $cart_data = $cart->load(['id'=>$request->query->get('cart_id')]);
        if (empty($cart_data)) {
            return new RedirectResponse('/carts/campaign/list');
        }
        return new Response($this->view->render('p/cart.twig', [
            'cart' => $cart_data,
        ]));
    }

    public function cartItemChange(...$args): JsonResponse
    {
        extract($args);
        $cart = new Cart();
        $cart_data = $cart->load(['id'=>$request->query->get('cart_id')]);
        $attributeProduct = ProductAttribute::load($request->query->get('attr'));
        $product = $attributeProduct->getProduct();
        $quantity = (int) $request->query->get('q');

        if ($quantity <= 0) {
            if ($cart->removeItemByProduct($product->getId())) {
                return new JsonResponse(['success' => true,
                    'message' => 'Item removed from cart successfully',
                    'cart' => $cart->load(['id'=>$cart_data->id]),
                ]);
            }
        }

        $result = $cart->addItem(
            $cart_data->id,
            $product->getId(),
            $attributeProduct->id(),
            $quantity,
            $attributeProduct->getPrice()->getPriceIn($cart_data->currency_code)
        );

        return new JsonResponse(['success' => $result,
            'cart' => $cart->load(['id'=>$cart_data->id]),
            ]);
    }

    public function cartItemNote(...$args)
    {
        extract($args);
        $cart = new Cart();
        $cart_data = $cart->load(['id'=>$request->query->get('cart_id')]);
        $note = json_decode($request->getContent(), true);

        $r = $cart->addNote($cart_data->id, $note['note']);
        return new JsonResponse(['success' => $r]);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function ordersListing(...$args): Response
    {
        extract($args);
        $store = new Store($request->query->get('store_id'));

        if (empty($store->getStoreId())) {
            return new Response($this->view->render('p/store_not_found.twig', []));
        }
        $orders = new Order()->getOrdersByStore($store->getStoreId());

        return new Response($this->view->render('p/orders_list.twig', [
            'orders' => $orders,
            'store' => $store,
        ]));
    }

    public function orderView(...$args)
    {
        extract($args);

        $store = new Store($request->query->get('store_id'));
        $order = new Order($request->query->get('order_id'));

        return new Response($this->view->render('p/order_view.twig', [
            'order' => $order,
            'store' => $store,
        ]));
    }

    public function orderUpdateStatus(...$args)
    {
        extract($args);
        $status = $request->query->get('status', 'placed');
        $order = new Order($request->query->get('order_id'));

        if (!empty($status)) {
            $r = $order->updateStatus($status);

            $cust = new Customer()->load($order->getUserId());

            $callback = _CALLBACK['order_status_change_email'];
            if (!empty($callback)) {
                $callbackObject = new $callback;
                $email = $callbackObject->sendOrderStatusChangeEmail(order: $order, status: $status);
                new Mail()->send(
                    $cust['email'],
                    $email->subject,
                    $email->body,
                );
            }

            return new JsonResponse(['success' => $r]);
        }
        return new JsonResponse(['success' => false]);
    }

    public function orderSendInvoice(...$args)
    {
        extract($args);

        $store = new Store($request->query->get('store_id'));
        $order = new Order($request->query->get('order_id'));

        $callback = _CALLBACK['order_invoice_email'] ?? null;

        if ($callback) {
            $callbackObject = new $callback;
            $email = $callbackObject->sendOrderInvoiceEmail(order: $order, store: $store);

            $cust = new Customer()->load($order->getUserId());

            new Mail()->send(
                $cust['email'],
                $email->subject,
                $email->body,
                toName: ucfirst($cust['first_name'] ?? '') . " " . ucfirst($cust['last_name'] ?? ''),
                attachments: $email->attachments ?? [],
            );

            return new RedirectResponse($request->headers->get('referer'));
        }
    }

    public function orderPrintInvoice(...$args)
    {
        extract($args);
        $store = new Store($request->query->get('store_id'));
        $order = new Order($request->query->get('order_id'));

        $callback = _CALLBACK['order_invoice_email'] ?? null;

        if ($callback) {
            $attachment = InvoiceFileManager::saveInvoice(
                $order->id(),
                $store,
                'p/order_invoice.twig',
                true
            );

            if (file_exists($attachment)) {
                return new Response(file_get_contents($attachment), 200, [
                    'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="invoice.pdf"'

                    ]
                );
            }
        }

        return new RedirectResponse($request->headers->get('referer'));
    }

    public function createUserAccount(...$args): RedirectResponse|Response
    {
        extract($args);

        if ($request->getMethod() === 'POST') {
            $account_data = $request->request->all();

            if (!empty($account_data['password']) && !empty($account_data['email']) && !empty($account_data['name']) && !empty($account_data['agree'])) {

                $user = User::create($account_data);

                if ($user->getId()) {
                    return new RedirectResponse('/user/login');
                }
            }
        }

        return new Response($this->view->render('p/create_user_account.twig', []));
    }

    public function loginAccount(...$args)
    {
        extract($args);

        if ($request->getMethod() === 'POST') {
            $account_data = $request->request->all();

            $user = User::loadByEmail($account_data['name']) ?? User::loadByName($account_data['name']);
            if ($user) {
                if ($user->login($account_data['password'])) {
                    return new RedirectResponse('/');
                }
            }
        }
        return new Response($this->view->render('p/login_account.twig', []));
    }

    public function logoutAccount(...$args)
    {
        extract($args);
        if (User::currentUser()->logout()) {
            return new RedirectResponse('/user/login');
        }
        return new RedirectResponse($this->view->render('pages/samples/error-505.html', []));
    }

    /**
     * @throws \DateMalformedStringException
     */
    public function storeCreateTask(...$args): JsonResponse
    {
        extract($args);

        $content = $request->request->all();

        if (!empty($content['title']) && !empty($content['start_date']) && !empty($content['end_date']))
        {
            $result = new CommerceTodoTask()->addTask(
                $content['title'],
                new DateTime($content['start_date'])->getTimestamp(),
                new DateTime($content['end_date'])->getTimestamp(),
            );

            return new JsonResponse(['success' => $result]);
        }
        return new JsonResponse(['success' => false]);
    }

    public function deleteTasks(...$args): JsonResponse
    {
        extract($args);

        $content = $request->request->all();

        if (!empty($content['task_id']))
        {
            $results = [];
            foreach ($content['task_id'] as $task_id){
                $result = new CommerceTodoTask()->deleteTask($task_id);
                $results[$task_id] = $result;
            }

            return new JsonResponse(['results' => $results]);

        }

        return new JsonResponse(['results' => []]);
    }

    public function checkoutPayment(...$args)
    {
        extract($args);
        $cart = new Cart();
        $cart_data = $cart->load(['id'=>$request->query->get('cart_id')]);

        $supported_payment = [];

        foreach (PAYMENT_METHODS as $method) {

            /**@var PaymentGetWayInterface $object */
            $object = new $method;
            if ($object->isEnabled()) {
                $supported_payment[] = [
                    'id' => $object->getGatewayId(),
                    'name' => $object->getGatewayName(),
                    'description' => $object->getGatewayDescription(),
                    'logos' => $object->getGatewayLogos(),
                    'form' => $object->getPaymentForm(
                        [
                            'total' => $cart_data->total,
                            'currency' => $cart_data->currency_code,
                            'amount' => $cart_data->total,
                            'cart_id' => $cart_data->id,
                            'type' => $object->getGatewayId(),
                            'warning' => !in_array($cart_data->currency_code, AUTHORIZE_SUPPORTED_CURRENCIES['currencies'])
                            ? " This payment will be conducted in " . AUTHORIZE_SUPPORTED_CURRENCIES['default'] . " currency. as the cart currency of ". $cart_data->currency_code . " is not supported."
                                : ""
                        ]
                    )
                ];
            }

        }

        if ($request->getMethod() === 'POST') {
            $payment_data = $request->request->all();

            if (!empty($payment_data['type'])) {

                $gateway = PaymentGatWayAbstract::getTypeGateway($payment_data['type']);
                if ($gateway instanceof PaymentGetWayInterface) {
                    $result = $gateway->processPayment($payment_data);
                    if ($result) {
                        return new RedirectResponse('/');
                    }
                    $messages = [];
                    foreach ($gateway->errors as $error) {
                        $messages[] = [
                            'type' => 'error',
                            'content' => $error,
                            'class' => 'danger'
                        ];
                    }
                }

            }
        }


        return new Response($this->view->render('p/cart_payment.twig', [
            'cart' => $cart_data,
            'supported_payment' => $supported_payment,
            'messages' => $messages ?? [],
        ]));
    }

    public function paymentsListing(...$args)
    {
        extract($args);

        $payments = CommercePayment::getPaymentByStore($request->query->get('store_id'));
        return new Response($this->view->render('p/payments_list.twig', [
            'payments' => $payments,
            'store' => new Store($request->query->get('store_id')),
        ]));
    }

    public function paymentsView(...$args): Response
    {
        extract($args);

        $payment = CommercePayment::loadById($request->query->get('pid'));
        return new Response($this->view->render('p/payments_view.twig', [
            'payment' => $payment,
            'store' => new Store($request->query->get('store_id')),
        ]));
    }

    public function paymentUpdateStatus(...$args)
    {
        extract($args);
        $status = $request->query->get('status', 'placed');
        $payment = CommercePayment::loadById($request->query->get('pid'));

        if (!empty($status)) {
            $r = $payment->updateStatus($status);

            return new JsonResponse(['success' => $r]);
        }
        return new JsonResponse(['success' => false]);
    }

    public function paymentSendInvoice(...$args)
    {
        extract($args);

        $store = new Store($request->query->get('store_id'));
        $payment = CommercePayment::loadById($request->query->get('pid'));

        $callback = _CALLBACK['order_payment_email'] ?? null;

        if ($callback) {
            $callbackObject = new $callback;
            $email = $callbackObject->sendOrderPaymentEmail(payment: $payment, store: $store);

            $cust = new Customer()->load($payment->getOrder()->getUserId());

            new Mail()->send(
                $cust['email'],
                $email->subject,
                $email->body,
                toName: ucfirst($cust['first_name'] ?? '') . " " . ucfirst($cust['last_name'] ?? ''),
                attachments: $email->attachements ?? [],
            );

            return new RedirectResponse($request->headers->get('referer'));
        }
    }

    /**
     * @throws MpdfException
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function paymentPrintInvoice(...$args)
    {
        extract($args);
        $store = new Store($request->query->get('store_id'));
        $payment = CommercePayment::loadById($request->query->get('pid'));

        PDF->WriteHTML(
            $this->view->render('p/order_payment_print.twig',[
                'payment' => $payment,
                'store' => $store
            ])
        );

        $content = PDF->Output(dest: Destination::STRING_RETURN);

        return new Response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="invoice.pdf"'

            ]
        );
    }

    public function paymentsDelete(...$args)
    {
        extract($args);
        $payment = CommercePayment::loadById($request->query->get('pid'));
        $action = $request->query->get('action');

        if ($action === 'delete') {
            $payment->delete();
            return new RedirectResponse($request->headers->get('referer'));
        } elseif (empty($action)) {
            return new Response($this->view->render('p/confirm_deletion.twig', [
                'title' => "this payment",
                'action' => $request->getUri() . "?" . http_build_query(['action' => 'delete']),
            ]));
        }

        return new Response($this->view->render('p/deletion_error.twig', []));
    }

    public function paymentsCreditPayment(...$args)
    {
        extract($args);
        $payment = CommercePayment::loadById($request->query->get('pid'));

        if ($request->getMethod() === 'POST') {
            $payment_data = $request->request->all();
            if (!empty($payment_data['type'])) {

                $payment_data['order'] = $payment->getOrder();
                $gateway = PaymentGatWayAbstract::getTypeGateway($payment_data['type']);

                if ($gateway instanceof PaymentGetWayInterface) {
                    $result = $gateway->processPayment($payment_data);

                    if ($result) {
                        return new RedirectResponse('/');
                    }
                    $messages = [];
                    foreach ($gateway->errors as $error) {
                        $messages[] = [
                            'type' => 'error',
                            'content' => $error,
                            'class' => 'danger'
                        ];
                    }
                }

            }
        }

        return new Response($this->view->render('p/payments_credit_payment.twig', [
            'payment' => $payment,
            'amount' => $payment->getAmount(),
            'currency' => $payment->getCurrency(),
            'type' =>  'credit_card',
            'total' => $payment->getAmount(),
        ]));
    }
}