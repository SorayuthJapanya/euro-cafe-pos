# Euro Cafe POS — Backend API Design Documentation

> **Stack:** Laravel 11 · MySQL · Laravel Sanctum  
> **Scope:** Single-location counter cafe · Cash + QR/PromptPay payments · Menu management  
> **Base URL:** `/api`  
> **Auth:** Bearer Token (Sanctum)  
> **Content-Type:** `application/json`

---

## Table of Contents

1. [System Design Summary](#1-system-design-summary)
2. [Standardized Response Format](#2-standardized-response-format)
3. [Authentication Module](#3-authentication-module)
4. [Categories Module](#4-categories-module)
5. [Products Module](#5-products-module)
6. [Orders Module](#6-orders-module)
7. [Payments Module](#7-payments-module)
8. [Receipts Module](#8-receipts-module)
9. [Settings Module](#9-settings-module)
10. [Database Schema](#10-database-schema)
11. [Laravel Implementation](#11-laravel-implementation)
12. [Security & Best Practices](#12-security--best-practices)
13. [Missing APIs & Future Considerations](#13-missing-apis--future-considerations)
14. [Production Deployment Recommendations](#14-production-deployment-recommendations)

---

## 1. System Design Summary

### Architecture

```
Client (POS Terminal / Admin Panel)
        │
        ▼
  Laravel REST API  ──────────────────────────────────┐
  ├── Auth (Sanctum)                                   │
  ├── Categories                                       │
  ├── Products                                         │
  ├── Orders                                           │
  ├── Payments (Cash / QR PromptPay)                   │
  ├── Receipts                                         │
  └── Settings                                    MySQL DB
```

### Role System

Two roles managed via a `role` column on the `users` table:

| Role | Capabilities |
|------|-------------|
| `admin` | Full access — manage menu, view all orders, update settings |
| `staff` | Create/view orders, process payments |

### Order Lifecycle

```
[pending] ──► [completed]
    │
    └──► [cancelled]
```

### Payment Lifecycle

```
[pending] ──► [paid]
    │
    └──► [failed]
```

---

## 2. Standardized Response Format

All endpoints return a consistent JSON envelope.

### Success Response

```json
{
  "success": true,
  "message": "Order created successfully",
  "data": { ... }
}
```

### Success with Pagination

```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 72
  }
}
```

### Validation Error (422)

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "amount": ["The amount field is required."],
    "method": ["The selected method is invalid."]
  }
}
```

### General Error (401 / 403 / 404 / 500)

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

---

## 3. Authentication Module

### POST `/api/auth/login`

Authenticates a user and returns a Sanctum token.

**Request Body:**
```json
{
  "email": "cashier@eurocafe.com",
  "password": "secret123"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "1|AbcDefGhiJklMno...",
    "user": {
      "id": 1,
      "name": "Som Cashier",
      "email": "cashier@eurocafe.com",
      "role": "staff"
    }
  }
}
```

**Error (401):**
```json
{
  "success": false,
  "message": "Invalid credentials"
}
```

---

### POST `/api/auth/logout`

Revokes the current token.

**Headers:** `Authorization: Bearer <token>`

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### GET `/api/auth/me`

Returns the authenticated user's profile.

**Headers:** `Authorization: Bearer <token>`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Som Cashier",
    "email": "cashier@eurocafe.com",
    "role": "staff",
    "created_at": "2024-01-15T08:00:00Z"
  }
}
```

---

## 4. Categories Module

All write operations require `admin` role.

### GET `/api/categories`

Returns all categories, ordered by `sort_order`.

**Headers:** `Authorization: Bearer <token>`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "Coffee", "color": "#6F4E37", "sort_order": 1 },
    { "id": 2, "name": "Bakery", "color": "#FFC0CB", "sort_order": 2 },
    { "id": 3, "name": "Cold Drinks", "color": "#87CEEB", "sort_order": 3 }
  ]
}
```

---

### POST `/api/categories`

**Role required:** `admin`

**Request Body:**
```json
{
  "name": "Seasonal Specials",
  "color": "#FFD700",
  "sort_order": 4
}
```

**Validation Rules:**
- `name` — required, string, max:100, unique:categories
- `color` — required, string, regex:/^#[0-9A-Fa-f]{6}$/
- `sort_order` — optional, integer, min:0

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Category created",
  "data": { "id": 4, "name": "Seasonal Specials", "color": "#FFD700", "sort_order": 4 }
}
```

---

### PUT `/api/categories/{id}`

**Role required:** `admin`

**Request Body:** Same fields as POST, all optional.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Category updated",
  "data": { "id": 4, "name": "Seasonal Specials", "color": "#FF6347", "sort_order": 4 }
}
```

---

### DELETE `/api/categories/{id}`

**Role required:** `admin`

Soft-delete prevention: returns `409 Conflict` if the category has associated products.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Category deleted"
}
```

**Error (409 Conflict):**
```json
{
  "success": false,
  "message": "Cannot delete category with existing products. Remove or reassign products first."
}
```

---

## 5. Products Module

### GET `/api/products`

Supports filtering and is used by the POS terminal to render the menu.

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `category_id` | integer | Filter by category |
| `is_available` | boolean | Filter available/unavailable items |
| `search` | string | Search by product name |

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Americano",
      "description": "Espresso with hot water",
      "price": 65.00,
      "is_available": true,
      "image_url": "https://cdn.eurocafe.com/americano.jpg",
      "category": { "id": 1, "name": "Coffee", "color": "#6F4E37" }
    }
  ]
}
```

---

### GET `/api/products/{id}`

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Americano",
    "description": "Espresso with hot water",
    "price": 65.00,
    "is_available": true,
    "image_url": "https://cdn.eurocafe.com/americano.jpg",
    "category": { "id": 1, "name": "Coffee" }
  }
}
```

---

### POST `/api/products`

**Role required:** `admin`

**Request Body:**
```json
{
  "category_id": 1,
  "name": "Oat Milk Latte",
  "description": "Espresso with oat milk",
  "price": 85.00,
  "is_available": true,
  "image_url": "https://cdn.eurocafe.com/oat-latte.jpg"
}
```

**Validation Rules:**
- `category_id` — required, exists:categories,id
- `name` — required, string, max:150
- `description` — optional, string, max:500
- `price` — required, numeric, min:0
- `is_available` — optional, boolean (default: true)
- `image_url` — optional, url, max:500

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Product created",
  "data": { "id": 12, "name": "Oat Milk Latte", "price": 85.00, ... }
}
```

---

### PUT `/api/products/{id}`

**Role required:** `admin`

**Request Body:** Same as POST, all fields optional.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Product updated",
  "data": { ... }
}
```

---

### DELETE `/api/products/{id}`

**Role required:** `admin`

Uses soft delete (`deleted_at`) so historical order data remains intact.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Product removed from menu"
}
```

---

## 6. Orders Module

### GET `/api/orders`

**Query Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter: `pending`, `completed`, `cancelled` |
| `date` | date (Y-m-d) | Filter by specific date |
| `per_page` | integer | Items per page (default: 15) |

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 101,
      "order_number": "ORD-20241024-0001",
      "status": "completed",
      "subtotal": 150.00,
      "discount_amount": 0.00,
      "total_amount": 150.00,
      "notes": null,
      "cashier": { "id": 2, "name": "Som Cashier" },
      "items_count": 2,
      "created_at": "2024-10-24T09:15:00Z"
    }
  ],
  "meta": { "current_page": 1, "last_page": 3, "per_page": 15, "total": 42 }
}
```

---

### POST `/api/orders`

Creates a new order with all line items in a single request. The order is created with `status: pending` until payment is confirmed.

**Request Body:**
```json
{
  "items": [
    { "product_id": 1, "quantity": 2 },
    { "product_id": 5, "quantity": 1 }
  ],
  "discount_amount": 20.00,
  "notes": "Extra ice for item 1"
}
```

**Validation Rules:**
- `items` — required, array, min:1
- `items.*.product_id` — required, exists:products,id, product must be available
- `items.*.quantity` — required, integer, min:1
- `discount_amount` — optional, numeric, min:0
- `notes` — optional, string, max:500

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Order created",
  "data": {
    "id": 102,
    "order_number": "ORD-20241024-0002",
    "status": "pending",
    "subtotal": 215.00,
    "discount_amount": 20.00,
    "total_amount": 195.00,
    "notes": "Extra ice for item 1",
    "items": [
      {
        "id": 201,
        "product": { "id": 1, "name": "Americano" },
        "quantity": 2,
        "unit_price": 65.00,
        "subtotal": 130.00
      },
      {
        "id": 202,
        "product": { "id": 5, "name": "Croissant" },
        "quantity": 1,
        "unit_price": 85.00,
        "subtotal": 85.00
      }
    ],
    "cashier": { "id": 2, "name": "Som Cashier" },
    "created_at": "2024-10-24T09:20:00Z"
  }
}
```

---

### GET `/api/orders/{id}`

Returns full order detail including items and payment info.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 102,
    "order_number": "ORD-20241024-0002",
    "status": "completed",
    "subtotal": 215.00,
    "discount_amount": 20.00,
    "total_amount": 195.00,
    "notes": "Extra ice for item 1",
    "items": [ ... ],
    "payment": {
      "id": 55,
      "method": "cash",
      "status": "paid",
      "amount_paid": 200.00,
      "change_amount": 5.00,
      "paid_at": "2024-10-24T09:21:30Z"
    },
    "cashier": { "id": 2, "name": "Som Cashier" },
    "created_at": "2024-10-24T09:20:00Z"
  }
}
```

---

### PATCH `/api/orders/{id}/cancel`

Cancels a `pending` order. Cannot cancel a `completed` order.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Order cancelled",
  "data": { "id": 102, "status": "cancelled" }
}
```

**Error (422):**
```json
{
  "success": false,
  "message": "Only pending orders can be cancelled"
}
```

---

## 7. Payments Module

### POST `/api/orders/{id}/payment`

Creates a payment for a pending order. Behavior differs by method.

**For cash payment:**
```json
{
  "method": "cash",
  "amount_paid": 200.00
}
```

**Response (200 OK) — order immediately marked `completed`:**
```json
{
  "success": true,
  "message": "Payment recorded. Change: ฿5.00",
  "data": {
    "payment_id": 55,
    "method": "cash",
    "status": "paid",
    "amount_paid": 200.00,
    "change_amount": 5.00,
    "order_status": "completed"
  }
}
```

---

**For QR/PromptPay payment:**
```json
{
  "method": "qr_promptpay"
}
```

**Response (200 OK) — payment stays `pending`, QR code returned:**
```json
{
  "success": true,
  "message": "QR code generated. Awaiting payment.",
  "data": {
    "payment_id": 56,
    "method": "qr_promptpay",
    "status": "pending",
    "amount": 195.00,
    "qr_image_url": "https://api.eurocafe.com/qr/order-102.png",
    "qr_reference": "REF202410240002",
    "expires_at": "2024-10-24T09:35:00Z"
  }
}
```

---

**Validation Rules:**
- `method` — required, in:cash,qr_promptpay
- `amount_paid` — required_if:method,cash, numeric, min:`total_amount`
- Order must be in `pending` status
- Order must not already have a payment

---

### GET `/api/orders/{id}/payment/status`

Polls the current payment status for a QR transaction. Call this every 3–5 seconds from the POS terminal until `status` is `paid` or `failed`.

**Response (200 OK) — still pending:**
```json
{
  "success": true,
  "data": {
    "payment_id": 56,
    "status": "pending",
    "order_status": "pending"
  }
}
```

**Response (200 OK) — paid:**
```json
{
  "success": true,
  "data": {
    "payment_id": 56,
    "status": "paid",
    "order_status": "completed",
    "paid_at": "2024-10-24T09:23:10Z"
  }
}
```

---

## 8. Receipts Module

### GET `/api/orders/{id}/receipt`

Returns structured receipt data. The frontend uses this to render a print-ready receipt.

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "shop_name": "Euro Cafe",
    "receipt_number": "ORD-20241024-0002",
    "cashier": "Som Cashier",
    "items": [
      { "name": "Americano", "qty": 2, "unit_price": 65.00, "subtotal": 130.00 },
      { "name": "Croissant",  "qty": 1, "unit_price": 85.00, "subtotal": 85.00 }
    ],
    "subtotal": 215.00,
    "discount": 20.00,
    "total": 195.00,
    "payment_method": "cash",
    "amount_paid": 200.00,
    "change": 5.00,
    "issued_at": "2024-10-24T09:21:30Z",
    "footer": "Thank you for visiting Euro Cafe!"
  }
}
```

