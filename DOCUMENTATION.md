# Patchee Application Documentation

## 1. Project Overview

This application serves as a robust, multi-tenant integration platform designed to bridge the gap between multiple Shopify stores, an internal ERP system, and its own database. It is not a standard monolithic web application but a service-oriented middleware focused on data synchronization and workflow automation.

Its primary responsibilities are:
- Listening for events from Shopify (like new orders) via webhooks.
- Processing and transforming that data.
- Sending order information to an ERP system.
- Synchronizing product information between the ERP, its own database, and Shopify.
- Providing an administrative interface for managing stores, products, and other core data.

## 2. Architecture

The application is built on the Laravel framework and follows a modern, service-oriented architecture.

### 2.1. Multi-Tenancy

The system is designed to handle multiple Shopify stores securely.
- **Store Model (`app/Models/Store.php`):** Each store has its own configuration, including API credentials.
- **Middleware (`app/Http/Middleware/ValidateStoreCode.php`):** API requests are authenticated using a custom `X-Store-Code` header and an access token. This middleware ensures that all operations are correctly scoped to a single store, preventing data leaks.
- **Database:** All relevant database tables contain a `store_id` column to partition data effectively.

### 2.2. Service-Oriented Design

Business logic is cleanly separated into dedicated service classes, promoting reusability and maintainability.
- **`ShopifyService` (`app/Services/ShopifyService.php`):** A foundational service that provides a resilient HTTP client for all Shopify API communication. It includes built-in retry logic to handle API rate limits and transient network errors gracefully.
- **`ShopifyProductService` (`app/Services/ShopifyProductService.php`):** Manages the two-way synchronization of product data between the application's database and Shopify. It handles the logic for creating and updating products.
- **`ShopifyOrderService` (`app/Services/ShopifyOrderService.php`):** Responsible for processing incoming Shopify order data.
- **`ErpOrderService` (`app/Services/ErpOrderService.php`):** Manages the communication of order data to the external ERP system. This acts as the translation layer between the Shopify order format and the format expected by the ERP.

### 2.3. Admin Panel

The application uses Filament PHP for its administrative interface. The configuration in the `app/Filament` directory defines the resources (like Products, Orders, Stores) that administrators can manage, providing a user-friendly way to view and interact with the synchronized data.

## 3. Key Workflows

### 3.1. Inbound: Shopify Order Creation

1.  **Webhook:** A Shopify store is configured to send a webhook to the application's API endpoint when a new order is created.
2.  **Authentication:** The request hits a route defined in `routes/web.php`, which is protected by the `ValidateStoreCode` middleware. The middleware identifies the correct store based on the `X-Store-Code` header.
3.  **Controller:** The request is routed to `ShopifyOrderController`, which calls the `ShopifyOrderService`.
4.  **Data Processing:** The `ShopifyOrderService` saves the order data to the `orders` and `order_line_items` tables in the local database.
5.  **ERP Forwarding:** The `ErpOrderService` is then invoked to transform the order data and send it to the ERP system's API.

### 3.2. Product Synchronization

The application handles two-way product data synchronization.
- **Shopify to App:** A console command, `FetchShopifyProducts` (`app/Console/Commands/FetchShopifyProducts.php`), can be run as a scheduled job to periodically pull product data from Shopify and update the local database.
- **App to Shopify:** The `ShopifyProductService` contains the logic (`createShopifyProduct`) to push product data from the local database to Shopify. This is likely used when new products are created or updated, possibly originating from the ERP system.

## 4. Database Schema

The database is the central source of truth for the integration. Key tables and fields include:
- **`stores`:** Contains configuration for each Shopify store.
- **`products`:** A central product table with crucial fields:
    - `erp_product_id`: The unique identifier for the product in the ERP system.
    - `shopify_product_id`: The unique identifier for the product in Shopify.
    - `store_id`: Foreign key linking the product to a specific store.
- **`orders` & `order_line_items`:** Stores order data received from Shopify.
- **`product_discounts`:** Manages temporary product discounts, with jobs to revert them.

## 5. Application Entry Points

### 5.1. API Routes (`routes/web.php`)

The primary entry points are API routes used for Shopify webhooks and potentially other external interactions. These routes handle:
- Order creation and updates.
- Product creation and updates.
- Collection management.

### 5.2. Console Commands (`app/Console/Commands`)

Automated and administrative tasks are handled via Artisan commands, which can be triggered manually or via a scheduler (cron job).
- **`FetchShopifyProducts`:** Fetches all products from a Shopify store.
- **`RevertExpiredProductDiscounts`:** A maintenance job to end product promotions.
