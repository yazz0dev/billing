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
        $finalUrl = $url;
        if (strpos($url, '/') === 0 && strpos($url, '//') !== 0) { // Relative path from app root
            $basePath = defined('BASE_PATH') ? rtrim(BASE_PATH, '/') : '';
            $finalUrl = $basePath . $url;
        }
        // Ensure it's an absolute URL for the Location header if using APP_URL
        // $appConfig = require PROJECT_ROOT . '/config/app.php';
        // if (strpos($finalUrl, 'http') !== 0) {
        //    $finalUrl = rtrim($appConfig['url'], '/') . $finalUrl;
        // }

        header("Location: " . $finalUrl, true, $status);
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