---

## 9. Settings Module

**Role required:** `admin` (write)

### GET `/api/settings`

```json
{
  "success": true,
  "data": {
    "shop_name": "Euro Cafe",
    "currency": "THB",
    "tax_rate": 0,
    "receipt_footer": "Thank you for visiting Euro Cafe!",
    "promptpay_id": "0812345678"
  }
}
```

---

### PUT `/api/settings`

**Request Body:**
```json
{
  "shop_name": "Euro Cafe Sukhumvit",
  "receipt_footer": "See you next time!",
  "promptpay_id": "0812345678"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Settings updated",
  "data": { ... }
}
```

---

## 10. Database Schema

### Migrations

```php
// users
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->enum('role', ['admin', 'staff'])->default('staff');
    $table->rememberToken();
    $table->timestamps();
});

// categories
Schema::create('categories', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100)->unique();
    $table->string('color', 7)->default('#6B7280');
    $table->unsignedSmallInteger('sort_order')->default(0);
    $table->timestamps();
    $table->index('sort_order');
});

// products
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('category_id')->constrained()->restrictOnDelete();
    $table->string('name', 150);
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->boolean('is_available')->default(true);
    $table->string('image_url', 500)->nullable();
    $table->softDeletes();
    $table->timestamps();
    $table->index(['category_id', 'is_available']);
});

// orders
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->restrictOnDelete();
    $table->string('order_number')->unique();
    $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');
    $table->decimal('subtotal', 10, 2);
    $table->decimal('discount_amount', 10, 2)->default(0);
    $table->decimal('total_amount', 10, 2);
    $table->text('notes')->nullable();
    $table->timestamps();
    $table->index('status');
    $table->index('created_at');
});

// order_items
Schema::create('order_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->restrictOnDelete();
    $table->unsignedSmallInteger('quantity');
    $table->decimal('unit_price', 10, 2);
    $table->decimal('subtotal', 10, 2);
    $table->timestamps();
    $table->unique(['order_id', 'product_id']);
});

// payments
Schema::create('payments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
    $table->enum('method', ['cash', 'qr_promptpay']);
    $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
    $table->decimal('amount_paid', 10, 2)->nullable();
    $table->decimal('change_amount', 10, 2)->nullable();
    $table->string('qr_reference')->nullable()->index();
    $table->string('qr_image_url', 500)->nullable();
    $table->timestamp('paid_at')->nullable();
    $table->timestamps();
});

// settings
Schema::create('settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->text('value')->nullable();
    $table->timestamps();
});
```

