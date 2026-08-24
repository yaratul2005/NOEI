<?php

declare(strict_types=1);

namespace Core;

/**
 * HTTP Response abstraction for NOEI CMS.
 * Manages status codes, headers, secure cookies, and body rendering.
 */
class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private array $cookies = [];
    private string $content = '';
    private bool $sent = false;

    /**
     * @param string $content
     * @param int $statusCode
     * @param array $headers
     */
    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Set HTTP status code.
     *
     * @param int $code
     * @return self
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Add or replace a response header.
     *
     * @param string $name
     * @param string $value
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * Set a cookie with secure defaults.
     *
     * @param string $name
     * @param string $value
     * @param int $expires Unix timestamp
     * @param string $path
     * @param string $domain
     * @param bool $secure
     * @param bool $httpOnly
     * @param string $sameSite 'Lax'|'Strict'|'None'
     * @return self
     */
    public function setCookie(
        string $name,
        string $value,
        int $expires = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = true,
        string $sameSite = 'Lax'
    ): self {
        $this->cookies[$name] = [
            'value' => $value,
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => $sameSite,
        ];
        return $this;
    }

    /**
     * Set response content string.
     *
     * @param string $content
     * @return self
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Return JSON response helper.
     *
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     * @return self
     */
    public function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Content-Type', 'application/json; charset=utf-8');

        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }

        $this->setContent((string)json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $this;
    }

    /**
     * Return HTTP Redirect response helper.
     *
     * @param string $url
     * @param int $statusCode
     * @return self
     */
    public function redirect(string $url, int $statusCode = 302): self
    {
        $this->setStatusCode($statusCode);
        $this->setHeader('Location', $url);
        return $this;
    }

    /**
     * Send HTTP headers, cookies, and output content.
     */
    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }

            foreach ($this->cookies as $name => $cookie) {
                setcookie($name, $cookie['value'], [
                    'expires' => $cookie['expires'],
                    'path' => $cookie['path'],
                    'domain' => $cookie['domain'],
                    'secure' => $cookie['secure'],
                    'httponly' => $cookie['httponly'],
                    'samesite' => $cookie['samesite'],
                ]);
            }
        }

        echo $this->content;
        $this->sent = true;
    }
}
