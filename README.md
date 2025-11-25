# Simp Commerce Package

A comprehensive, modular PHP e-commerce solution designed for flexibility and ease of integration. This package provides a robust backend panel, order management, product variations, shopping cart logic, and payment gateway integration.

## 📋 Prerequisites

*   **PHP 8.4+**
*   **Composer**
*   **Database** (MySQL/MariaDB)

---

## 🚀 Installation & Setup

Follow these steps strictly to ensure the package functions correctly.

### 1. ⚠️ Environment Configuration (CRITICAL)
Before doing anything else, you **must** configure your environment variables. The application relies on these for database connections, encryption, and path resolution.

1.  Copy the example environment file:
```shell script
cp .env.example .env
```

2.  **Open `.env` and set ALL keys.**
    *   Ensure database credentials are correct.
    *   Set up your mail server credentials.
    *   Define absolute paths if required by specific keys.

### 2. Configuration File Setup
The core configuration of this package resides in a specific settings file. You need to bring this into your project root.

1.  Copy the settings file from the vendor source to your root directory:
```shell script
cp src/config/commerce.settings commerce.settings
```

    > **Note:** You can edit this `commerce.settings` file to customize configuration constants, but **keep the default code logic intact** to ensure compatibility.

### 3. Initialize in Entry Point
To load the commerce system, require the settings file in your application's entry point (typically `index.php`).

```php
<?php
// index.php

// ... autoloader and other setup ...

// Load the Commerce Configuration
require_once __DIR__ . '/commerce.settings';
```


---

## 🛠️ Customization & Advanced Configuration

This package is designed to be overridden and extended via the `commerce.settings` file.

### 🔄 Routing
This package uses [Simp/Router (CHANCENY/router)](https://github.com/CHANCENY/router) for handling web requests.

*   **Default Routes:** The default admin panel routes are registered automatically within the settings file.
*   **Custom Routes:** You can copy the route definitions found in `commerce.settings` and redefine them to change URLs or point to different controllers.
*   **Middleware:** Access control is handled via the `AccessMiddleware::class`.

### 🎨 Twig Templates (Admin Panel)
The package comes with built-in Twig templates for the administration panel. If you need to modify the design or layout:

1.  **Copy Templates:** Copy all files from `src/commerce_panel/twig` to a directory in your project (e.g., `templates/commerce`).
2.  **Update Path:** Open your root `commerce.settings` file.
3.  **Change Constant:** Find the `TEMPLATE_ROOT` constant and update it to point to your new directory:

```php
// commerce.settings
define("TEMPLATE_ROOT", __DIR__ . '/path/to/your/custom/templates');
```


### 🔌 Payment Gateways
The system utilizes a driver-based approach for payments.
1.  **Create Driver:** Create a class that extends `Simp\Commerce\payment\PaymentGatWayAbstract`.
2.  **Register:** Open `commerce.settings` and add your class name to the `PAYMENT_METHODS` constant array.
3.  **Currencies:** Ensure you configure `AUTHORIZE_SUPPORTED_CURRENCIES` if you are using the default Authorize.net integration or require currency fallback logic.

### 📞 Callbacks (Event Handling)
You can hook into specific system events (like Order Confirmation or Cart Abandonment campaigns) without touching core code.

1.  Look for the `_CALLBACK` constant in `commerce.settings`.
2.  Create a class that handles the specific event logic.
3.  Override the default handler in the array:

```php
const _CALLBACK = [
    // Override the default email confirmation handler
    'order_confirmation' => \My\Project\Handlers\CustomOrderConfirmation::class,
    // ... other callbacks
];
```


---

## 📦 Features Overview

*   **Store Management:** Multi-store capability with configurable currencies and tax rules.
*   **Product Management:**
    *   Support for complex Product Variations (Attributes).
    *   Image galleries and file uploads.
    *   Inventory management.
*   **Order System:**
    *   Full order lifecycle (Placed, Processing, Completed).
    *   Invoice generation (PDF) via `mpdf`.
    *   Transactional emails.
*   **Shopping Cart:**
    *   Abandonment campaign tools.
    *   Admin ability to modify customer carts (add items, add notes).
*   **Internal Tools:**
    *   To-Do list for store administrators.
    *   Dashboard summaries (Order stats, Quick financial summary).