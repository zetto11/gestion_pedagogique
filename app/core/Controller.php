<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function render(string $view, array $data = [], ?array $layout = null): void
{
    extract($data, EXTR_SKIP);

    // Go two levels up to the project root
    $viewFile = dirname(__DIR__, 2) . '/views/' . $view . '.php';

    if (!is_file($viewFile)) {
        throw new \RuntimeException(
            'View not found: ' . $view . ' | Path: ' . $viewFile
        );
    }

    if ($layout === null) {
        require $viewFile;
        return;
    }

    ob_start();
    require $viewFile;
    $pageContent = ob_get_clean();

    require dirname(__DIR__, 2) . '/views/layouts/dashboard.php';
}

    protected function requireRole(string $role): void
    {
        if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? null) !== $role) {
            http_response_code(403);
            $this->redirect('login');
        }
    }

    protected function redirect(string $route): never
    {
        header('Location: index.php?route=' . rawurlencode($route));
        exit;
    }
}
