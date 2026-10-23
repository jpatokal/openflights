<?php

use PHPUnit\Framework\TestCase;

/**
 * PHPUnit base class for the OpenFlights server test suite.
 *
 * Reproduces the small subset of the SimpleTest WebTestCase/UnitTestCase API
 * that the test suite relied on, implemented on top of PHPUnit. It keeps an
 * in-memory cookie jar so the PHP session established by login() persists
 * across the requests within a single test.
 */
abstract class OpenFlightsTestCase extends TestCase
{
    /** @var string Last HTTP response body. */
    protected $lastResponse = '';

    /** @var array<string,string> Cookie name => value. */
    protected $cookies = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->lastResponse = '';
        $this->cookies = [];
    }

    /**
     * Perform an HTTP request against the running OpenFlights server and
     * return the response body.
     */
    public function request(string $method, string $url, array $params = []): string
    {
        $method = strtoupper($method);
        $query = '';
        $content = '';

        if ($method === 'GET') {
            if ($params) {
                $query = '?' . http_build_query($params);
            }
        } else {
            $content = http_build_query($params);
        }

        $headers = "Content-Type: application/x-www-form-urlencoded\r\n";
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = $name . '=' . $value;
            }
            $headers .= 'Cookie: ' . implode('; ', $pairs) . "\r\n";
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $content,
                'ignore_errors' => true,
                'timeout' => 30,
            ],
        ]);

        $body = @file_get_contents($url . $query, false, $context);
        $this->lastResponse = $body === false ? '' : $body;
        $this->storeCookies($http_response_header ?? []);

        return $this->lastResponse;
    }

    public function post(string $url, array $params = []): string
    {
        return $this->request('POST', $url, $params);
    }

    public function get(string $url, array $params = []): string
    {
        return $this->request('GET', $url, $params);
    }

    private function storeCookies(array $headers): void
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Set-Cookie:') !== 0) {
                continue;
            }

            $cookie = trim(substr($header, strlen('Set-Cookie:')));
            $pair = explode(';', $cookie, 2)[0];
            $eq = strpos($pair, '=');
            if ($eq === false) {
                continue;
            }

            $name = trim(substr($pair, 0, $eq));
            $value = trim(substr($pair, $eq + 1));

            if ($value === '' || strcasecmp($value, 'deleted') === 0) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $value;
            }
        }
    }

    // ---- SimpleTest-compatible assertion wrappers --------------------------

    public function assertText(string $needle): void
    {
        $this->assertStringContainsString($needle, $this->lastResponse);
    }

    public function assertNoText(string $needle): void
    {
        $this->assertStringNotContainsString($needle, $this->lastResponse);
    }

    public function assertPattern(string $pattern, ?string $subject = null): void
    {
        $this->assertMatchesRegularExpression($pattern, $subject ?? $this->lastResponse);
    }

    public function assertEqual($expected, $actual): void
    {
        $this->assertEquals($expected, $actual);
    }

    public function assertNotEqual($expected, $actual): void
    {
        $this->assertNotEquals($expected, $actual);
    }
}

// Compatibility aliases so the existing test files can keep extending the
// SimpleTest class names without any changes.
class WebTestCase extends OpenFlightsTestCase
{
}

class UnitTestCase extends OpenFlightsTestCase
{
}
