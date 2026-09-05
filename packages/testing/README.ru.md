# Набор для тестов IndexNow — `indexnowkit/testing`

Общий набор тестов семейства как `require-dev`-пакет: сценарии соответствия спецификации в виде абстрактных
PHPUnit-кейсов, которые вы наследуете против *своей* проводки (C01–C22 для всего, что говорит с протоколом,
A01–A21 для ORM-адаптера), ассерции HTTP- и командных сценариев (H01–H05), чтобы тест фреймворка разобрал свой
объект ответа и проверил один раз, ассерция для README-раздела, который читают AI-ассистенты, и mock-сервер IndexNow
для сквозных прогонов. Этим тестируются `indexnowkit/doctrine`, `indexnowkit/symfony-bundle`, `indexnowkit/laravel`
и `indexnowkit/yii2`; адаптер для другого фреймворка начинается отсюда.

Четыре тестовых двойника (`FakeTransport`, `ArrayLogger`, `FrozenClock`, `RecordingDispatcher`) остаются в
[`indexnowkit/core`](https://github.com/indexnowkit/php/tree/main/packages/core) под `IndexNowKit\Testing`: они
реализуют интерфейсы ядра и не требуют PHPUnit, так что тесты приложения получают их без этого пакета.

[English version](README.md) · Issues и pull requests: [github.com/indexnowkit/php](https://github.com/indexnowkit/php/issues) (репозитории `php-*` — read-only сплиты)

## Установка

```bash
composer require --dev indexnowkit/testing      # тянет indexnowkit/core; PHPUnit 11 ожидается в вашем require-dev
```

Всё лежит под `IndexNowKit\Testing\Conformance\`.

## Киты соответствия

Два абстрактных тест-кейса превращают [docs/spec/03](https://github.com/indexnowkit/spec/blob/main/03-conformance.md)
в исполняемые сценарии против фасада, который собрал ваш контейнер:

```php
use IndexNowKit\IndexNowKit;
use IndexNowKit\Testing\Conformance\CoreConformanceTestCase;
use IndexNowKit\Testing\FakeTransport;

final class CoreConformanceTest extends CoreConformanceTestCase
{
    protected function kit(): IndexNowKit           { return $this->container()->get(IndexNowKit::class); }
    protected function transport(): FakeTransport   { return $this->container()->get(FakeTransport::class); }
    protected function secondHost(): ?string        { return 'example.de'; }   // вторая запись `hosts`, либо null — C04 пропускается
}
```

- `CoreConformanceTestCase` (C01, C03, C04, C06, C09–C12, C14, C19, C20): верните фасад и `FakeTransport`, к которому
  он подключён; сценарии берут свежие URL, окно дебаунса не мешает.
- `OrmConformanceTestCase` (A01–A21 плюс A05b/A05c): реализуйте драйвер — транзакционные глаголы вашего слоя данных
  (`begin()`, `commit()`, `rollback()`), конец единицы работы (`flush()`, `collectedCount()`) и фикстуры с
  фиксированной формой правил (`createPost()`, `createMultiPost()`, `createCategorizedPost()`, `createTag()`,
  `attachTag()`, `bulkUpdateTitle()`, …). Docblock класса перечисляет правила каждой фикстуры; соглашения об URL
  (`postUrl()`, `ampUrl()`, `categoryUrl()`, `homeUrl()`) переопределяемы.

`indexnowkit/doctrine` (`tests/OrmConformanceTest.php`) и `indexnowkit/laravel` (`tests/Conformance/`) — эталонные
драйверы. Сценарий, который не применим к вашему фреймворку, описывается в README, а не пропускается молча.
Идентификаторы сценариев — кросс-языковой контракт: сценарий добавляется, никогда не перенумеровывается.

## Ассерции для HTTP- и командных тестов

Сценарии H01–H05 одинаковы во всех фреймворках, различается только способ получить ответ или вывод команды.
Разберите объекты своего фреймворка, проверьте здесь:

```php
use IndexNowKit\Testing\Conformance\CheckOutputAssertions;
use IndexNowKit\Testing\Conformance\KeyFileAssertions;

// H01: 200, text/plain, ключ как тело, Cache-Control с public и max-age, Vary: Host только при карте hosts
KeyFileAssertions::assertKeyFileResponse($response->getStatusCode(), $response->headers->all(), $response->getContent(), $key, maxAge: 300, expectVaryHost: true);
// H02/H03: неизвестный ключ, ключ другого хоста, key_file.enabled: false
KeyFileAssertions::assertNotServed($response->getStatusCode());

// H04/H05: команда check
CheckOutputAssertions::assertExitCode(0, $exitCode, $output);        // вывод — сообщение об ошибке
CheckOutputAssertions::assertReady($output, 'www.example.com');       // "<host>: key file OK" и финальная строка
CheckOutputAssertions::assertKeyFileHint($output, 403);              // статус и подсказка, что сделают поисковики
```

`Cache-Control` сравнивается по директивам (фреймворки упорядочивают их по-разному), имена заголовков в любом
регистре, значения строкой или списком. Фразы — те, что печатают `Checker` ядра и команда `check`, так что тест не
несёт их копию.

`ReadmeAssertions::assertAiNotes($packageDir, $commands, $optionKeys)` проверяет раздел «Заметки для AI-ассистентов»
README пакета (EN и RU): раздел есть, в нём PHP-сниппет со строками `use`, упомянуты только команды семейства и
ключи конфигурации, которые пакет принимает. Его запускает каждый пакет семейства; ваш адаптер тоже может.

## Mock-сервер IndexNow

Для сквозных прогонов через настоящий PSR-18 клиент, не трогая поисковики:

```bash
php -S 127.0.0.1:8089 vendor/indexnowkit/testing/resources/mock-server/router.php
```

Направьте `engines` на `http://127.0.0.1:8089/indexnow` (чистый HTTP принимается только на loopback-хостах) и
выберите поведение заголовком `X-Mock-Scenario` или `?scenario=`: `ok200` (по умолчанию), `pending202`, `bad400`,
`forbidden403`, `unprocessable422`, `ratelimit429` (`Retry-After: 2`), `ratelimit429-then-ok` и `flaky500-then-ok`
(сначала `?n=` отказов), `timeout`. Сервер проверяет тело как настоящий endpoint (host, key, `urlList`, не больше
10 000 URL, каждый URL на объявленном хосте — иначе 422), отдаёт `GET /{key}.txt` для ключей из переменной окружения
`MOCK_KEYS` (через запятую) и логирует каждый запрос: `GET /_mock/requests` возвращает лог в JSON,
`DELETE /_mock/requests` очищает. Из теста запускайте через `proc_open` на свободном порту, как `Psr18TransportTest` ядра.

## Требования

PHP 8.2+, `indexnowkit/core ^0.7`, PHPUnit 11 в вашем `require-dev` (тест-кейсы наследуют `PHPUnit\Framework\TestCase`).

## Заметки для AI-ассистентов

- Composer-пакет `indexnowkit/testing`, только `require-dev`: тест-кейсы соответствия и ассерции для набора тестов, использующего `indexnowkit/core` или один из адаптеров; в приложении ничего отсюда не выполняется.
- Минимальный полный сниппет (все `use` на месте) — тест соответствия адаптера:

```php
use IndexNowKit\IndexNowKit;
use IndexNowKit\Testing\Conformance\CoreConformanceTestCase;
use IndexNowKit\Testing\FakeTransport;

final class CoreConformanceTest extends CoreConformanceTestCase
{
    protected function kit(): IndexNowKit { return $this->app->get(IndexNowKit::class); }              // фасад, собранный контейнером
    protected function transport(): FakeTransport { return $this->app->get(FakeTransport::class); }    // транспорт, к которому он подключён
}
```

- Проверка: `vendor/bin/phpunit` прогоняет сценарии; красный C-сценарий — проблема проводки адаптера, а не кита.
- Ловушки:
  - Тестовые двойники (`FakeTransport`, `ArrayLogger`, `FrozenClock`, `RecordingDispatcher`) — `IndexNowKit\Testing\*` в ядре; киты и ассерции — `IndexNowKit\Testing\Conformance\*` здесь. До core 0.7 ассерции жили в ядре под `IndexNowKit\Testing\*`.
  - `assertKeyFileResponse()` ожидает `Vary: Host` только когда приложение обслуживает несколько хостов (карта `hosts`) и отвергает его иначе.
  - `CheckOutputAssertions::assertExitCode()` принимает весь вывод третьим аргументом, чтобы упавший тест показал, что напечатала команда.
  - Mock-сервер принимает чистый HTTP только на loopback-хостах; `engines` должен называть полный endpoint (`http://127.0.0.1:8089/indexnow`).
  - `dispatch: auto` есть в Symfony (`auto` | `messenger` | `sync` | `none`) и Yii2 (`auto` | `queue` | `sync` | `none`), в Laravel **нет** (`queue` | `sync` | `none`): тест соответствия адаптера идёт с `dispatch: sync`.

## Версионирование

SemVer; до 1.0 минорные версии могут ломать совместимость, изменения перечислены в [CHANGELOG.md](CHANGELOG.md).
Что покрывает обещание совместимости: [docs/bc.md](docs/bc.md).

MIT. IndexNow — товарный знак его владельца; проект независимый и не связан с Microsoft, Яндексом или indexnow.org.
