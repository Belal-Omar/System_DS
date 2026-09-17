# Digital Soldiers

## Affiliate Marketing & E-commerce Platform

> **System Architecture, Business Logic & Technical Documentation**

Digital Soldiers is a custom-built **Affiliate Marketing and E-commerce / Drop-shipping platform** that connects merchants, marketers (media buyers), customer support teams, and platform administrators in a single system.

The platform manages the complete business lifecycle:

**Product → Marketing → Customer Order → Confirmation → Shipping → Delivery → Commission → Balance → Withdrawal**

This document provides a comprehensive overview of the system architecture, business logic, user roles, order lifecycle, database relationships, financial model, security controls, and future integrations.

---

# 1. System Overview

Digital Soldiers is designed around a multi-role e-commerce model.

The platform allows:

* **Merchants** to provide and manage products.
* **Marketers / Affiliates** to promote products and generate customer orders.
* **Support staff** to confirm customer orders.
* **Administrators** to manage the platform, users, products, orders, finances, and reports.
* **Shipping companies** to fulfill confirmed orders and report delivery status.

The core business flow is:

```text
Merchant
   │
   │ Products
   ▼
Digital Soldiers
   │
   │ Products available for marketing
   ▼
Marketer / Media Buyer
   │
   │ Advertising
   ▼
Customer
   │
   │ Order
   ▼
Digital Soldiers
   │
   ▼
Customer Support
   │
   │ Confirmation
   ▼
Shipping Company
   │
   │ Delivery
   ▼
Customer
   │
   │ Successful Delivery
   ▼
Financial Settlement
   │
   ├── Marketer Commission
   ├── Merchant Revenue
   └── Platform Profit
```

The financial settlement is triggered by the business event of a **successful delivery**, not simply by creating or confirming an order.

---

# 2. Technology Stack

## Backend

* PHP
* Procedural PHP architecture
* MySQL
* Server-side session authentication
* Custom business logic
* Custom security helpers

The system does not currently depend on a large backend framework such as Laravel. It is implemented as a custom PHP application using directly accessible PHP modules and shared helper files.

## Frontend

* HTML5
* CSS3
* JavaScript
* Tailwind CSS
* Boxicons
* Responsive dashboard interfaces

## Database

* MySQL
* Relational data model
* Application-level relationships between entities
* Transaction-based financial operations

---

# 3. Core Application Architecture

The application follows a modular PHP structure.

Important shared components include:

| File                      | Responsibility                             |
| ------------------------- | ------------------------------------------ |
| `config.php`              | Database connection and core configuration |
| `helpers.php`             | Shared helper and security functions       |
| `balance_system.php`      | Financial calculations and balance logic   |
| `admin_panel.php`         | Main administration interface / routing    |
| `index.php`               | Main marketer interface                    |
| `merchant_dashboard.php`  | Merchant dashboard                         |
| `support_dashboard.php`   | Support dashboard                          |
| `add_order.php`           | Customer order creation                    |
| `withdrawals.php`         | Withdrawal requests                        |
| `marketing_dashboard.php` | Marketing analytics                        |

The application is organized primarily around **role-specific dashboards and business modules**.

---

# 4. User Roles

The system currently supports four primary operational roles.

## 4.1 Admin

### Main responsibility

The Admin manages the platform as a whole.

### Main interface

```text
admin_login.php
        ↓
admin_panel.php
```

`admin_panel.php` acts as the main administration interface and can load different administrative modules.

### Main responsibilities

* Manage users
* Manage merchants
* Manage marketers
* Manage products
* Monitor orders
* Review withdrawals
* Monitor financial performance
* Review platform profitability
* Manage system settings
* Monitor marketer and merchant performance
* Manage shipping-related operations

### Important modules

```text
admin_users.php
admin_financial.php
admin_withdrawals.php
admin_orders.php
admin_sheets.php
admin_shipping_storage.php
admin_marketing.php
```

---

# 5. Marketer / Affiliate

The marketer is responsible for generating sales through advertising and customer acquisition.

