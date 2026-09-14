# Cache invalidation API

`MintCart\Core\Cache\CacheInvalidator` (`upload/system/library/mintcart/core/cache/cacheinvalidator.php`)
— `MINTCART_FUNCTIONAL_SCOPE.md` §19 ("cache invalidation API", P1/P2) /
`MINTCART_TECHNICAL_PLAN.md` §36 (Performance: "cache invalidation API",
listed before any page/block caching, which is explicitly deferred "only
after measurement").

## What it is

A small domain → cache-key-prefix registry plus four operations
(`invalidate`, `invalidateMany`, `invalidateKeys`, `invalidateAll`) on top
of OpenCart's stock `\Cache` class (`upload/system/library/cache.php`).
Same "build the contract first" shape as `MintCart\Core\Migration\MigrationManager`
(Phase 1), `MintCart\Update\PackageManifest` (Phase 11) and the
`MintCart\Seo\*Builder` classes (Phase 18): a pure, injectable-callback
primitive, no live caller yet (`CLAUDE.md` §85). The natural first
consumer is a future write path that changes cached data outside its
normal TTL window — a bulk product/category edit (Phase 16 Bulk Editor,
category move) invalidating the `catalog` domain, or a Business Settings
save invalidating `localization`/`checkout`. None of those write paths
exist on `master` yet (as of this writing they're still open, unmerged
PRs — see `docs/IMPLEMENTATION_STATUS.md`), so wiring a real call site is
left for whichever of those lands first.

## Why an injected callable, not `\Cache`/`\Registry` directly

Every other pure MintCart service in this repo (`Logger`, `CartService`,
`SearchService`, `StoreReadiness`, ...) either has zero OpenCart
dependency or takes its OpenCart collaborators as constructor arguments,
never reaches into a global `\Registry`. `CacheInvalidator` follows the
same rule: its constructor takes one `callable(string): void` — in real
use, `[$this->cache, 'delete']` from any controller/model context that
already has `$this->cache` — so the whole class is unit-testable with a
plain recording closure and no `DIR_CACHE`/adapter bootstrap
(`tests/mintcart/core/cache-invalidator-test.php`).

## The registry

Five domains, each a list of literal OpenCart cache key prefixes. Every
prefix was found by grepping actual `$this->cache->get(...)`/`->set(...)`
call sites across `upload/catalog/` and `upload/admin/` — not assumed
from memory (`CLAUDE.md` §84):

| Domain | Prefixes |
| --- | --- |
| `catalog` | `product`, `product.latest.`, `product.popular.`, `product.bestseller.`, `category`, `manufacturer`, `manufacturer.`, `attribute` |
| `localization` | `country`, `country.admin`, `country.catalog`, `currency`, `zone`, `zone.`, `geo_zone`, `length_class`, `length_class.`, `weight_class`, `weight_class.` |
| `checkout` | `order_status`, `order_status.`, `return_action`, `return_action.`, `return_reason`, `return_reason.`, `return_status`, `return_status.`, `stock_status`, `tax_class`, `voucher_theme`, `voucher_theme.` |
| `content` | `information`, `information.`, `banner` |
| `store` | `store` |

`CacheInvalidator::knownDomains()` / `::prefixesFor($domain)` are static
so a caller (or a future admin "Clear cache" screen) can introspect the
registry without instantiating anything.

Not included: some cache keys observed in the same grep (`admin.language`,
`catalog.language`, `paypal`, `squareup`) are either framework-internal
(language pack cache, unrelated to any MintCart write path) or a specific
payment extension's own private cache key — grouping them under a
MintCart-owned domain name would invent a public contract with no real
caller (`CLAUDE.md` §85/§86), so they're left out of the registry rather
than guessed into a category. Extend `DOMAINS` when a real MintCart write
path needs one of them invalidated.

## IMPORTANT — verified backend caveat, not a MintCart bug

`\Cache::delete($key)` behaves differently depending on the configured
`cache_engine` (`upload/system/config/default.php` line 39, default
`file`) — read from each adapter's actual `delete()` method, not assumed:

- **`file`** (`upload/system/library/cache/file.php`) — `glob()`-matches
  `cache.<key>.*`, so `$key` really does work as a PREFIX (e.g.
  `delete('product.latest.')` clears every
  `product.latest.<lang>.<store>.<group>.<limit>` variant), and the
  special case `delete('*')` globs and deletes every cache file.
- **`apc`**/**`apcu`** (`upload/system/library/cache/apc.php`,
  `.../apcu.php`) — scan the adapter's key list and delete every entry
  whose key starts with `$key`, i.e. also genuinely prefix-based.
- **`mem`**/**`memcached`**/**`redis`** (`.../cache/mem.php`,
  `.../cache/memcached.php`, `.../cache/redis.php`) — call the backend's
  single-key delete on the LITERAL `$key`. There is no prefix or wildcard
  support in any of the three. `delete('product.latest.')` deletes
  nothing (no key is ever named exactly that), and **`delete('*')` is
  also a no-op** — it tries to delete a key literally named `*`, which
  never exists.

The `delete('*')` gap on `mem`/`memcached`/`redis` is not something
MintCart introduced: OpenCart's own admin "clear cache" step —
`admin/controller/tool/backup.php` line 132, `$this->cache->delete('*')`
— relies on exactly the same call and inherits the exact same silent
no-op on those three adapters. This is a pre-existing stock OpenCart
limitation. It is **not** fixed as part of this increment:

- Fixing it means changing `tool/backup.php`, which an in-flight PR
  (fixing an unrelated byte-length bug in the same file's restore-safety
  check) already touches — landing a second concurrent edit there risks
  a collision this routine's own hot-file rule exists to avoid.
- A real fix (calling each adapter's actual flush-all primitive —
  `Memcache(d)::flush()`/`Redis::flushDB()` — instead of a single-key
  `delete()`) needs a live memcached/redis server to verify safely, which
  this environment doesn't have.

It's documented here, and as a caveat directly in `CacheInvalidator`'s own
class docblock, rather than silently ignored, per the Fixes Policy
(`CLAUDE.md` §13) — a candidate `docs/fixes/registry.md` entry for a
future run once it can be verified live, deliberately not added this run
to avoid contending `registry.md`'s own shared trailer with the
in-flight `tool/backup.php` PR.

### What this means for callers today

- `invalidate($domain)` / `invalidateMany($domains)` are fully correct on
  the default `file` adapter (and on `apc`/`apcu`), which is what a fresh
  MintCart install actually uses (`cache_engine` defaults to `file`) —
  the common case works.
- `invalidateKeys($exactKeys)` deletes each key literally and is the one
  method guaranteed to behave identically on **all five** adapters —
  prefer it when the caller already knows the exact key it just wrote
  (e.g. `product.latest.1.1.1.10`), regardless of which cache engine the
  store is configured with.
- `invalidateAll()` is a best-effort `delete('*')` — reliable on
  `file`/`apc`/`apcu` only, per the caveat above.

## Not done yet

- No real write-path caller (see "What it is" above) — this is a
  primitive shipped ahead of its first consumer, same as several other
  Phase 1/11/18 classes in this repo.
- No admin "Clear cache" UI surfacing `knownDomains()` — a plausible
  future consumer once one exists, not attempted here.
- The `mem`/`memcached`/`redis` `delete('*')` limitation is documented,
  not fixed (see above).
