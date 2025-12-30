# Limit-Order Exchange Mini Engine

A full-stack limit order exchange system built with Laravel API and Vue.js frontend. This mini trading engine allows users to place buy/sell orders for cryptocurrencies (BTC/ETH) with automatic order matching, real-time updates via Pusher, and commission handling.

## Features

- ✅ Limit order placement and cancellation
- ✅ Automatic order matching engine
- ✅ Real-time order updates via Pusher broadcasting
- ✅ Balance and asset management with race condition safety
- ✅ 1.5% commission on matched trades
- ✅ Orderbook visualization
- ✅ Wallet overview with USD and crypto balances
- ✅ Full test coverage with TDD approach

## Tech Stack

- **Backend**: Laravel 12 (latest stable)
- **Frontend**: Vue.js 3 with Composition API + Tailwind CSS
- **Database**: MySQL
- **Real-time**: Pusher via Laravel Broadcasting
- **Authentication**: Laravel Sanctum

## Prerequisites

- PHP >= 8.2
- Composer
- Node.js >= 18
- Docker Desktop (for Sail)
- MySQL (or use Docker)

## Installation

### 1. Clone the repository

```bash
git clone <your-repo-url>
cd limitOrderExchangeMiniEngine
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install Node dependencies

```bash
npm install
```

### 4. Configure environment

Copy the `.env.example` file to `.env`:

```bash
cp .env.example .env
```

Generate application key:

```bash
php artisan key:generate
```

### 5. Configure Database

Update your `.env` file with database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=limit_order_exchange
DB_USERNAME=root
DB_PASSWORD=your_password
```

### 6. Configure Pusher (Optional for development)

For real-time features, configure Pusher in `.env`:

```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1
```

**Note**: For development/testing without Pusher, you can use:
```env
BROADCAST_DRIVER=log
```

### 7. Run migrations

```bash
php artisan migrate
```

### 8. Build frontend assets

```bash
npm run build
```

Or for development with hot reload:

```bash
npm run dev
```

## Running with Docker (Laravel Sail)

### 1. Start Docker containers

```bash
./vendor/bin/sail up -d
```

### 2. Run migrations

```bash
./vendor/bin/sail artisan migrate
```

### 3. Access phpMyAdmin

- URL: http://localhost:8080
- Username: `sail` (or `root`)
- Password: `password`
- Server: `mysql`

### 4. Access the application

- Frontend: http://localhost
- API: http://localhost/api

## Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter OrderMatchingTest
php artisan test --filter OrderCreationTest
php artisan test --filter ProfileTest
```

## API Endpoints

All endpoints require authentication via Bearer token (Laravel Sanctum).

### Authentication

- `POST /api/login` - Login and get API token
  ```json
  {
    "email": "user@example.com",
    "password": "password"
  }
  ```

### Profile

- `GET /api/profile` - Get user balance and assets

### Orders

- `GET /api/orders?symbol=BTC` - Get all open orders (orderbook)
- `POST /api/orders` - Create a new limit order
  ```json
  {
    "symbol": "BTC",
    "side": "buy",
    "price": 100000.00,
    "amount": 0.01
  }
  ```
- `POST /api/orders/{id}/cancel` - Cancel an open order

## Project Structure

```
├── app/
│   ├── Events/
│   │   └── OrderMatched.php          # Broadcasting event
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/                   # API controllers
│   ├── Models/                        # Eloquent models
│   └── Services/
│       └── OrderService.php          # Order matching logic
├── database/
│   └── migrations/                   # Database migrations
├── resources/
│   ├── js/
│   │   ├── components/                # Vue components
│   │   ├── utils/                    # Utilities (eventBus)
│   │   ├── App.vue                   # Main Vue app
│   │   └── app.js                    # Entry point
│   └── views/
│       └── app.blade.php             # Main Blade template
├── routes/
│   ├── api.php                       # API routes
│   ├── channels.php                  # Broadcasting channels
│   └── web.php                       # Web routes
└── tests/
    └── Feature/                      # Feature tests
```

## Key Features Explained

### Order Matching

Orders are matched immediately when created:
- **BUY orders** match with first **SELL** order where `sell.price <= buy.price`
- **SELL orders** match with first **BUY** order where `buy.price >= sell.price`
- Only full matches (same amount) are executed
- Matching happens atomically within database transactions

### Real-time Updates

When an order is matched:
1. `OrderMatched` event is broadcast via Pusher
2. Both users (buyer and seller) receive the event on their private channels
3. Frontend automatically updates balances, assets, and order lists

### Security Features

- **Race condition safety**: Uses `lockForUpdate()` for pessimistic locking
- **Atomic transactions**: All order operations are wrapped in DB transactions
- **Balance validation**: Prevents negative balances
- **Asset validation**: Prevents selling more than available

## Testing

The project includes comprehensive tests:

- **Model tests**: User relationships and validations
- **Order creation tests**: Validation and fund locking
- **Matching tests**: Order matching logic and balance updates
- **API tests**: Endpoint functionality and authentication

Run tests with:

```bash
php artisan test
```

## Development Notes

- The matching engine executes immediately when orders are created (no background jobs)
- Commission (1.5%) is deducted from the buyer
- Orders can only be cancelled if they're still open
- All prices and amounts use decimal precision (20,8 for amounts, 20,2 for prices)

## License

This project is part of a technical assessment.