### Main interface

```text
index.php
```

### Main responsibilities

* Browse available products
* Select products to market
* Run advertising campaigns
* Generate customer leads
* Create customer orders
* Track order statuses
* Record advertising expenditure
* Analyze marketing performance
* Monitor commissions
* Request withdrawals

### Main modules

```text
home111.php
add_order.php
marketer_orders.php
my_finances.php
withdrawals.php
marketing_dashboard.php
creative_performance.php
```

### Marketing metrics

The marketing dashboard can use advertising expenditure and order data to calculate metrics such as:

* CPO — Cost Per Order
* CPD — Cost Per Delivered Order
* Delivery rate
* Advertising spend
* Order volume
* Delivered orders
* Returned orders
* Campaign / creative performance

---

# 6. Merchant / Supplier

The merchant provides the products sold through the platform.

### Main interface

```text
merchant_dashboard.php
```

### Main responsibilities

* Add products
* Manage product information
* Define product pricing
* Manage inventory
* Monitor products
* View relevant orders
* Prepare products for fulfillment
* Monitor financial results

### Important module

```text
add_product.php
```

Merchants should only have access to data and operations associated with their own products and business scope.

---

# 7. Customer Support

Customer Support is responsible for validating customer orders before fulfillment.

### Main interface

```text
support_dashboard.php
```

### Main responsibilities

* Review pending orders
* Contact customers
* Confirm legitimate orders
* Cancel invalid orders
* Update order status

The normal support transition is:

```text
Pending
   │
   ├── Confirmed
   │
   └── Cancelled
```

---

# 8. Order Lifecycle

The order lifecycle is one of the most important components of the system.

## 8.1 Pending

The marketer creates an order through:

```text
add_order.php
```

The order is stored in the `orders` table.

Associated products are stored in:

```text
order_items
```

Initial state:

```text
PENDING
```

---

## 8.2 Confirmed

Customer Support reviews the order and contacts the customer.

If the customer confirms:

```text
PENDING → CONFIRMED
```

If the customer rejects or the order is considered invalid:

```text
PENDING → CANCELLED
```

---

## 8.3 Shipped

Confirmed orders are prepared for fulfillment.

The current system may use manual operational workflows such as spreadsheets.

The order can then move to:

```text
CONFIRMED → SHIPPED
```

---

## 8.4 Out for Delivery

Where supported, the shipment can progress to:

```text
SHIPPED → OUT_FOR_DELIVERY
```

This stage represents the shipment being with the delivery representative.

---

## 8.5 Delivered

When the customer receives the order and payment is successfully collected:

```text
OUT_FOR_DELIVERY → DELIVERED
```

### Financial settlement point

**Delivered is the critical financial event.**

At this stage the system becomes eligible to calculate the financial distribution associated with the order.

Conceptually:

```text
Delivered Order
       │
       ├── Marketer Commission
       ├── Merchant Revenue
       └── Platform Profit
```

---

## 8.6 Returned

If the customer refuses or the shipment is returned:

```text
SHIPPED / OUT_FOR_DELIVERY
              ↓
           RETURNED
```

A returned order should not generate a normal successful-delivery commission.

---

# 9. Database Architecture

The database follows a relational model centered around users, products, orders, financial records, and marketing data.

## Main entities

```text
users
products
orders
order_items
marketer_commissions
withdrawals
marketing_daily_spend
shipping_companies
```

---

# 10. Users Table

The `users` table represents the main platform users.

Important fields include:

```text
id
fullname
email
password
user_type
```

`user_type` determines the user's operational role.

For example:

```text
marketer
merchant
```

Administrative and support access may be handled through the application's corresponding authentication and authorization mechanisms.

---

# 11. Products

The `products` table represents products supplied by merchants.

A product is associated with its merchant through:

```text
products.merchant_id
        ↓
users.id
```

Conceptually:

```text
Merchant
   │
   ├── Product A
   ├── Product B
   └── Product C
```

Product data may include:

