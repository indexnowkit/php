# История отправок IndexNow — `indexnowkit/history`

Что было объявлено Яндексу, Bing и остальным поисковикам с поддержкой [IndexNow](https://yandex.ru/support/webmaster/ru/indexing-options/index-now),
когда и с каким ответом: две поставляемые реализации `Submission\SubmissionStoreInterface` ядра — кольцевой буфер в
PSR-16-кэше для разработки и небольших сайтов и таблица в базе (PDO) для production — плюс команды `history` и
`status` каждого адаптера и список «последние отправки» на панели профилера Symfony. IndexNow — уведомление, не
индексация: история говорит, что сделала ваша сторона; обошёл ли поисковик страницу — в Bing Webmaster Tools и
Яндекс.Вебмастере.

**Ничего не записывается, пока не назван стор** (`history.store: null` по умолчанию).

[![Packagist](https://img.shields.io/packagist/v/indexnowkit/history)](https://packagist.org/packages/indexnowkit/history)
[![Downloads](https://img.shields.io/packagist/dt/indexnowkit/history)](https://packagist.org/packages/indexnowkit/history)
[![CI](https://github.com/indexnowkit/php/actions/workflows/ci.yml/badge.svg)](https://github.com/indexnowkit/php/actions)
![Coverage](https://img.shields.io/badge/coverage-%E2%89%A5%2090%25%20enforced-brightgreen)
![PHPStan](https://img.shields.io/badge/phpstan-level%209-4c1)
![PHP](https://img.shields.io/badge/php-%5E8.2-777bb4)
[![License](https://img.shields.io/packagist/l/indexnowkit/history)](LICENSE)

[English version](README.md) · Issues и pull requests: [github.com/indexnowkit/php](https://github.com/indexnowkit/php/issues) (репозитории `php-*` — read-only сплиты)

## Установка

```bash
composer require indexnowkit/history        # тянет indexnowkit/core
```

```yaml
# Symfony: config/packages/indexnowkit.yaml       # Laravel: config/indexnow.php 'history' => [...]
indexnowkit:                                      # Yii2: 'history' => [...] компонента
    history:
        store: pdo               # null | psr16 | pdo
        pdo:
            service: ~           # соединение адаптера (по умолчанию — дефолтное) или dsn: 'mysql:…'
            table: indexnow_submissions
        retention_days: 90
```

При `store: pdo` сначала создайте таблицу: в [docs/migrations.md](docs/migrations.md) сниппеты для Doctrine
Migrations, Laravel и Yii2, каждый — строка поверх `History\Pdo\Schema::sql($driver)`. `check` печатает `history: 1 240
records, last 3 min ago` (или исключение с подсказкой про миграцию, если таблицы нет).

## Что записывается

Одна запись на `Result` — одна пачка URL у одного движка — с URL **после нормализации ядра** (трекинг-параметры уже
срезаны), движком, хостом, статусом (`ok`, `pending`, `failed`, `skipped`), причиной `Reason`, HTTP-кодом и
`Result::$error`, обрезанным до 1000 символов. Пропущенные результаты (дебаунс, dry-run, предпроверка
`indexnowkit/verify`) — тоже записи. **Никогда** — тело ответа, заголовки или ключ. Персональные данные попадают в
историю только если их несёт сам URL.

| Стор | Где | Для чего |
|---|---|---|
| `psr16` | кэш адаптера за `debounce.store`, кольцевой буфер из `history.limit` (500) записей под `<debounce.key_prefix>history.<n>`, слот берётся атомарным `increment()`, если он есть у кэша | один процесс, разработка, небольшие сайты: два воркера, пишущие одновременно, могут потерять запись, вытеснение кэша обнуляет историю |
| `pdo` | таблица `history.pdo.table` (строка на URL, строки одного Result делят `batch`), индексы по `url`, `at`, `(host, at)` | production; `history --purge` удаляет старше `history.retention_days`. При `dispatch: sync` внутри транзакции приложения запись живёт в той же транзакции (откат стирает запись об уже ушедшем запросе): при необходимости дайте истории своё соединение через `history.pdo.dsn` |

Любой другой `SubmissionStoreInterface` тоже работает с командами (достаточно `recent()`); счётчики, «последняя
отправка» и `--purge` нужны `History\HistoryStoreInterface` (`count()`, `last()`, `purge()`) — оба поставляемых стора
его реализуют.

## Команды

```bash
bin/console indexnow:history --host=www.example.com --status=failed --since=2h --limit=20
php artisan indexnow:history --url=https://www.example.com/post-1        # точный URL после нормализации
php yii indexnow/history --json                                           # {"records":[{at,status,reason,engine,http_status,error,urls[]}]}
bin/console indexnow:history --purge                                       # старше retention_days; --purge=30 — 30 дней; одна строка для cron
bin/console indexnow:status --json                                         # enabled, dry_run, окружение, dispatch, debounce store, счётчики 403 по хостам, последняя успешная отправка, размер истории, версия core
```

`indexnow:status` только читает: ничего не запрашивается и не отправляется. JSON — по
[docs/status.schema.json](https://github.com/indexnowkit/php/blob/main/packages/history/docs/status.schema.json).

## Чистый PHP

```php
use IndexNowKit\Config;
use IndexNowKit\History\Pdo\PdoSubmissionStore;
use IndexNowKit\IndexNowKit;

$store = new PdoSubmissionStore(new PDO('sqlite:var/indexnow.sqlite'));
$store->createTable();                                            // или миграция из docs/migrations.md
$indexNow = IndexNowKit::create(Config::fromEnv(), submissionStore: $store);
$indexNow->submit(['https://www.example.com/post-1']);
foreach ($store->recent(10) as $record) { echo $record->at->format(DATE_ATOM), ' ', $record->result->status->value, ' ', implode(' ', $record->urls), PHP_EOL; }
```

## Требования

PHP 8.2+, `indexnowkit/core ^0.11`; `ext-pdo` с драйвером вашей базы для `store: pdo` (схемы для `sqlite`, `mysql`,
`pgsql`); `indexnowkit/console ^0.4` для команд (есть у каждого адаптера).

## Заметки для AI-ассистентов

- Composer-пакет `indexnowkit/history` (дополнение к `indexnowkit/core`). С адаптером фреймворка ничего не проводится вручную: `history.store: pdo` (или `psr16`) в конфигурации адаптера подключает стор; для `history.pdo.table` сначала нужна миграция из `docs/migrations.md`.
- Минимальный полный сниппет (все `use` включены):

```php
use IndexNowKit\Config;
use IndexNowKit\History\Pdo\PdoSubmissionStore;
use IndexNowKit\IndexNowKit;

$store = new PdoSubmissionStore(new PDO('sqlite:var/indexnow.sqlite'));
$store->createTable();
$indexNow = IndexNowKit::create(Config::fromEnv(), submissionStore: $store);
$indexNow->submit(['https://www.example.com/post-1']);
$last = $store->lastFor('https://www.example.com/post-1');   // ?SubmissionRecord: urls, result (status, reason, httpCode, error), at
```

- Проверка: `bin/console indexnow:history`, `php artisan indexnow:history`, `php yii indexnow/history` показывают записи; `indexnow:status` / `indexnow/status` — проводку и последнюю успешную отправку; `indexnow:check` / `indexnow/check` печатают строку `history:`.
- Ловушки:
  - `history.store` по умолчанию `null`: установка ничего не записывает. `psr16` теряет записи при вытеснении кэша и одновременной записи двух воркеров; production — `pdo`.
  - `--purge` и счётчики нужны поставляемым сторам (`History\HistoryStoreInterface`); свой `SubmissionStoreInterface` по-прежнему показывается `indexnow:history`.
  - `--url` сравнивает URL после нормализации (`normalizer.strip_tracking_params` включён по умолчанию): передавайте URL без `utm_*`.
  - `history.key_prefix` и имя PDO-таблицы не должны содержать `{}()/\@:` (PSR-6) и ничего кроме `[A-Za-z0-9_]` (SQL-идентификатор).
  - `dispatch: auto` есть в Symfony и Yii2, **нет** в Laravel; локали — `router.locales` (Laravel), `router.languages` (Yii2), `framework.enabled_locales` (Symfony).

## Версионирование

SemVer; до 1.0 минорные версии могут содержать ломающие изменения, они перечислены в [CHANGELOG.md](CHANGELOG.md).
Что покрывает обещание совместимости: [docs/bc.md](docs/bc.md).

MIT. IndexNow — торговая марка её владельца; проект независим и не связан с Microsoft, Яндексом или indexnow.org.
