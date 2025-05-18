<?php // src/Core/Router.php

namespace App\Core;

use App\Core\Exception\RouteNotFoundException; // Changed namespace
use App\Core\Exception\AccessDeniedException; // Changed namespace & Custom exception

class Router
{
    protected array $routes = [];
    protected string $basePath = '';

    public function __construct()
    {
        // Get the base path from the defined constant or default to empty string
        $this->basePath = defined('BASE_PATH') ? BASE_PATH : '';
    }

    public function addRoute(string $method, string $path, array $handlerConfig): void // Renamed $handler to $handlerConfig for clarity
    {
        $this->routes[strtoupper($method)][$this->normalizePath($path)] = $handlerConfig;
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path, '/');
        return '/' . $path;
    }

    public function dispatch(string $requestMethod, string $requestUri): void
    {
        $requestMethod = strtoupper($requestMethod);
        
        // Remove the base path from the request URI if it exists
        if (!empty($this->basePath) && strpos($requestUri, $this->basePath) === 0) {
            $requestUri = substr($requestUri, strlen($this->basePath));
        }
        
        $uri = $this->normalizePath(parse_url($requestUri, PHP_URL_PATH) ?: '/');
        $rawBody = file_get_contents('php://input'); // Read raw body once

        foreach ($this->routes[$requestMethod] ?? [] as $routePath => $routeConfig) {
            $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                
                // Create Request and Response objects once per dispatch
                $request = new Request($_GET, $_POST, $_COOKIE, $_FILES, $_SERVER, $rawBody);
                $response = new Response();

                $this->callHandler($routeConfig, $request, $response, $params);
                return;
            }
        }
        throw new RouteNotFoundException("No route found for {$requestMethod} {$uri}");
    }

    protected function callHandler(array $routeConfig, Request $request, Response $response, array $params = []): void
    {
        // Determine the actual handler and middleware configuration
        $actualHandler = $routeConfig['handler'] ?? $routeConfig;
        $middlewareConfig = $routeConfig['middleware'] ?? null;

        if (!is_array($actualHandler) || count($actualHandler) !== 2) {
            throw new \RuntimeException("Invalid handler configuration for route. Expected [ControllerClass, 'methodName'].");
        }
        [$controllerClass, $methodName] = $actualHandler;

        if (!class_exists($controllerClass)) {
            throw new \RuntimeException("Controller class {$controllerClass} not found.");
        }

        $controller = new $controllerClass();

        // Middleware check
        if ($middlewareConfig) {
            $middlewares = is_array($middlewareConfig) ? $middlewareConfig : [$middlewareConfig];
            foreach ($middlewares as $middlewareName) {
                // Construct middleware class name, assuming a convention like 'auth:admin' -> AuthMiddleware with 'admin' param
                // This part might need more sophisticated parsing if middleware params are complex.
                // For 'auth:admin', $middlewareName is 'auth:admin'. We might need to parse this.
                // Simple example: if middleware is just 'auth', class is 'AuthMiddleware'.
                // If 'auth:admin', class is 'AuthMiddleware', and 'admin' is passed to its handle or constructor.
                // For now, let's assume middleware name directly maps to class or needs specific parsing.
                // The current example 'auth:admin' implies the middleware itself handles the role.
                
                // Simplified middleware name to class mapping
                $middlewareParts = explode(':', $middlewareName, 2);
                $baseMiddlewareName = ucfirst($middlewareParts[0]);
                $middlewareClass = 'App\\Middleware\\' . $baseMiddlewareName . 'Middleware';


                if (!class_exists($middlewareClass)) {
                    throw new \RuntimeException("Middleware {$middlewareClass} (derived from {$middlewareName}) not found.");
                }
                $middlewareInstance = new $middlewareClass();
                
                // The middleware handle method should throw AccessDeniedException or return void
                // It might also need parameters (e.g., the role 'admin' from 'auth:admin')
                // For simplicity, the current middleware structure seems to handle this internally or via constructor.
                $middlewareInstance->handle($request, $middlewareParts[1] ?? null); // Pass request and optional parameter
            }
        }

        if (!method_exists($controller, $methodName)) {
            throw new \RuntimeException("Method {$methodName} not found in controller {$controllerClass}.");
        }

        // Inject Request, Response objects, and route parameters
        $reflectionMethod = new \ReflectionMethod($controllerClass, $methodName);
        $methodArgs = [];
        foreach ($reflectionMethod->getParameters() as $param) {
            $paramReflectionName = $param->getName(); // Use $paramReflectionName to avoid conflict with $params array
            $paramType = $param->getType() ? $param->getType()->getName() : null;

            if (isset($params[$paramReflectionName])) {
                $methodArgs[] = $params[$paramReflectionName];
            } elseif ($paramType === Request::class) {
                $methodArgs[] = $request; // Use the passed Request object
            } elseif ($paramType === Response::class) {
                $methodArgs[] = $response; // Use the passed Response object
            } elseif ($param->isDefaultValueAvailable()) {
                $methodArgs[] = $param->getDefaultValue();
            } else {
                // This case should ideally not be hit if controller methods are type-hinted correctly
                // or all non-typed, non-defaulted params are route params.
                 throw new \RuntimeException("Cannot resolve parameter '{$paramReflectionName}' for {$controllerClass}::{$methodName}. Type: {$paramType}, Is Optional: " . ($param->isOptional() ? 'Yes' : 'No'));
            }
        }
        $controller->$methodName(...$methodArgs);
    }
}