* Product name
* Product description
* Price
* Stock
* Merchant ownership
* Product status
* Commission-related information

---

# 12. Orders

The `orders` table represents customer orders.

An order is associated with the marketer who generated it through:

```text
orders.user_id
        ↓
users.id
```

The order contains information such as:

* Customer information
* Order status
* Marketer ownership
* Creation date
* Order-related metadata

Conceptually:

```text
Marketer
   │
   ├── Order #1001
   ├── Order #1002
   └── Order #1003
```

---

# 13. Order Items

`order_items` stores the individual products inside an order.

Relationships:

```text
orders.id
    │
    └── order_items.order_id

products.id
    │
    └── order_items.product_id
```

This allows one order to contain multiple products.

Example:

```text
Order #1001
   │
   ├── Product A × 2
   ├── Product B × 1
   └── Product C × 3
```

The order item should preserve the relevant commercial values at the time of the transaction rather than relying blindly on the product's current values.

---


# 14. Marketer Commissions

The `marketer_commissions` table records commission information associated with marketer earnings.

Conceptually:

```text
Order
  │
  └── Marketer Commission
          │
          └── Marketer
```

This provides a traceable financial relationship between:

```text
Order → Commission → Marketer
```

A commission must be protected against accidental duplicate creation when the same order reaches the delivered state more than once.

---

# 15. Withdrawals

The `withdrawals` table stores financial withdrawal requests.

Typical flow:

```text
Marketer
   │
   │ Withdrawal Request
   ▼
withdrawals
   │
   ▼
Admin Review
   │
   ├── Approved / Completed
   └── Rejected
```

Withdrawal processing is a sensitive financial operation and therefore requires transactional protection.

---

# 16. Marketing Daily Spend

The:

```text
marketing_daily_spend
```

table stores advertising expenditure entered by marketers.

This allows the system to connect:

```text
Advertising Spend
        +
Orders
        +
Delivered Orders
        ↓
Marketing Performance
```

This data can be used to calculate metrics such as:

```text
CPO
CPD
Delivery Rate
ROAS-related metrics
Campaign Performance
```

---

# 17. Shipping Companies

The:

```text
shipping_companies
```

entity represents shipping providers.

The current architecture supports operational/manual workflows, while direct API integration is planned.

Future relationships may include:

```text
Order
  ↓
Shipping Company
  ↓
External Shipment ID
  ↓
Shipment Status
```

---

# 18. Financial Architecture

The financial system is one of the most critical parts of Digital Soldiers.

The platform does not necessarily depend on a simple static:

```text
users.balance
```

field.

Instead, marketer availability can be derived from transactional information.

---

# 19. Marketer Balance

Conceptually:

```text
Total Earned
=
Sum of eligible commissions from successfully delivered orders
```

Then:

```text
Available Balance
=
Total Earned
-
Eligible / Completed Withdrawals
```

The exact treatment of pending withdrawals must be enforced consistently so that the same funds cannot be withdrawn multiple times.

---

# 20. Withdrawal Protection

Financial withdrawals are processed using database transactions and pessimistic locking where implemented.

Conceptually:

```text
BEGIN TRANSACTION
        │
        ▼
Lock relevant financial records
        │
        ▼
Calculate available balance
        │
        ▼
Validate requested amount
        │
        ├── Invalid → ROLLBACK
        │
        ▼
Create withdrawal record
        │
        ▼
COMMIT
```

The purpose is to protect against concurrent requests attempting to spend the same available balance.

Example:

```text
Available Balance = 1,000 EGP

Request A = 1,000 EGP
Request B = 1,000 EGP

Without proper concurrency control:
A → 1,000
B → 1,000
Total = 2,000  ❌

With proper transactional protection:
A → 1,000
B → Rejected  ✅
```

---

# 21. Financial Integrity Requirements

The financial system should also protect against:

* Duplicate commissions
* Duplicate withdrawals
* Invalid negative amounts
* Unauthorized withdrawals
* Commission generation for cancelled orders
* Commission generation for returned orders
* Re-processing an already settled order
* Unauthorized modification of financial records
* Race conditions
* Invalid order-state transitions

