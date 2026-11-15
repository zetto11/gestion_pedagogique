<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) return;
    $parts = explode('\\', substr($class, strlen($prefix)), 2);
    if (count($parts) !== 2) return;
    $path = __DIR__ . '/app/' . strtolower($parts[0]) . '/' . $parts[1] . '.php';
    if (is_file($path)) require $path;
});
