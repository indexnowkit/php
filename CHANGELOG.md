# Changelog

All notable changes to the PHP packages are documented here. Format: Keep a Changelog. Tags: `<package>@<version>`.

## Unreleased

### core@0.2.0
See [packages/core/CHANGELOG.md](packages/core/CHANGELOG.md). Highlights: full GET bodies in `Psr18Transport` (sitemaps
over 2 KiB work), `http.timeout` applied, throttle per HTTP request, `SitemapReader` on XMLReader with same-host and size
caps, exceptions from listeners/debounce/attributes never escape, `RetryPolicy`/`RetryingSubmitter` (C13),
`GuardedUrlResolver`, interfaces for every swappable piece, `Config::with()`, per-host `key_location`, non-production
dry-run safety net. Breaking: `Url\Event` → `IndexNowKit\Event`, `ParamExtractor`/`PublishGuard` → `Attribute\`.

### doctrine@0.2.0
- Uses `GuardedUrlResolver`: a typo in `#[IndexNow(when: ...)]` is logged instead of breaking the flush.
- Requires `indexnowkit/core ^0.2` (`IndexNowKit\Event`).

### symfony-bundle@0.2.0
- `http.timeout` reaches the discovered HTTP client; throttle counts HTTP requests, not batches.
- `hosts` accepts `host: {key, key_location}`; `kernel.environment` enables the dry-run safety net outside `prod`.
- `SubmitterInterface`, `UrlNormalizerInterface`, `ThrottleInterface` and `AttributeReaderInterface` are container aliases.
- Requires `indexnowkit/core ^0.2`, `indexnowkit/doctrine ^0.2`.

## 0.1.0 — 2026-09-03

### core@0.1.0
- Protocol client for the shared endpoint and per-engine endpoints (Yandex, Bing, Naver, Seznam, Yep).
- Batching (10 000 URLs, grouped by host), per-URL debounce, token-bucket throttle, typed handling of 200/202/400/403/422/429/5xx.
- `#[IndexNow]` attribute, `Config::fromArray/fromEnv`, sitemap reader, `Checker`, pure-PHP punycode.

### doctrine@0.1.0
- `onFlush`/`postFlush` listener resolving URLs (deletions before removal, publish/unpublish transitions).
- DBAL driver middleware (DBAL 3 and 4) delivering URLs only after the outermost COMMIT.

### symfony-bundle@0.1.0
- Bundle config, Messenger dispatch with retry-after, `kernel.terminate` batching, key file route.
- Commands `indexnow:key:generate`, `indexnow:check`, `indexnow:submit`, `indexnow:submit-entity`, `indexnow:sitemap`.
- Web Profiler panel. Flex recipe in `packages/symfony-bundle/recipe`.