A key principle is:

> **Every financial event should be traceable to a business event and should be processed exactly once.**

---

# 22. Security Architecture

Security is implemented at multiple layers.

The main security areas are:

```text
Authentication
      │
      ▼
Authorization
      │
      ▼
Input Validation
      │
      ▼
Database Security
      │
      ▼
Output Encoding
      │
      ▼
Transaction / Financial Protection
```

---

# 23. CSRF Protection

State-changing requests should use CSRF protection.

Examples include:

* Deleting users
* Updating user information
* Changing commissions
* Creating withdrawals
* Other sensitive POST operations

The application uses CSRF tokens generated and validated through shared security functionality.

Conceptually:

```text
Authenticated User
       │
       ▼
Sensitive POST Request
       │
       ├── Valid CSRF Token → Process
       │
       └── Missing / Invalid Token → Reject
```

### Important distinction

CSRF protection is designed to prevent an attacker from causing an authenticated user's browser to submit an unauthorized state-changing request.

It is **not the same as protection against session hijacking**.

---

# 24. XSS Protection

User-controlled data must be safely encoded before being rendered in HTML.

For example:

```php
echo htmlspecialchars(
    $user['fullname'],
    ENT_QUOTES,
    'UTF-8'
);
```

This is an example of **output encoding**, rather than simply "cleaning" every input.

Potentially user-controlled data includes:

* Names
* Emails
* Phone numbers
* Customer information
* Notes
* Product descriptions
* Other text fields

Security testing should verify that stored payloads are rendered as text rather than executed as HTML/JavaScript.

---

# 25. SQL Injection Protection

Database queries should use parameterized queries / prepared statements rather than constructing SQL statements directly from user input.

Unsafe pattern:

```php
$sql = "SELECT * FROM users WHERE id = $id";
```

Preferred approach:

```php
$stmt = $db->prepare(
    "SELECT * FROM users WHERE id = ?"
);
$stmt->execute([$id]);
```

All user-controlled values entering database queries should be reviewed for this property.

---

# 26. Authentication

Authentication establishes the identity of the current user.

The platform uses PHP sessions to maintain authenticated state.

Typical flow:

```text
Login
  ↓
Credentials Validation
  ↓
Session Creation
  ↓
Authenticated Dashboard
```

Authentication and authorization should remain separate concepts.

---

# 27. Authorization & Access Control

A user's role alone is not always sufficient.

The system should enforce both:

### Role-level authorization

Example:

```text
Marketer → Marketer functionality
Merchant → Merchant functionality
Admin → Administration functionality
Support → Support functionality
```

### Object-level authorization

The system must also verify ownership of the requested object.

For example, a marketer should only be able to access their own orders:

```text
orders.user_id == current_user.id
```

Similarly, a merchant should only access products belonging to that merchant:

```text
products.merchant_id == current_merchant.id
```

This protects against **IDOR / Broken Access Control** vulnerabilities.

---

# 28. Race Condition Protection

Financial operations are particularly sensitive to concurrent requests.

The withdrawal system uses database transactions and row locking where implemented.

The goal is to prevent:

```text
Request A ──┐
            ├── Same balance
Request B ──┘
```

from both spending the same funds.

---

# 29. Security Verification

Security controls should not be considered verified merely because they are documented.

Each important control should be tested.

| Security Control    | Verification                                        |
| ------------------- | --------------------------------------------------- |
| CSRF                | Valid, missing, and invalid token testing           |
| XSS                 | Stored XSS test with harmless payload               |
| SQL Injection       | Parameterization/code review and safe input testing |
| Access Control      | Cross-user / cross-role authorization testing       |
| IDOR                | Attempt to access another user's object             |
| Race Condition      | Concurrent withdrawal testing                       |
| Financial Integrity | Duplicate commission and state-transition testing   |
| Session Security    | Session and authentication review                   |
| Debug Exposure      | Production file and endpoint review                 |

