# Changelog

All notable changes to the PHP packages are documented here. Format: Keep a Changelog. Tags: `<package>@<version>`.

## Unreleased

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
