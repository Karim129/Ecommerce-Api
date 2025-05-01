# E-commerce API

A robust RESTful API for e-commerce applications built with Laravel, featuring user authentication, product management, shopping cart functionality, order processing, and payment integration with Stripe and PayPal.

## Features

-   **Authentication**

    -   Email/Password registration and login
    -   Google OAuth integration
    -   Email verification
    -   Token-based authentication using Laravel Sanctum
    -   Password reset functionality

-   **User Management**

    -   User roles (Admin, Customer)
    -   User profile management
    -   Permission-based access control using Spatie Laravel-permission

-   **Product Management**

    -   Category CRUD operations
    -   Product CRUD operations with image upload
    -   Product search and filtering
    -   Multi-language support for product details

-   **Shopping Cart**

    -   Add/remove products
    -   Update quantities
    -   Clear cart
    -   Persistent cart storage

-   **Order Management**

    -   Order creation and processing
    -   Order status tracking
    -   Order history
    -   Refund processing

-   **Payment Integration**

    -   Stripe payment processing
    -   PayPal payment processing
    -   Webhook handling for payment events
    -   Secure payment flow

-   **API Documentation**
    -   Swagger/OpenAPI documentation
    -   Detailed API endpoints documentation
    -   Request/Response examples

## Requirements

-   PHP >= 8.2
-   Composer
-   MySQL/PostgreSQL/SQLite
-   Node.js & NPM (for development)
-   SSL certificate (for production)

## Installation

1. Clone the repository:

    ```bash
    git clone <repository-url>
    cd ecommerce-api
    ```

2. Install PHP dependencies:

    ```bash
    composer install
    ```

3. Copy the environment file and configure it:

    ```bash
    cp .env.example .env
    ```

4. Generate application key:

    ```bash
    php artisan key:generate
    ```

5. Configure your database in .env:

    ```
    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=your_database
    DB_USERNAME=your_username
    DB_PASSWORD=your_password
    ```

6. Run migrations and seeders:

    ```bash
    php artisan migrate --seed
    ```

7. Create storage link for public file access:

    ```bash
    php artisan storage:link
    ```

8. Configure payment providers in .env:

    ```
    STRIPE_KEY=your_stripe_public_key
    STRIPE_SECRET=your_stripe_secret_key
    STRIPE_WEBHOOK_SECRET=your_stripe_webhook_secret

    PAYPAL_CLIENT_ID=your_paypal_client_id
    PAYPAL_SECRET=your_paypal_secret
    PAYPAL_WEBHOOK_ID=your_paypal_webhook_id
    ```

9. Configure Google OAuth (optional):
    ```
    GOOGLE_CLIENT_ID=your_google_client_id
    GOOGLE_CLIENT_SECRET=your_google_client_secret
    ```

## Running the Application

1. Start the development server:

    ```bash
    php artisan serve
    ```

2. (Optional) Start the queue worker for background jobs:
    ```bash
    php artisan queue:work
    ```

The API will be available at `http://localhost:8000`.

## API Documentation

The API documentation is generated using L5-Swagger. To view the documentation:

1. Generate the documentation:

    ```bash
    php artisan l5-swagger:generate
    ```

2. Access the Swagger UI at:
    ```
    http://localhost:8000/api/documentation
    ```

## Testing

Run the test suite:

```bash
php artisan test
```

## Available Endpoints

### Public Endpoints

-   `POST /api/register` - User registration
-   `POST /api/login` - User login
-   `POST /api/login/google` - Google OAuth login
-   `GET /api/categories` - List all categories
-   `GET /api/products` - List all products
-   `GET /api/email/verify/{id}/{hash}` - Email verification

### Protected Endpoints

-   `GET /api/user` - Get authenticated user details
-   `POST /api/logout` - User logout
-   `GET /api/cart` - View shopping cart
-   `POST /api/cart` - Add items to cart
-   `GET /api/orders` - List user orders
-   `POST /api/orders` - Create new order

### Admin Endpoints

-   `POST /api/categories` - Create category
-   `POST /api/products` - Create product
-   `GET /api/users` - List all users
-   `PUT /api/orders/{order}/status` - Update order status

## Error Handling

The API uses standard HTTP response codes and returns errors in the following format:

```json
{
    "message": "Error message",
    "errors": {
        "field": ["Error description"]
    }
}
```

## Security

-   API uses Laravel Sanctum for token-based authentication
-   CORS support for frontend integration
-   Rate limiting on authentication endpoints
-   Input validation and sanitization
-   XSS protection
-   SQL injection prevention

## Contributing

1. Fork the repository
2. Create a new branch for your feature
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is open-sourced software licensed under the MIT license.
