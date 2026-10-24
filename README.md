# Raslo

**Open-source delivery infrastructure for merchants, drivers, and e-commerce.**

Raslo connects businesses with delivery partners to manage pricing, assignments, pickup, tracking, and proof of delivery. Previously named DeliveryHub, the project is built with Laravel and an API-first architecture, with a companion Expo application for merchants and drivers.

> Active development. Delivery operations, Enterprise catalog and the WooCommerce delivery bridge are implemented as a pilot; production rollout still requires operational acceptance. Test coverage and a working development build do not constitute production certification.

## What exists today

- Merchant and driver registration, authentication, account completion, and administration.
- Delivery creation, pricing and merchant price approval, assignment, driver acceptance, pickup, transit, and completion.
- Driver availability and location updates, tracking, status history, and proof of delivery.
- Merchant addresses, cash-on-delivery collection/receipt, and financial views.
- Subscription billing integration with Stripe.
- Recurring deliveries and automatic dispatch, with optional AI ranking.
- In-app notifications and Expo phone push, including device registration and an outbox.
- Docker development infrastructure, automated tests, and GitHub Actions.

The companion app has been tested on an Android development build with a real push notification. Other workflows and production credentials must be validated in their deployment environment.

## Enterprise catalog

Paid Enterprise merchants can connect stores, synchronize products using a shared Push API or compatible HTTPS feed, and publish approved products to web and mobile discovery. Initial feed import starts on connection creation and repeats every 15 minutes. A WooCommerce Feed connector is included as pilot 0.2.1; Shopify remains planned. [Setup and API contract](docs/enterprise-catalog.md).

## E-commerce delivery integration

Paid Enterprise stores can create prepaid deliveries through `POST /api/integrations/v1/deliveries`, read their status and receive signed status callbacks. The WooCommerce 0.2.1 pilot includes catalog feeds and order forwarding. Orders enter pricing; merchant approval is still required before dispatch. Store checkout payments remain with the store.

Serviceability and guaranteed delivery quotes inside checkout, Shopify adapters, split shipments and automatic cancellation/refunds remain future work. See [the delivery API guide](docs/store-delivery-api.md).

## Roadmap

| Stage | State | Scope |
| --- | --- | --- |
| Foundation | Implemented | Laravel, Docker, authentication, roles, versioned API, CI |
| Delivery operations | Implemented; validation continues | Pricing, assignments, pickup, tracking, delivery proof, COD |
| Merchant and driver app | Development build | Account flows, delivery operations, in-app and phone notifications |
| Automation and subscriptions | Implemented; validation continues | Recurring deliveries, dispatch, Stripe billing |
| Enterprise catalog integration | Implemented | Store keys, paid entitlement, push/feed product sync, run history, publication review, web/mobile discovery |
| Delivery integration API | Pilot implemented | Prepaid creation, idempotency, status, tracking and signed callbacks; checkout quotes remain planned |
| Store connectors | WooCommerce pilot; Shopify planned | Installable WooCommerce catalog and order plugin and shared contract for custom stores |
| Production pilot | Planned | End-to-end merchant trials, failure recovery, monitoring, deployment hardening |

## Stack

Laravel 12, PHP 8.4 development target, Sanctum, Laratrust, Stripe Cashier, Docker Compose, Nginx, Redis, and relational storage. The environment template defaults to SQLite; Compose also includes MySQL. Configure database settings to match the selected deployment.

The [mobile application](https://github.com/AsmarWeb/raslo-mobile) uses Expo SDK 57, React Native, and TypeScript.

## Run locally

Clone the current repository:

```bash
git clone https://github.com/AsmarWeb/raslo.git raslo
cd raslo
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

Check `docker-compose.yml` for the published HTTP port. Configure the database, application URL, and mail settings in your local `.env`. Never commit live credentials.

For an existing installation, follow [the rename guide](docs/raslo-rebrand.md). Existing Docker and database identifiers remain compatible.

## Checks

```bash
docker compose exec app php artisan test
docker compose exec app php artisan schedule:list
```

Inspect GitHub Actions for the current CI result. Earlier local results are not a guarantee that the latest branch passes.

## API and operations

Application endpoints currently use `/api/v1` with Sanctum authentication and resource authorization. These are application APIs; a dedicated catalog integration API is available under `/api/integrations/v1`; prepaid delivery creation and status callbacks are available; guaranteed checkout quotes remain planned.

- [Enterprise catalog integration](docs/enterprise-catalog.md)
- [Catalog OpenAPI](docs/catalog-openapi.json)
- [API notes](docs/api.md)
- [Database notes](docs/database.md)
- [Architecture notes](docs/architecture.md)
- [Phone push](docs/phone-push.md)
- [Automatic dispatch](docs/automatic-dispatch.md)
- [Stripe billing](docs/stripe-billing.md)
- [Automatic webhook recovery](docs/stripe-webhook-recovery.md)
- [WooCommerce connector](integrations/woocommerce/README.md)
- [Raslo rename and compatibility](docs/raslo-rebrand.md)

For push, configure your own Expo/Firebase credentials and use a development build. The scheduler handles `notifications:send-push`; enabling it alone does not verify device delivery.

## Contributing

Open issues and pull requests for fixes, tests, documentation, and integrations. Keep provider-specific store logic outside the core delivery workflow. Include validation evidence and clearly distinguish implemented behavior from proposed APIs.

Please read [the code of conduct](CODE_OF_CONDUCT.md) and [security policy](SECURITY.md). Do not publish secrets or customer data in issues.

## License

[MIT](LICENSE).


## Raslo / رسلو visual identity

The UAE visual refresh uses an emerald, ivory and gold palette, bilingual wordmarks and a consistent route-inspired icon. See [the brand guide](docs/raslo-brand.md) for assets, typography, naming and release checks.


## Store integration dashboard

Merchants can set up WooCommerce, download its plugin and OpenAPI contract, inspect connections, and check a feed from the shared dashboard. Administrators review publication from the sidebar. See [the workspace guide](docs/store-integration-dashboard.md). Native Shopify integration is not included. Optional prepaid WooCommerce delivery forwarding is documented in [the Delivery API guide](docs/store-delivery-api.md).


## Enterprise delivery-order API

The optional WooCommerce 0.2.1 bridge forwards new eligible prepaid UAE orders to the existing Raslo pricing/approval workflow. Dedicated delivery keys, idempotency, signed retryable status callbacks and private customer tracking are documented in [the Delivery API guide](docs/store-delivery-api.md). The pilot requires real WordPress/MySQL checkout acceptance before production.

## Emirate-based automatic pricing

Optional directed UAE emirate rates cover orders without coordinates. Configure your own rates in the admin pricing settings; merchant price approval remains required. See [the pricing guide](docs/emirate-delivery-pricing.md).

## Driver documents

Drivers can upload private identity and vehicle documents, while administrators review versioned files inside each driver profile. The mobile account menus include My documents and Store integrations. See [the document workflow](docs/driver-documents.md).

## Delivery failure recovery

Drivers report pickup/delivery failure, merchants or administrators set a future appointment, and the assigned driver resumes when due. Same-driver recovery preserves parcel custody, pricing and capacity. See [the workflow and acceptance steps](docs/delivery-recovery.md). Returns remain a separate phase.
