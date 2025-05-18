<?php // src/Core/Controller.php

namespace App\Core;

abstract class Controller
{
    protected View $view;

    public function __construct()
    {
        $this->view = new View(PROJECT_ROOT . '/templates');
    }

    protected function render(string $template, array $data = [], ?string $layout = 'layouts/main.php'): void
    {
        // Automatically add CSRF token for forms if not already set by controller
        if (empty($data['csrf_token_name']) && empty($data['csrf_token_value'])) {
             $data['csrf_token_name'] = 'csrf_token'; // Default name
             $data['csrf_token_value'] = $this->generateCsrfToken();
        }
        
        echo $this->view->render($template, $data, $layout);
        exit; // Stop execution after rendering
    }
    
    protected function generateCsrfToken(string $formKey = 'default_form'): string
    {
        if (empty($_SESSION['csrf_tokens'][$formKey])) {
            $_SESSION['csrf_tokens'][$formKey] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_tokens'][$formKey];
    }

    protected function verifyCsrfToken(Request $request, string $formKey = 'default_form', string $tokenFieldName = 'csrf_token'): bool
    {
        $token = $request->post($tokenFieldName);
        if (!empty($token) && isset($_SESSION['csrf_tokens'][$formKey]) && hash_equals($_SESSION['csrf_tokens'][$formKey], $token)) {
            unset($_SESSION['csrf_tokens'][$formKey]); // Use token once
            return true;
        }
        return false;
    }
}