### Relationships Summary

```
User          ──< Order (user_id)
Order         ──< OrderItem (order_id)
Order         ──1 Payment (order_id, unique)
Product       ──< OrderItem (product_id)
Category      ──< Product (category_id)
```

---

## 11. Laravel Implementation

### routes/api.php

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\SettingController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);

    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel']);

    Route::post('orders/{order}/payment', [PaymentController::class, 'store']);
    Route::get('orders/{order}/payment/status', [PaymentController::class, 'status']);
    Route::get('orders/{order}/receipt', [ReceiptController::class, 'show']);

    Route::middleware('role:admin')->group(function () {
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);
    });
});
```

---

### Middleware — CheckRole

```php
<?php
// app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): mixed
    {
        if ($request->user()?->role !== $role) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }
        return $next($request);
    }
}
```

```php
// bootstrap/app.php — register alias
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias(['role' => \App\Http\Middleware\CheckRole::class]);
})
```

---

### AuthController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('pos-terminal')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data'    => ['token' => $token, 'user' => $user->only('id', 'name', 'email', 'role')],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $request->user()]);
    }
}
```

---

### LoginRequest

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }
}
```

---

### OrderController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['user:id,name', 'items'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->date,   fn ($q, $v) => $q->whereDate('created_at', $v))
            ->latest()
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'data'    => $orders->items(),
            'meta'    => [
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
                'per_page'     => $orders->perPage(),
                'total'        => $orders->total(),
            ],
        ]);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $productIds = collect($request->items)->pluck('product_id');
        $products   = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $items    = [];
        $subtotal = 0;

        foreach ($request->items as $item) {
            $product    = $products[$item['product_id']];
            $lineTotal  = $product->price * $item['quantity'];
            $subtotal  += $lineTotal;

            $items[] = [
                'product_id' => $product->id,
                'quantity'   => $item['quantity'],
                'unit_price' => $product->price,
                'subtotal'   => $lineTotal,
            ];
        }

        $discount = $request->discount_amount ?? 0;
        $total    = max(0, $subtotal - $discount);

        $order = Order::create([
            'user_id'         => $request->user()->id,
            'order_number'    => $this->generateOrderNumber(),
            'status'          => 'pending',
            'subtotal'        => $subtotal,
            'discount_amount' => $discount,
            'total_amount'    => $total,
            'notes'           => $request->notes,
        ]);

        $order->items()->createMany($items);

        return response()->json([
            'success' => true,
            'message' => 'Order created',
            'data'    => $order->load('items.product', 'user:id,name'),
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => $order->load('items.product', 'payment', 'user:id,name'),
        ]);
    }

    public function cancel(Order $order): JsonResponse
    {
        if ($order->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Only pending orders can be cancelled'], 422);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json(['success' => true, 'message' => 'Order cancelled', 'data' => $order]);
    }

    private function generateOrderNumber(): string
    {
        $date     = now()->format('Ymd');
        $sequence = str_pad(Order::whereDate('created_at', today())->count() + 1, 4, '0', STR_PAD_LEFT);
        return "ORD-{$date}-{$sequence}";
    }
}
```

