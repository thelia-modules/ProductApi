# Product API

An API to get a product from a Thelia based website with only the product's reference or id.

## Installation

Requires Thelia 3.2.0 or later.

```
composer require thelia/product-api-module
```

Then activate the module in the back-office (or `php Thelia module:refresh` and `php Thelia module:activate ProductAPI`).

**The API key has no default value.** Until a key is saved (Modules, Product API, "API Key"), `/api/product` answers `503`.
Save the key before activating the module on a site whose callers are already signing their requests.

## Usage

`GET /api/product` returns the product as JSON.

The request must be signed: add the `hash` of the values of the parameters (in the order they are sent, `hash` excluded)
followed by the API key, with `sha1`.

### Example

To get the product whose reference is `130010` with the API key `my-secret-key`:

- `sha1('130010' . 'my-secret-key')` => the value of `hash`
- `/api/product?ref=130010&hash=<hash>`

With more parameters, they all take part in the signature, in the order of the query string:
`/api/product?ref=130010&lang=fr_FR&country=FRA&hash=sha1('130010' . 'fr_FR' . 'FRA' . 'my-secret-key')`.

### Input arguments

| Argument  | Description                                                              |
|-----------|--------------------------------------------------------------------------|
| **hash**  | `sha1` of the parameter values followed by the API key                   |
| *ref*     | The reference of the product                                             |
| *id*      | The id of the product (`ref` or `id` is required; both narrow the search) |
| *country* | The country for the taxes, ISO 3166-1 alpha-3, `FRA` by default          |
| *lang*    | The locale of the images and of the product URL, `fr_FR` by default      |

Any other parameter is ignored. Without any parameter the API answers `{"message": "Thelia Product API is working !"}`.

### Response

`200` with `{"Product": {...}}`: the product columns (`Id`, `Ref`, `Visible`, ...), `Images`, `URL`, `ProductSaleElements`
(by id, each with its columns, `Prices` and the attribute titles under `i18ns`) and `ProductI18ns` (by locale).
Offline products are served too: check `Visible`.

Errors are a JSON string with a fixed message: `403` invalid or missing signature, `400` unknown product, country or
language, `429` too many requests (`Retry-After` header), `503` no API key configured, `500` unexpected error (the
detail is in the log, never in the response).

A successful response can be cached for 60 seconds (`Cache-Control: public, max-age=60`). A client address that fails
the signature more than 30 times a minute is answered `429` until the window ends; correctly signed requests are never
counted. Change the limit with `failed_signatures_per_minute` in the module configuration table. The address is the one
Symfony resolves (`Request::getClientIp()`): behind a reverse proxy configure `trusted_proxies`, otherwise every client
shares the address of the proxy, and a forged `X-Forwarded-For` header can dodge or frame a client.

## Changes in 3.0.0

Thelia 3 only (Symfony 7.4, PHP 8.3). Route, parameters and response format are those of 2.0.3.

- Requests must be signed, and the module answers `503` until an API key is saved. 2.x accepted a request without `hash`,
  and shipped a default key in its code, which is published in the repository: do not reuse it, generate a new key and
  give it to the callers.
- The signature is compared in constant time.
- Only `ref` and `id` select a product. In 2.x every other parameter became a filter on a product column (`visible`, `position`,
  `tax_rule_id`, ...); they are now ignored.
- Errors no longer return the text of the exception (no more `PROPEL ERROR : ` or `UNKNOW ERROR : ` prefix): fixed
  messages, details in the log. Unexpected errors answer `500` instead of `400`. An unknown `lang` answers `400`
  (`Language code not found.`), and a request with neither `ref` nor `id` answers `400` (2.x returned the first product).
- The `image_path` key (file path on the server) is removed from `Images`. Image URLs are absolute and, when
  `one_domain_foreach_lang` is enabled, on the domain of the requested `lang`, like `URL`.
- Prices are taxed with the requested country (2.x always computed the taxes of the default country).
- Fixed number of queries whatever the number of sale elements, attributes and images.
- Signature failures are limited per client address, and successful responses can be cached for a minute.
- Back-office configuration page in Twig (`default-twig`), saved through a CSRF-protected form; the key must be at least
  16 characters.
- Removed: Smarty template, `routing.xml`, empty `schema.xml`, the `server_host` setting, `ApiService`.

Kept as they were in 2.x, because callers are written against them: the `i18ns.i18ns.<locale>` nesting of image texts,
the position of attribute titles in `Attributes` (the position of the translation, not of the attribute), a hole in
`Images` for an image that cannot be processed, and the last price row when a sale element has several currencies.

## Tests

The integration tests need a Thelia 3 project with this module installed, and a disposable database whose name
ends with `_test` (they refuse to run on any other). From the project root, with the `DATABASE_*` variables (host,
port, name, user, password), `APP_ENV=test` and `KERNEL_CLASS` set for that database:

```
php bin/test-prepare
vendor/bin/phpunit --bootstrap vendor/thelia/modules/ProductAPI/Tests/bootstrap.php vendor/thelia/modules/ProductAPI/Tests
```

Set `THELIA_TEST_CACHE_DIR` (a path relative to the project root) to give these tests their own compiled container
when the project runs other test suites on other databases.
