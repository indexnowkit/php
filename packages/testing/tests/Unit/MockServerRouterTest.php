<?php

declare(strict_types=1);

namespace IndexNowKit\Testing\Tests\Unit;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

/**
 * The shipped mock server: the router runs under the built-in server, answers the documented scenarios, and the
 * core's private copy (its tests cannot depend on this package) is byte-identical to the shipped one.
 */
final class MockServerRouterTest extends TestCase
{
    private const ROUTER = __DIR__ . '/../../resources/mock-server/router.php';

    #[TestDox('the core keeps a byte-identical copy of the router (monorepo only)')]
    public function testTheCoreCopyIsIdentical(): void
    {
        $core = \dirname(__DIR__, 3) . '/core/tests/Support/mock-server/router.php';
        if (!is_file($core)) {
            self::markTestSkipped('the core is not next to this package (split repository): the monorepo runs this test');
        }

        self::assertFileEquals(self::ROUTER, $core, 'packages/core/tests/Support/mock-server/router.php must be a copy of resources/mock-server/router.php');
    }

    #[TestDox('the router answers the documented scenarios, serves MOCK_KEYS and logs the requests')]
    public function testTheRouterAnswersTheScenarios(): void
    {
        $key = 'abcdef1234567890abcdef1234567890';
        $port = self::freePort();
        $cmd = \sprintf('MOCK_KEYS=%s exec php -S 127.0.0.1:%d %s', escapeshellarg($key), $port, escapeshellarg(self::ROUTER));
        $process = proc_open($cmd, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        self::assertIsResource($process);
        try {
            $base = 'http://127.0.0.1:' . $port;
            self::waitFor($port);
            self::request($base . '/_mock/requests', 'DELETE');

            [$status, $body] = self::request($base . '/' . $key . '.txt');
            self::assertSame(200, $status);
            self::assertSame($key, $body);
            [$status] = self::request($base . '/nope-nope-nope.txt');
            self::assertSame(404, $status);

            $payload = json_encode(['host' => 'www.example.com', 'key' => $key, 'urlList' => ['https://www.example.com/a']], JSON_THROW_ON_ERROR);
            foreach (['ok200' => 200, 'pending202' => 202, 'forbidden403' => 403, 'unprocessable422' => 422, 'ratelimit429' => 429] as $scenario => $expected) {
                [$status] = self::request($base . '/indexnow', 'POST', $payload, ['Content-Type: application/json', 'X-Mock-Scenario: ' . $scenario]);
                self::assertSame($expected, $status, $scenario);
            }
            [$status] = self::request($base . '/indexnow', 'POST', json_encode(['host' => 'www.example.com', 'key' => $key, 'urlList' => ['https://other.example.com/a']], JSON_THROW_ON_ERROR), ['Content-Type: application/json']);
            self::assertSame(422, $status, 'a URL of another host is refused like the real endpoint does');

            [$status, $body] = self::request($base . '/_mock/requests');
            self::assertSame(200, $status);
            /** @var list<array{scenario: string, json: array{urlList: list<string>}}> $log */
            $log = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            self::assertCount(6, $log);
            self::assertSame('ratelimit429', $log[4]['scenario']);
            self::assertSame(['https://www.example.com/a'], $log[0]['json']['urlList']);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    private static function freePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertNotFalse($sock, $errstr);
        $name = (string) stream_socket_get_name($sock, false);
        fclose($sock);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private static function waitFor(int $port): void
    {
        for ($i = 0; $i < 50; ++$i) {
            usleep(100_000);
            $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if (\is_resource($sock)) {
                fclose($sock);

                return;
            }
        }
        self::fail('the mock server did not start');
    }

    /**
     * @param list<string> $headers
     *
     * @return array{0: int, 1: string}
     */
    private static function request(string $url, string $method = 'GET', ?string $body = null, array $headers = []): array
    {
        $context = stream_context_create(['http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body ?? '', 'ignore_errors' => true, 'timeout' => 5]]);
        $response = (string) file_get_contents($url, false, $context);
        /** @var list<string> $http_response_header */
        preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m);

        return [(int) ($m[1] ?? 0), $response];
    }
}