---

### StoreOrderRequest

```php
<?php

namespace App\Http\Requests\Order;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'items'               => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],
            'discount_amount'     => ['sometimes', 'numeric', 'min:0'],
            'notes'               => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ($this->items ?? [] as $index => $item) {
                    $product = Product::find($item['product_id'] ?? null);
                    if ($product && ! $product->is_available) {
                        $validator->errors()->add(
                            "items.{$index}.product_id",
                            "Product \"{$product->name}\" is currently unavailable."
                        );
                    }
                }
            },
        ];
    }
}
```

---

### PaymentController

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Order $order): JsonResponse
    {
        if ($order->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Order is not pending'], 422);
        }

        if ($order->payment()->exists()) {
            return response()->json(['success' => false, 'message' => 'Payment already recorded for this order'], 409);
        }

        if ($request->method === 'cash') {
            return $this->processCash($order, $request->amount_paid);
        }

        return $this->generateQr($order);
    }

    private function processCash(Order $order, float $amountPaid): JsonResponse
    {
        $change = round($amountPaid - $order->total_amount, 2);

        $payment = $order->payment()->create([
            'method'        => 'cash',
            'status'        => 'paid',
            'amount_paid'   => $amountPaid,
            'change_amount' => $change,
            'paid_at'       => now(),
        ]);

        $order->update(['status' => 'completed']);

        return response()->json([
            'success' => true,
            'message' => "Payment recorded. Change: ฿{$change}",
            'data'    => array_merge($payment->toArray(), ['order_status' => 'completed']),
        ]);
    }

    private function generateQr(Order $order): JsonResponse
    {
        $reference = 'REF' . now()->format('YmdHis') . str_pad($order->id, 4, '0', STR_PAD_LEFT);

        // Replace this stub with your actual PromptPay QR library call
        $qrImageUrl = "https://promptpay.io/{$this->getPromptPayId()}/{$order->total_amount}.png";

        $payment = $order->payment()->create([
            'method'        => 'qr_promptpay',
            'status'        => 'pending',
            'qr_reference'  => $reference,
            'qr_image_url'  => $qrImageUrl,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'QR code generated. Awaiting payment.',
            'data'    => [
                'payment_id'    => $payment->id,
                'method'        => 'qr_promptpay',
                'status'        => 'pending',
                'amount'        => $order->total_amount,
                'qr_image_url'  => $qrImageUrl,
                'qr_reference'  => $reference,
                'expires_at'    => now()->addMinutes(15)->toISOString(),
            ],
        ]);
    }

    public function status(Order $order): JsonResponse
    {
        $payment = $order->payment;

        if (! $payment) {
            return response()->json(['success' => false, 'message' => 'No payment found for this order'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'payment_id'   => $payment->id,
                'status'       => $payment->status,
                'order_status' => $order->status,
                'paid_at'      => $payment->paid_at,
            ],
        ]);
    }

    private function getPromptPayId(): string
    {
        return \App\Models\Setting::getValue('promptpay_id', '');
    }
}
```

---

### StorePaymentRequest

```php
<?php

