<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Plain PHP templates. Views escape output with e(); layouts receive $content.
 */
final class View
{
    /** @var array<string, mixed>|null */
    private static ?array $shared = null;

    /** @param array<string, mixed> $data */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::partial($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string, mixed> $data */
    public static function partial(string $view, array $data = []): string
    {
        $file = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!preg_match('#^[a-z0-9/_-]+$#', $view) || !is_file($file)) {
            throw new \RuntimeException("View not found: $view");
        }
        $data = array_merge(self::shared(), $data);
        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                require $__file;
                return (string) ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        })($file, $data);
    }

    /** Old input, field errors and flash messages are read once per request. @return array<string, mixed> */
    public static function shared(): array
    {
        if (self::$shared === null) {
            $old = $_SESSION['_old'] ?? [];
            $errors = $_SESSION['_errors'] ?? [];
            unset($_SESSION['_old'], $_SESSION['_errors']);
            self::$shared = [
                '_old' => is_array($old) ? $old : [],
                '_errors' => is_array($errors) ? $errors : [],
            ];
        }
        return self::$shared;
    }

    public static function resetShared(): void
    {
        self::$shared = null;
    }
}
