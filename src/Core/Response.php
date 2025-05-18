<?php // src/Core/Response.php
namespace App\Core;

class Response
{
    public function json(array $data, int $status = 200, array $headers = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        foreach ($headers as $name => $value) header("$name: $value");
        echo json_encode($data);
        exit;
    }

    public function redirect(string $url, int $status = 302): void
    {
        $appConfig = require PROJECT_ROOT . '/config/app.php';
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) { // Relative path from app root
            $url = rtrim($appConfig['url'], '/') . $url;
        }
        header("Location: $url", true, $status);
        exit;
    }

    public function html(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }
}
