<?php // src/Core/Router.php

namespace App\Core;

use App\Core\Exception\RouteNotFoundException;
use App\Core\Exception\AccessDeniedException; // Custom exception

class Router
{
    protected array $routes = [];

    public function addRoute(string $method, string $path, array $handler): void
    {
        $this->routes[strtoupper($method)][$this->normalizePath($path)] = $handler;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        return '/' . $path;
    }

    public function dispatch(string $requestMethod, string $requestUri): void
    {
        $requestMethod = strtoupper($requestMethod);
        $uri = $this->normalizePath(parse_url($requestUri, PHP_URL_PATH) ?: '/');

        foreach ($this->routes[$requestMethod] ?? [] as $routePath => $handler) {
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->callHandler($handler, $params);
                return;
            }
        }
        throw new RouteNotFoundException("No route found for {$requestMethod} {$uri}");
    }

    protected function callHandler(array $handler, array $params = []): void
    {
        [$controllerClass, $method] = $handler;

        if (!class_exists($controllerClass)) {
            throw new \RuntimeException("Controller class {$controllerClass} not found.");
        }

        $controller = new $controllerClass(); // Basic, consider DI container for complex apps

        // Basic middleware check example (can be expanded)
        if (isset($handler['middleware'])) {
            $middlewares = is_array($handler['middleware']) ? $handler['middleware'] : [$handler['middleware']];
            foreach ($middlewares as $middlewareName) {
                $middlewareClass = 'App\\Middleware\\' . ucfirst($middlewareName) . 'Middleware';
                if (!class_exists($middlewareClass)) {
                    throw new \RuntimeException("Middleware {$middlewareClass} not found.");
                }
                $middlewareInstance = new $middlewareClass();
                $request = new Request($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, file_get_contents('php://input')); // Pass request
                
                // The middleware handle method should throw AccessDeniedException or return void
                $middlewareInstance->handle($request); 
            }
        }


        if (!method_exists($controller, $method)) {
            throw new \RuntimeException("Method {$method} not found in controller {$controllerClass}.");
        }

        // Inject Request and Response objects, and route parameters
        $reflectionMethod = new \ReflectionMethod($controllerClass, $method);
        $methodArgs = [];
        foreach ($reflectionMethod->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType() ? $param->getType()->getName() : null;

            if (isset($params[$paramName])) {
                $methodArgs[] = $params[$paramName];
            } elseif ($paramType === Request::class) {
                $methodArgs[] = new Request($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, file_get_contents('php://input'));
            } elseif ($paramType === Response::class) {
                $methodArgs[] = new Response();
            } elseif ($param->isDefaultValueAvailable()) {
                $methodArgs[] = $param->getDefaultValue();
            } else {
                // This logic might need adjustment if a parameter is not a route param or a core object and has no default.
                // For simplicity, we assume controller methods either take Request/Response, route params, or have defaults.
                // error_log("Router: Unresolved parameter '{$paramName}' for {$controllerClass}::{$method}");
                // throw new \RuntimeException("Cannot resolve parameter '{$paramName}' for {$controllerClass}::{$method}");
            }
        }
        $controller->$method(...$methodArgs);
    }
}

// Define custom exceptions
namespace App\Core\Exception;
class RouteNotFoundException extends \Exception {}
class AccessDeniedException extends \Exception {}999o
