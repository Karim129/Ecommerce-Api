# Payment Testing Guide

## Stripe Test Cards

### Successful Payment

-   Card Number: 4242 4242 4242 4242
-   Expiry: Any future date (e.g., 12/25)
-   CVC: Any 3 digits (e.g., 123)

### Insufficient Funds

-   Card Number: 4000 0000 0000 9995
-   Expiry: Any future date
-   CVC: Any 3 digits

### Card Declined

-   Card Number: 4000 0000 0000 0002
-   Expiry: Any future date
-   CVC: Any 3 digits

## Test Steps

1. Success Scenario:

    ```bash
    curl -X POST http://localhost:8000/api/orders \
    -H "Authorization: Bearer YOUR_AUTH_TOKEN" \
    -H "Content-Type: application/json" \
    -d '{
      "city": "Test City",
      "address": "123 Test St",
      "building_number": "45",
      "payment_method": "stripe",
      "notes": "Test order"
    }'
    ```

    - Use card number: 4242 4242 4242 4242
    - Expected: Payment succeeds, order status updates to "paid"

2. Insufficient Funds:

    - Use card number: 4000 0000 0000 9995
    - Expected: Payment fails with "insufficient_funds" error
    - Verify that order is deleted and stock is restored

3. Cancellation:
    - Start payment process
    - Close payment modal or click cancel
    - Verify that order is deleted and stock is restored

## Webhook Testing

1. Start Stripe webhook listener:

    ```bash
    stripe listen --forward-to localhost:8000/api/webhooks/stripe
    ```

2. Monitor webhook events:
    ```bash
    tail -f storage/logs/laravel.log
    ```