namespace App\Http\Requests\Payment;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'method'      => ['required', 'in:cash,qr_promptpay'],
            'amount_paid' => ['required_if:method,cash', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->method === 'cash' && $this->amount_paid < $this->route('order')->total_amount) {
                $v->errors()->add('amount_paid', 'Amount paid is less than the order total.');
            }
        });
    }
}
```

---

### Models

```php
<?php
// app/Models/Order.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = ['user_id', 'order_number', 'status', 'subtotal', 'discount_amount', 'total_amount', 'notes'];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function items(): HasMany      { return $this->hasMany(OrderItem::class); }
    public function payment(): HasOne     { return $this->hasOne(Payment::class); }
}
```

```php
<?php
// app/Models/OrderItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'quantity', 'unit_price', 'subtotal'];

    public function order(): BelongsTo   { return $this->belongsTo(Order::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
```

```php
<?php
// app/Models/Payment.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['order_id', 'method', 'status', 'amount_paid', 'change_amount', 'qr_reference', 'qr_image_url', 'paid_at'];
    protected $casts = ['paid_at' => 'datetime'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
```

```php
<?php
// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = ['category_id', 'name', 'description', 'price', 'is_available', 'image_url'];
    protected $casts = ['is_available' => 'boolean', 'price' => 'decimal:2'];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
```

```php
<?php
// app/Models/Setting.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }
}
```

### ReceiptController

```php
<?php
// app/Http/Controllers/Api/ReceiptController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ReceiptController extends Controller
{
    public function show(Order $order): JsonResponse
    {
        if ($order->status !== 'completed') {
            return response()->json(['success' => false, 'message' => 'Receipt only available for completed orders'], 422);
        }

        $order->load('items.product', 'payment', 'user:id,name');

        return response()->json([
            'success' => true,
            'data' => [
                'shop_name'      => Setting::getValue('shop_name', 'Euro Cafe'),
                'receipt_number' => $order->order_number,
                'cashier'        => $order->user->name,
                'items'          => $order->items->map(fn ($item) => [
                    'name'       => $item->product->name,
                    'qty'        => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal'   => $item->subtotal,
                ]),
                'subtotal'       => $order->subtotal,
                'discount'       => $order->discount_amount,
                'total'          => $order->total_amount,
                'payment_method' => $order->payment?->method,
                'amount_paid'    => $order->payment?->amount_paid,
                'change'         => $order->payment?->change_amount,
                'issued_at'      => $order->payment?->paid_at,
                'footer'         => Setting::getValue('receipt_footer', 'Thank you!'),
            ],
        ]);
    }
}
```

---

### SettingController

```php
<?php
// app/Http/Controllers/Api/SettingController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private array $allowedKeys = ['shop_name', 'currency', 'tax_rate', 'receipt_footer', 'promptpay_id'];

    public function index(): JsonResponse
    {
        $settings = Setting::whereIn('key', $this->allowedKeys)->pluck('value', 'key');
        return response()->json(['success' => true, 'data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'shop_name'      => ['sometimes', 'string', 'max:100'],
            'currency'       => ['sometimes', 'string', 'size:3'],
            'tax_rate'       => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'receipt_footer' => ['sometimes', 'nullable', 'string', 'max:300'],
            'promptpay_id'   => ['sometimes', 'string', 'max:20'],
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return response()->json(['success' => true, 'message' => 'Settings updated', 'data' => $data]);
    }
}
```

---

## 12. Security & Best Practices

### Rate Limiting

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->throttleApi();
})
```

```php
// routes/api.php — stricter limit on login
Route::middleware('throttle:5,1')->group(function () {  // 5 attempts per minute
    Route::post('auth/login', [AuthController::class, 'login']);
});
```

### Input Validation

- All write endpoints use dedicated `FormRequest` classes
- Enum fields validated with `Rule::in()` or `in:` rule — no raw DB values from user input
- Foreign keys validated with `exists:` — prevents orphaned records
- Payment `amount_paid` cross-validated against live `order->total_amount` in `withValidator`

### Error Handling

Register a global JSON error handler in `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->expectsJson()) {
            $status  = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500;
            $message = $status < 500 ? $e->getMessage() : 'Server error';
            return response()->json(['success' => false, 'message' => $message], $status);
        }
    });
})
```

### Security Headers

Add to your web server config or a middleware:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: no-referrer
```

### Sanctum Token Expiry

```php
// config/sanctum.php
'expiration' => 60 * 12,  // tokens expire after 12 hours
```

---

## 13. Missing APIs & Future Considerations

The following APIs are **not included** in the current scope but would be needed for a more complete system:

| Missing Feature | Suggested Endpoint | Priority |
|----------------|-------------------|----------|
| Staff management | `GET/POST /api/users` | Medium |
| Change staff password | `PUT /api/users/{id}/password` | Medium |
| Daily sales summary | `GET /api/reports/daily` | Medium |
| Revenue by category | `GET /api/reports/categories` | Low |
| QR webhook callback | `POST /api/webhooks/promptpay` | High (if using real gateway) |
| Order void / refund | `POST /api/orders/{id}/refund` | Medium |
| Discount codes | `GET/POST /api/discounts` | Low |
| Product image upload | `POST /api/products/{id}/image` | Low |
| Offline sync support | `POST /api/orders/sync` | Low |

---

## 14. Production Deployment Recommendations

### Environment

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.eurocafe.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=eurocafe_pos

SANCTUM_STATEFUL_DOMAINS=pos.eurocafe.com
SESSION_DOMAIN=.eurocafe.com

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
```

### Server Setup

- **PHP 8.2+** with `opcache` enabled
- **Nginx** as web server, point root to `/public`
- **MySQL 8.0+** — enable `slow_query_log` for monitoring
- **Redis** for cache and session (prevents file-lock contention)
- **SSL/TLS** — enforce HTTPS at the Nginx level, never allow plain HTTP

### Nginx Config (minimal)

```nginx
server {
    listen 443 ssl;
    server_name api.eurocafe.com;
    root /var/www/eurocafe/public;

    add_header X-Frame-Options "DENY";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

### Deployment Checklist

- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan migrate --force`
- [ ] `php artisan db:seed --class=SettingsSeeder` (initial shop settings)
- [ ] Set file permissions: `storage/` and `bootstrap/cache/` writable by web user
- [ ] Configure daily DB backups (e.g. `mysqldump` via cron or Spatie Laravel Backup)
- [ ] Set up log rotation (`storage/logs/laravel.log`)
- [ ] Test QR PromptPay flow end-to-end before going live

---

*Generated: 2026-04-30 · Euro Cafe POS · Laravel 11 · Single Location · Cash + QR/PromptPay*
