<?php

declare(strict_types=1);

namespace Core;

/**
 * HTTP Request Abstraction for NOEI CMS.
 * Encapsulates global environment data and provides safe accessors.
 */
class Request
{
    private array $get;
    private array $post;
    private array $server;
    private array $files;
    private array $cookie;
    private ?string $rawBody = null;
    private ?array $parsedJson = null;

    /**
     * @param array|null $get
     * @param array|null $post
     * @param array|null $server
     * @param array|null $files
     * @param array|null $cookie
     */
    public function __construct(
        ?array $get = null,
        ?array $post = null,
        ?array $server = null,
        ?array $files = null,
        ?array $cookie = null
    ) {
        $this->get = $get ?? $_GET;
        $this->post = $post ?? $_POST;
        $this->server = $server ?? $_SERVER;
        $this->files = $files ?? $_FILES;
        $this->cookie = $cookie ?? $_COOKIE;
    }

    /**
     * Get the HTTP request method (e.g. GET, POST, PUT, DELETE).
     *
     * @return string
     */
    public function getMethod(): string
    {
        $method = $this->server['REQUEST_METHOD'] ?? 'GET';
        $method = strtoupper($method);

        if ($method === 'POST' && isset($this->post['_method'])) {
            return strtoupper((string)$this->post['_method']);
        }

        return $method;
    }

    /**
     * Check if request method matches.
     *
     * @param string $method
     * @return bool
     */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    public function isGet(): bool
    {
        return $this->isMethod('GET');
    }

    public function isPost(): bool
    {
        return $this->isMethod('POST');
    }

    /**
     * Determine if connection is secured via HTTPS, reverse proxies, or Cloudflare.
     *
     * @return bool
     */
    public function isHttps(): bool
    {
        if (!empty($this->server['HTTPS']) && strtolower((string)$this->server['HTTPS']) !== 'off') {
            return true;
        }

        if (strtolower((string)($this->server['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }

        if (strtolower((string)($this->server['HTTP_X_FORWARDED_SSL'] ?? '')) === 'on') {
            return true;
        }

        if ((string)($this->server['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        if (isset($this->server['HTTP_CF_VISITOR'])) {
            $cfVisitor = json_decode((string)$this->server['HTTP_CF_VISITOR'], true);
            if (is_array($cfVisitor) && ($cfVisitor['scheme'] ?? '') === 'https') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the URL scheme (http or https).
     *
     * @return string
     */
    public function getScheme(): string
    {
        return $this->isHttps() ? 'https' : 'http';
    }

    /**
     * Get the HTTP Hostname.
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->server['HTTP_HOST'] ?? ($this->server['SERVER_NAME'] ?? 'localhost');
    }

    /**
     * Get the dynamic installation base path (e.g. '' for root or '/cms' for subfolder).
     *
     * @return string
     */
    public function getBasePath(): string
    {
        $scriptName = $this->server['SCRIPT_NAME'] ?? '';
        if (empty($scriptName)) {
            return '';
        }

        $dir = str_replace('\\', '/', dirname($scriptName));
        return ($dir === '/' || $dir === '.') ? '' : '/' . trim($dir, '/');
    }

    /**
     * Get the full base URL including scheme, host, and subfolder base path.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->getScheme() . '://' . $this->getHost() . $this->getBasePath();
    }

    /**
     * Get full request URI.
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /**
     * Get clean request URI path (without query string and relative to dynamic base path).
     *
     * @return string
     */
    public function getPath(): string
    {
        $uri = $this->getUri();
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        $basePath = $this->getBasePath();
        if (!empty($basePath) && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        return '/' . trim((string)$path, '/');
    }

    /**
     * Retrieve parameter from GET.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->get[$key] ?? $default;
    }

    /**
     * Retrieve parameter from POST.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function post(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $default;
    }

    /**
     * Retrieve parameter from POST, GET, or JSON body in priority order.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        if (array_key_exists($key, $this->get)) {
            return $this->get[$key];
        }

        $json = $this->json();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }

        return $default;
    }

    /**
     * Get all request parameters merged.
     *
     * @return array
     */
    public function all(): array
    {
        return array_merge($this->get, $this->post, $this->json());
    }

    /**
     * Get uploaded file array.
     *
     * @param string $key
     * @return mixed
     */
    public function file(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    /**
     * Check if a file was uploaded under the given key.
     *
     * @param string $key
     * @return bool
     */
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key]) &&
            is_array($this->files[$key]) &&
            ($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * Retrieve cookie value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function cookie(string $key, mixed $default = null): mixed
    {
        return $this->cookie[$key] ?? $default;
    }

    /**
     * Get HTTP header value.
     *
     * @param string $key
     * @param string|null $default
     * @return string|null
     */
    public function header(string $key, ?string $default = null): ?string
    {
        $normalizedKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        
        if (isset($this->server[$normalizedKey])) {
            return $this->server[$normalizedKey];
        }

        if (strtoupper($key) === 'CONTENT_TYPE' && isset($this->server['CONTENT_TYPE'])) {
            return $this->server['CONTENT_TYPE'];
        }

        if (strtoupper($key) === 'CONTENT_LENGTH' && isset($this->server['CONTENT_LENGTH'])) {
            return $this->server['CONTENT_LENGTH'];
        }

        return $default;
    }

    /**
     * Check if request expects JSON or contains JSON content-type.
     *
     * @return bool
     */
    public function isJson(): bool
    {
        $contentType = $this->header('CONTENT_TYPE', '');
        return str_contains(strtolower($contentType), 'application/json');
    }

    /**
     * Check if request was sent via AJAX (X-Requested-With).
     *
     * @return bool
     */
    public function isAjax(): bool
    {
        return strtolower($this->header('X_REQUESTED_WITH', '')) === 'xmlhttprequest';
    }

    /**
     * Get Client IP address.
     *
     * @return string
     */
    public function ip(): string
    {
        if (!empty($this->server['HTTP_CLIENT_IP'])) {
            return $this->server['HTTP_CLIENT_IP'];
        }
        if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $this->server['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Get raw request input stream.
     *
     * @return string
     */
    public function rawBody(): string
    {
        if ($this->rawBody === null) {
            $this->rawBody = file_get_contents('php://input') ?: '';
        }
        return $this->rawBody;
    }

    /**
     * Parse raw request body as JSON array.
     *
     * @return array
     */
    public function json(): array
    {
        if ($this->parsedJson === null) {
            $body = $this->rawBody();
            if (!empty($body) && $this->isJson()) {
                $decoded = json_decode($body, true);
                $this->parsedJson = is_array($decoded) ? $decoded : [];
            } else {
                $this->parsedJson = [];
            }
        }
        return $this->parsedJson;
    }
}
