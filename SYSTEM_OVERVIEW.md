# System Overview

This document provides an overview of the application's architecture and workflow.

## Core Functionalities

- Listening for events from Shopify (like new orders) via webhooks.
- Processing and transforming that data.
- Sending order information to an ERP system.
- Synchronizing product information between the ERP, its own database, and Shopify.
- Providing an administrative interface for managing application data.

## Table of Contents

1.  [Shopify Integration](#shopify-integration)
    -   [Webhook Handling](#webhook-handling)
    -   [Product Synchronization](#product-synchronization)
    -   [Order Processing](#order-processing)
2.  [ERP Integration](#erp-integration)
3.  [Administrative Interface](#administrative-interface)
4.  [Key Components](#key-components)
    -   [Controllers](#controllers)
    -   [Services](#services)
    -   [Jobs](#jobs)
    -   [Commands](#commands)
    -   [Models](#models)

---

## 1. Shopify Integration

The application integrates with Shopify to receive order information and synchronize product data. The integration is handled through a combination of webhooks and API calls.

### Webhook Handling

Shopify webhooks are used to receive real-time notifications for events such as new orders. The webhook endpoints are defined in `routes/web.php`.

- **Order Creation Webhook**: The endpoint `POST /shopify-order-create-webhook` (and an alias `POST /shopify/webhooks/create-order`) listens for new order notifications from Shopify. The request is handled by the `handleOrderCreateWebhook` method in the `ShopifyOrderController`.

The webhook handling logic in `ShopifyOrderController` is as follows:

1.  **Validate Store**: It identifies the store based on a `store_code` query parameter.
2.  **Verify Webhook Origin**: It checks the `X-Shopify-Shop-Domain` header to ensure the request is from an allowed Shopify store. (Note: This logic is commented out in the current implementation).
3.  **Prevent Duplicates**: It checks if the order has already been processed by looking up the order ID in the local database.
4.  **Process Order**: It calls the `ShopifyOrderService` to process and save the order data.
5.  **Push to ERP**: It then calls the `ErpOrderService` to send the order to the ERP system.

### Order Processing

Order data received from Shopify is processed and stored in the local database. This is handled by the `ShopifyOrderService`.

- **`createOrder()` method**: This method is called by the `ShopifyOrderController` when a webhook is received. It takes the raw order data from Shopify and transforms it into the application's data model, saving it to the `orders` and `order_line_items` tables.
- **`fetchOrdersFromShopify()` method**: This service also provides a way to manually fetch orders from Shopify, which is likely triggered from the admin interface.

### Product Synchronization

The application also appears to have functionality for synchronizing products with Shopify. This is handled by `ShopifyProductController` and related services. The routes for product synchronization are grouped under the `/shopify/{storeCode}` prefix and protected by the `validate.store` middleware. These routes allow for:

- Creating products in Shopify.
- Replacing product images.
- Creating and deleting collections.
- Applying discounts to products.

The actual synchronization logic is likely contained within the `ShopifyProductService` and the `FetchShopifyProducts` command.

## 2. ERP Integration

The application is designed to communicate with an external ERP system. This is primarily handled by the `ErpOrderService`.

- **`pushOrderToERP()` method**: After an order is successfully received and saved from Shopify, this method is called to send the order data to the ERP.
- **Configuration**: The ERP integration is configurable on a per-store basis. The `stores` table contains the `erp_backend_url` for each store. The integration can also be enabled or disabled globally via the `erp_create_order_enabled` setting.
- **Data Format**: The `ErpOrderService` transforms the order data into the format expected by the ERP's API and sends it to the `/api/order/create` endpoint.
- **Sync Status**: The service updates the `erp_synced` flag on the order to `true` after a successful push to the ERP.

## 3. Administrative Interface

