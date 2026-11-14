<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Deterministic HTTP fixtures; the production breach constraint/validator stays enabled. */
final class PasswordBreachHttpClient extends MockHttpClient
{
    private static ?string $compromisedHash = null;
    private static int $status = 200;
    private static int $successfulRequestsBeforeFailure = 0;
    /** @var list<array{method: string, url: string, headers: mixed}> */
    public static array $requests = [];

    public function __construct()
    {
        parent::__construct(static function (string $method, string $url, array $options): MockResponse {
            self::$requests[] = ['method' => $method, 'url' => $url, 'headers' => $options['headers'] ?? []];
            $body = str_repeat('0', 35).":0\r\n";
            if (null !== self::$compromisedHash && str_ends_with($url, substr(self::$compromisedHash, 0, 5))) {
                $body .= substr(self::$compromisedHash, 5).":42\r\n";
            }

            $status = count(self::$requests) <= self::$successfulRequestsBeforeFailure ? 200 : self::$status;

            return new MockResponse($body, ['http_code' => $status]);
        });
    }

    public static function configureFixture(?string $compromisedPassword = null, int $status = 200, int $successfulRequestsBeforeFailure = 0): void
    {
        self::$compromisedHash = null === $compromisedPassword ? null : strtoupper(sha1($compromisedPassword));
        self::$status = $status;
        self::$successfulRequestsBeforeFailure = $successfulRequestsBeforeFailure;
        self::$requests = [];
    }
}
