# E-Commerce Laravel Backend

This is the backend API and administration layer for the e-commerce platform, built with Laravel 11.

## Implementation Concept & Domain Models

The application is structured around a robust e-commerce data model designed to handle users, flexible product catalogs with complex variants, orders, and payments. The domain is broken down into the following core areas:

### 1. Users & Identity
- **User**: Represents customers and administrators (differentiated by a `role` field). A user can have multiple saved addresses and place multiple orders.
- **Address**: Stores user contact and location details. Can be used as a billing or shipping address for an order.

### 2. Catalog Management
- **Category**: Supports a hierarchical structure (parent-child relationships) for organizing products.
- **Product**: The base representation of an item in the store (e.g., "T-Shirt"). 
- **Attribute & AttributeValue**: Used for defining variable traits across the catalog (e.g., Attribute: "Color", Values: "Red", "Blue").
- **ProductVariant**: The specific, purchasable SKU of a product (e.g., "Red T-Shirt, Size L"). Variants link to `AttributeValue` via a pivot table (`product_variant_attribute_value`). Each variant manages its own pricing, compare-at price, weight, and stock quantity.
- **ProductImage**: Stores image paths. Can be linked generally to a `Product` or tied specifically to a `ProductVariant`.

### 3. Orders & Cart
- **Order**: Represents a customer's checkout session. Tracks financial totals, currency, payment status, and is linked to a user and a billing address.
- **OrderItem**: Represents a line item in an order. It stores a "snapshot" of the `ProductVariant` details (title, SKU, unit price) at the exact time of purchase to prevent historical order data from changing if the product is later modified.

### 4. Payments
- **Payment**: Tracks payment attempts and completed transactions. Designed to integrate with payment gateways (like Chapa), storing references, amounts, and JSON webhook payloads for reliable verification.

---

## Technical Stack & Tooling
- **Framework**: Laravel
- **Database**: SQLite (Development environment)
- **Code Style**: Laravel Pint
