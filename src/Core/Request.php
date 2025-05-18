<?php // src/Core/Request.php
namespace App\Core;

class Request
{
    public readonly array $query; // GET
    public readonly array $post;  // POST
    public readonly array $cookies;
    public readonly array $files;
    public readonly array $server;
    public readonly string $rawBody;
    private ?array $jsonBody = null;

    public function __construct(array $query, array $post, array $cookies, array $files, array $server, string $rawBody)
    {
        $this->query = $query;
        $this->post = $post;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->rawBody = $rawBody;
    }

    public function method(): string { return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET'); }
    public function path(): string { return parse_url($this->server['REQUEST_URI'], PHP_URL_PATH) ?: '/'; }
    public function get(string $key, $default = null) { return $this->query[$key] ?? $default; }
    public function post(string $key, $default = null) { return $this->post[$key] ?? $default; }
    public function input(string $key, $default = null) { return $this->post[$key] ?? $this->query[$key] ?? $default; }

    public function json(string $key = null, $default = null)
    {
        if ($this->jsonBody === null && str_contains($this->server['CONTENT_TYPE'] ?? '', 'application/json')) {
            $this->jsonBody = json_decode($this->rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) $this->jsonBody = []; // or handle error
        }
        if ($key === null) return $this->jsonBody;
        return $this->jsonBody[$key] ?? $default;
    }
}