Security documentation should distinguish between:

```text
Implemented
```

and:

```text
Verified
```

---

# 30. Application Data Flow

The complete business flow can be summarized as:

```text
                    ┌─────────────┐
                    │   Merchant  │
                    └──────┬──────┘
                           │
                        Products
                           │
                           ▼
                 ┌──────────────────┐
                 │ Digital Soldiers  │
                 └────────┬─────────┘
                          │
                       Products
                          │
                          ▼
                 ┌──────────────────┐
                 │     Marketer     │
                 └────────┬─────────┘
                          │
                      Advertising
                          │
                          ▼
                       Customer
                          │
                        Order
                          │
                          ▼
                    Pending Order
                          │
                          ▼
                 ┌──────────────────┐
                 │ Customer Support │
                 └────────┬─────────┘
                          │
                    Confirmation
                          │
                          ▼
                     Confirmed
                          │
                          ▼
                    Shipping
                          │
                          ▼
                 Out for Delivery
                          │
                          ▼
                      Delivered
                          │
             ┌────────────┼────────────┐
             ▼            ▼            ▼
        Marketer       Merchant      Platform
        Commission      Revenue       Profit
             │
             ▼
       Available Balance
             │
             ▼
         Withdrawal
             │
             ▼
       Admin Processing
```

---

# 31. Important Business Rules

The platform should enforce the following business rules.

### Rule 1 — Delivery is the financial trigger

Creating an order does not automatically create a successful commission.

```text
Order Created ≠ Earned Commission
```

The normal commission event is:

```text
Delivered
```

---

### Rule 2 — Returned orders do not generate successful-delivery commission

```text
Returned → No normal delivery commission
```

---

### Rule 3 — Marketers own their orders

A marketer should not be able to access another marketer's orders simply by modifying an ID in a URL or request.

---

### Rule 4 — Merchants own their products

A merchant should not be able to modify another merchant's products.

---

### Rule 5 — Withdrawals cannot exceed available funds

The system must prevent:

```text
Requested Amount > Available Balance
```

---

### Rule 6 — Financial events must be idempotent

Processing the same business event twice must not create two financial settlements.

Example:

```text
Delivered event
     ↓
Commission created

Same Delivered event again
     ↓
No duplicate commission
```

---

### Rule 7 — Product pricing must be handled consistently

Order calculations should use the correct commercial values associated with the transaction rather than relying blindly on the product's current price after the order has already been created.

---

# 32. Project Structure

The project is organized around role-based pages and shared services.

A simplified structure is:

```text
Digital Soldiers/
│
├── config.php
├── helpers.php
├── balance_system.php
│
├── index.php
├── home111.php
├── add_order.php
├── marketer_orders.php
├── my_finances.php
├── withdrawals.php
├── marketing_dashboard.php
├── creative_performance.php
│
├── merchant_dashboard.php
├── add_product.php
├── merchant_orders.php
│
├── admin_login.php
├── admin_panel.php
├── admin_users.php
├── admin_financial.php
├── admin_withdrawals.php
├── admin_orders.php
├── admin_sheets.php
├── admin_shipping_storage.php
├── admin_marketing.php
│
├── support_dashboard.php
│
└── ...
```

The exact file list may evolve as the system grows.

---

# 33. Current Operational Model

The current platform supports a combination of automated application logic and manual operational processes.

### Automated / application-driven

* User management
* Product management
* Order creation
* Order storage
* Role-based interfaces
* Marketing calculations
* Financial calculations
* Withdrawal processing
* Commission-related logic

### Manual / operational

Some shipping workflows may currently depend on:

* Spreadsheet exports
* Spreadsheet imports
* Manual status updates
* Manual coordination with shipping companies

This is the primary area targeted for future automation.

---

# 34. Future Shipping API Integration

The planned architecture replaces manual shipping workflows with direct API integration.

Future flow:

```text
Confirmed Order
       │
       ▼
Digital Soldiers
       │
       │ Create Shipment API
       ▼
Shipping Company
       │
       ▼
Shipment Created
       │
       ▼
External Tracking ID
       │
       ▼
Delivery Process
```

