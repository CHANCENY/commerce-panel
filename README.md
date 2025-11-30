Here is **ONLY the raw markdown**, no code blocks, no HTML preview — just pure text you can copy:

---

# Simp Commerce — Installation & Setup Guide

This guide explains how to install and set up the **Simp Commerce** PHP package in a new project.

## Requirements

Before installing, ensure you have:

1. **PHP 8.4 or higher**
2. **Composer** installed globally ([https://getcomposer.org](https://getcomposer.org))

---

## Create Your Project

1. Create a directory for your commerce project:

   `mkdir my-commerce-project`

2. Enter the directory:

   `cd my-commerce-project`

3. Install Simp Commerce:

   `composer require simp/commerce`

---

## Project Structure Setup

After installation, your directory should look like this:

```
your_project_directory
  ├── composer.json
  ├── composer.lock
  ├── vendor/
  └── public/
```

Create the **public** directory manually if it doesn’t exist.

---

## Create `public/index.php`

Inside the `public` folder create `index.php`:

```
<?php
require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../commerce.settings";
```

---

## Copy Required Configuration Files

### 1. Copy `commerce.settings`

Copy this file:

`vendor/simp/commerce/src/config/commerce.settings`

Place it inside your project root:

`your_project_directory/commerce.settings`

**Do not modify** the default content.
You may add new constants, but leave the original values unchanged.

---

## Routing Notes

The package uses the routing system:

[https://packagist.org/packages/simp/router](https://packagist.org/packages/simp/router)

Because of this:

* It is recommended to run this project on a **separate subdomain** in production.
* Copy the `.htaccess` file:

`vendor/simp/commerce/src/config/.htaccess → public/.htaccess`

---

## Environment Variables

Copy the `.env.example` file:

`vendor/simp/commerce/.env.example → .env`

Final structure:

```
your_project_directory
  ├── composer.json
  ├── composer.lock
  ├── commerce.settings
  ├── .env
  ├── vendor/
  └── public/
       ├── index.php
       └── .htaccess
```

---

## Important

Before running anything, edit `.env` and configure all required keys.

During development set:

`STORAGE_CREATED_FLAG=true`

---

## Important `.env` Keys

### Directories & File Handling

* UPLOAD_DIR
* FILE_WEB_ACCESS
* MPDF_TMP
* INVOICE_DIR

### Third-Party Conversion API Keys

Set all conversion-related API keys for proper functionality.

---

If you want, I can generate a shorter README, a professional version, or a version with badges.
