# LocalTech — Smart Local Inventory Discovery & Reservation System
PHP + MongoDB + HTML/CSS/JS. Customers search nearby shops, compare prices, and reserve for pickup; shop owners manage inventory; admins verify and monitor.

## Requirements
PHP 8.1+, the `mongodb` PHP extension (`pecl install mongodb`), Composer, MongoDB 5+ running locally.

## Run
```bash
composer install            # installs mongodb/mongodb library
php seed.php                # creates indexes + demo data
php -S localhost:8000       # open http://localhost:8000
```
Env overrides: `MONGO_URI`, `MONGO_DB`.

## Demo logins
| Role | Email | Password |
|---|---|---|
| Admin | admin@localtech.com | admin123 |
| Shop owner | shop1@localtech.com (shop2, shop3 verified; shop4 pending) | pass123 |
| Customer | customer@localtech.com | pass123 |

## Modules
- **Customer**: keyword search, filters (category, city, max price, in-stock), sort, "Best price" badge across shops, nearest-shop sorting via browser location, atomic reservations, cancel.
- **Shop owner**: overview + low-stock alerts, add/edit/delete products, inline price/stock update, confirm/hand over/cancel reservations.
- **Admin**: dashboard with MongoDB aggregation stats, verify shops, block/delete users, remove listings, monitor reservations, activity log.

## MongoDB collections
`users`, `shops`, `products`, `reservations` (embeds product/shop snapshot), `logs`. Indexes: unique email, text index on products, compound `shop_id+category`, `price`, `stock`, reservation lookups. Overselling is prevented with `updateOne({stock:{$gte:qty}}, {$inc:{stock:-qty}})`.

## Security
Password hashing, CSRF tokens, role guards, output escaping, session regeneration on login.
