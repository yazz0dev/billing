<?php // src/Core/View.php

namespace App\Core;

class View
{
    protected string $templateDir;
    protected array $globals = [];

    public function __construct(string $templateDir)
    {
        $this->templateDir = rtrim($templateDir, '/');
        $this->globals['appConfig'] = require PROJECT_ROOT . '/config/app.php';
        $this->globals['session'] = $_SESSION ?? [];
        // Helper function for escaping HTML (can be used in templates as e($var))
        $this->globals['e'] = function($string) {
            return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
        };
    }

    public function render(string $templateFile, array $data = [], ?string $layoutFile = null): string
    {
        $data = array_merge($this->globals, $data);
        extract($data);

        ob_start();
        $templatePath = $this->templateDir . '/' . ltrim($templateFile, '/');
        if (!file_exists($templatePath)) {
            throw new \RuntimeException("View template not found: {$templatePath}");
        }
        require $templatePath;
        $content = ob_get_clean(); // This is the content for the layout

        if ($layoutFile) {
            ob_start();
            $layoutPath = $this->templateDir . '/' . ltrim($layoutFile, '/');
            if (!file_exists($layoutPath)) {
                throw new \RuntimeException("Layout template not found: {$layoutPath}");
            }
            // $content is now available in the layout
            require $layoutPath;
            return ob_get_clean();
        }
        return $content;
    }
}