---

# 35. Webhook Architecture

Instead of repeatedly asking the shipping company for status updates, the shipping provider can notify Digital Soldiers.

Example:

```text
Shipping Company
       │
       │ HTTPS Webhook
       ▼
Digital Soldiers Webhook Endpoint
       │
       ├── Authentication
       ├── Signature Verification
       ├── Timestamp Validation
       ├── Replay Protection
       ├── Input Validation
       ├── Order Lookup
       ├── State Validation
       └── Idempotent Processing
       │
       ▼
Order Status Update
       │
       ▼
Financial Settlement
```

---

# 36. Webhook Security Requirements

Before production deployment of shipping webhooks, the integration should implement:

* HTTPS
* Strong authentication
* Request signature verification
* Timestamp validation
* Replay protection
* Input validation
* Rate limiting where appropriate
* Idempotency
* Valid order/state verification
* Logging and monitoring
* Secure secret management

The webhook must never blindly trust an externally supplied:

```text
order_id
status
amount
```

without validating the request and the corresponding internal business state.

---

# 37. Order State Integrity

The application should prevent invalid state transitions.

Example:

```text
PENDING
   ↓
CONFIRMED
   ↓
SHIPPED
   ↓
OUT_FOR_DELIVERY
   ↓
DELIVERED
```

Invalid transitions should be rejected unless explicitly supported by the business rules.

For example, a random request should not be able to directly change:

```text
PENDING → DELIVERED
```

without satisfying the required workflow.

---

# 38. Financial Settlement Architecture

The ideal conceptual settlement flow is:

```text
Order Delivered
       │
       ▼
Validate Order
       │
       ├── Already Settled?
       │        │
       │        └── Yes → Stop / Ignore Duplicate
       │
       ▼
Calculate Commercial Values
       │
       ├── Marketer Commission
       ├── Merchant Amount
       └── Platform Profit
       │
       ▼
Record Financial Events
       │
       ▼
Mark Order as Settled
```

This ensures that financial processing is tied to an explicit business event.

---

# 39. Recommended Database Integrity

The application should use database constraints wherever appropriate.

Potential relationships include:

```text
products.merchant_id
        → users.id

orders.user_id
        → users.id

order_items.order_id
        → orders.id

order_items.product_id
        → products.id

marketer_commissions.order_id
        → orders.id

marketer_commissions.user_id
        → users.id

withdrawals.user_id
        → users.id

marketing_daily_spend.user_id
        → users.id
```

Where foreign keys are implemented, the database itself can enforce referential integrity rather than relying entirely on application logic.

---

# 40. Development & Security Principles

The project should follow these principles as it evolves:

### Least Privilege

Every role should have only the permissions necessary for its responsibilities.

### Defense in Depth

Security should not depend on a single control.

### Server-Side Validation

Client-side validation should improve UX but must never be treated as a security boundary.

### Parameterized Queries

All database input must be handled safely.

### Output Encoding

User-controlled content must be encoded appropriately before rendering.

### Transactional Financial Operations

Financial operations should be atomic and concurrency-safe.

### Ownership Verification

Access to objects must be verified against the authenticated user.

### Idempotency

Repeated requests or events should not create duplicate financial effects.

### Auditability

Important financial and administrative actions should be traceable.

---

# 41. Known Limitations / Areas for Verification

The following areas should continue to be verified through code review and security testing:

* Complete CSRF coverage across every state-changing endpoint
* Stored and reflected XSS protection
* SQL injection protection across all database queries
* Object-level authorization / IDOR protection
* Session security
* Duplicate commission prevention
* Financial state consistency
* Concurrent withdrawal handling
* Order-state transition enforcement
* Database foreign-key enforcement
* Production debug-file exposure
* Shipping API authentication
* Webhook replay protection
* Audit logging

Documentation of a security control should not be treated as proof that the control has been completely verified.

---

# 42. Security Testing Philosophy

