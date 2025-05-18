<?php // src/Core/Router.php

namespace App\Core;

// Ensure correct namespace for exceptions
use App\Core\Exception\RouteNotFoundException;
use App\Core\Exception\AccessDeniedException;

// Use ReflectionClass for accessing properties/methods that might be protected
use ReflectionClass;
use ReflectionException; // Import ReflectionException


class Router
{
    protected array $routes = [];
    protected string $basePath = '';

    public function __construct()
    {
        // Get the base path from the defined constant or default to empty string
        // BASE_PATH is now expected to be defined in the entry script (./index.php)
        $this->basePath = defined('BASE_PATH') ? BASE_PATH : '';
    }

    public function addRoute(string $method, string $path, array $handlerConfig): void
    {
        // Normalize path when adding it to the routes array
        $this->routes[strtoupper($method)][$this->normalizePath($path)] = $handlerConfig;
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        // Optional: Remove trailing slash unless it's just '/'
        // This ensures /about and /about/ map to the same internal route key.
        if (strlen($path) > 1 && substr($path, -1) === '/') {
            $path = substr($path, 0, -1);
        }
        return $path;
    }

    public function dispatch(string $requestMethod, string $requestUri): void
    {
        $requestMethod = strtoupper($requestMethod);

        // Request URI received here is ALREADY adjusted by api/index.php
        // to remove BASE_PATH and ensure it starts with '/'.
        // So we just need to normalize it again to match internal route keys.
        $uri = $this->normalizePath(parse_url($requestUri, PHP_URL_PATH) ?: '/');

        $rawBody = file_get_contents('php://input'); // Read raw body once

        // Create Request and Response objects once per dispatch
        $request = new Request($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, $rawBody);
        $response = new Response();

        foreach ($this->routes[$requestMethod] ?? [] as $routePath => $routeConfig) {
            // Build regex pattern for the route path
            // Escape forward slashes in the route path before building the regex
            $escapedRoutePath = str_replace('/', '\/', $routePath);
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^\/]+)', $escapedRoutePath); // Match any char except / for params
            $pattern = '#^' . $pattern . '$#'; // Anchor to beginning and end

            if (preg_match($pattern, $uri, $matches)) {
                // Extract named parameters from URL (keys are strings)
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                // Remove the full match (key 0) if it exists
                unset($params[0]);

                $this->callHandler($routeConfig, $request, $response, $params);
                return; // Stop processing after finding and calling a route
            }
        }
        // If no route matched after checking all of them
        throw new RouteNotFoundException("No route found for {$requestMethod} {$uri}");
    }

    protected function callHandler(array $routeConfig, Request $request, Response $response, array $params = []): void
    {
        $actualHandler = $routeConfig['handler'] ?? $routeConfig;
        $middlewareConfig = $routeConfig['middleware'] ?? null;

        if (!is_array($actualHandler) || count($actualHandler) !== 2 || !is_string($actualHandler[0]) || !is_string($actualHandler[1])) {
            throw new \RuntimeException("Invalid handler configuration for route. Expected [ControllerClass::class, 'methodName'].");
        }
        [$controllerClass, $methodName] = $actualHandler;

        if (!class_exists($controllerClass)) {
            throw new \RuntimeException("Controller class {$controllerClass} not found.");
        }

        $controller = new $controllerClass(); // Controllers instantiated here

        // Middleware check
        if ($middlewareConfig) {
            $middlewares = is_array($middlewareConfig) ? $middlewareConfig : [$middlewareConfig];
            foreach ($middlewares as $middlewareDefinition) {
                // Parse middleware definition like 'auth:admin,staff'
                $middlewareParts = explode(':', $middlewareDefinition, 2);
                $baseMiddlewareName = ucfirst($middlewareParts[0]);
                $middlewareClass = 'App\\Middleware\\' . $baseMiddlewareName . 'Middleware'; // Assumes standard naming

                if (!class_exists($middlewareClass)) {
                    throw new \RuntimeException("Middleware class {$middlewareClass} (derived from '{$middlewareDefinition}') not found.");
                }

                $middlewareInstance = new $middlewareClass();

                // Prepare middleware parameters (Request object + roles for AuthMiddleware)
                $middlewareParams = [];
                $middlewareParams[] = $request; // Pass the Request object first

                if (isset($middlewareParts[1])) {
                     // Split the roles string by comma and trim whitespace
                    $roles = array_map('trim', explode(',', $middlewareParts[1]));
                    // Add roles as individual parameters
                    $middlewareParams = array_merge($middlewareParams, $roles);
                }

                // Check if the middleware has a 'handle' method
                 if (!method_exists($middlewareInstance, 'handle')) {
                     throw new \RuntimeException("Middleware class {$middlewareClass} must have a public 'handle' method.");
                 }

                // Call the middleware handle method with collected arguments
                try {
                    $reflectionMiddleware = new ReflectionMethod($middlewareClass, 'handle');
                    $reflectionMiddleware->invokeArgs($middlewareInstance, $middlewareParams);

                } catch (ReflectionException $e) {
                     throw new \RuntimeException("Error invoking middleware method 'handle' on {$middlewareClass}: " . $e->getMessage(), 0, $e);
                }
                 // AuthMiddleware::handle is expected to throw AccessDeniedException if validation fails.
                 // If it returns void, middleware passed.

            }
        }

        // Prepare controller method arguments
        $reflectionMethod = new \ReflectionMethod($controllerClass, $methodName);
        $methodArgs = [];
        foreach ($reflectionMethod->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType() && !$param->getType()->isBuiltin() ? $param->getType()->getName() : null; // Get class name for objects

            if ($paramType === Request::class) {
                $methodArgs[] = $request;
            } elseif ($paramType === Response::class) {
                $methodArgs[] = $response;
            } elseif (array_key_exists($paramName, $params)) {
                // Pass route parameters by name
                $methodArgs[] = $params[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $methodArgs[] = $param->getDefaultValue();
            } else {
                 // If a required parameter isn't a route param or injected service
                 // This indicates a mismatch between route definition and controller method signature
                 throw new \RuntimeException("Cannot resolve required parameter '{$paramName}' for {$controllerClass}::{$methodName}.");
            }
        }

        // Call the controller method with collected arguments
        $controller->$methodName(...$methodArgs);
    }
}