The security testing process follows:

```text
Identify
   ↓
Understand
   ↓
Test
   ↓
Capture Evidence
   ↓
Fix
   ↓
Retest
   ↓
Document
```

For every security issue:

```text
Before Fix
    ↓
Proof of Vulnerability
    ↓
Remediation
    ↓
After Fix
    ↓
Proof of Protection
```

This approach ensures that security claims are supported by actual verification rather than documentation alone.

---

# 43. Future Roadmap

## Phase 1 — Core Platform

* User management
* Product management
* Order management
* Support workflow
* Marketing dashboard
* Financial system
* Withdrawals

## Phase 2 — Security Hardening

* Full authorization audit
* IDOR testing
* CSRF coverage verification
* XSS verification
* SQL injection audit
* Session security review
* Financial integrity testing
* Duplicate commission protection
* Audit logging

## Phase 3 — Shipping Integration

* Shipping provider API
* Automatic shipment creation
* Tracking IDs
* Webhooks
* Automatic status synchronization
* Delivery confirmation

## Phase 4 — Advanced Analytics

* Marketer ranking
* Performance signals
* Creative performance
* Campaign analysis
* Advanced profitability analytics
* Automated performance recommendations

---

# 44. High-Level System Summary

Digital Soldiers can be understood through five major layers:

```text
┌─────────────────────────────────────────────┐
│              USER INTERFACES                │
│ Admin / Marketer / Merchant / Support       │
├─────────────────────────────────────────────┤
│             BUSINESS LOGIC                  │
│ Orders / Products / Commissions / Finance   │
├─────────────────────────────────────────────┤
│             DATA LAYER                      │
│ MySQL / Users / Orders / Products           │
├─────────────────────────────────────────────┤
│             SECURITY                        │
│ Auth / Authorization / CSRF / XSS / SQL     │
│ Transactions / Ownership / Integrity        │
├─────────────────────────────────────────────┤
│          EXTERNAL INTEGRATIONS              │
│ Shipping APIs / Webhooks / Future Services  │
└─────────────────────────────────────────────┘
```

---

# 45. Complete Business Flow

The entire platform can ultimately be summarized as:

```text
Merchant
   │
   │ Adds Product
   ▼
Product Catalog
   │
   ▼
Marketer
   │
   │ Advertises Product
   ▼
Customer
   │
   │ Places Order
   ▼
Pending Order
   │
   ▼
Customer Support
   │
   ├───────────────┐
   │               │
Confirmed       Cancelled
   │
   ▼
Shipping
   │
   ├───────────────┐
   │               │
Delivered       Returned
   │
   ▼
Financial Settlement
   │
   ├───────────────┐
   │               │
Marketer       Merchant
Commission      Revenue
   │
   └───────┬───────┘
           │
           ▼
      Platform Profit
           │
           ▼
   Marketer Available Balance
           │
           ▼
       Withdrawal
           │
           ▼
      Admin Review
```

---

# 46. Project Status

**Project:** Digital Soldiers
**Type:** Affiliate Marketing / E-commerce / Drop-shipping Platform
**Backend:** PHP
**Database:** MySQL
**Frontend:** HTML / CSS / JavaScript / Tailwind CSS
**Architecture:** Custom PHP / Modular Application
**Financial Model:** Transaction-based / Dynamically calculated balances
**Shipping:** Manual / Semi-automated workflow with API integration planned
**Security:** Multi-layer security controls with ongoing verification

---

# 47. Documentation Principle

This README describes the intended architecture and business behavior of the Digital Soldiers platform.

Where a feature is described as a **security control**, **financial protection**, or **business rule**, implementation should be verified against the actual source code and tested behavior.

The goal is to maintain a system where:

```text
Business Logic
      +
Implementation
      +
Database Integrity
      +
Security Controls
      +
Testing Evidence
```

all remain consistent with each other.

---

## Digital Soldiers

**Affiliate Marketing • E-commerce • Drop-shipping • Financial Management • Marketing Analytics**
