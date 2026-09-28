<?php

declare(strict_types=1);

namespace Qryno;

/** PHPテンプレート（app/views）の描画 */
final class View
{
    /** @param array<string, mixed> $shared すべてのテンプレートに渡す値 */
    public function __construct(
        private readonly string $directory,
        private readonly string $publicDir,
        private readonly array $shared = [],
    ) {
    }

    /** @param array<string, mixed> $vars */
    public function render(string $template, array $vars = []): string
    {
        $vars += $this->shared + ['view' => $this];
        $file = $this->directory . '/' . $template . '.php';

        return (static function (string $__file, array $__vars): string {
            extract($__vars, EXTR_SKIP);
            ob_start();
            try {
                require $__file;

                return (string) ob_get_contents();
            } finally {
                ob_end_clean();
            }
        })($file, $vars);
    }

    /**
     * レイアウト付きで1画面を描画します。
     *
     * @param array<string, mixed> $vars
     */
    public function page(string $template, array $vars = [], string $layout = 'layout'): string
    {
        return $this->render($layout, ['content' => $this->render($template, $vars)] + $vars);
    }

    /** 静的ファイルのURL（更新時にブラウザのキャッシュが切り替わるよう更新日時を付けます） */
    public function asset(string $path): string
    {
        $file = $this->publicDir . '/' . $path;

        return is_file($file) ? $path . '?v=' . filemtime($file) : $path;
    }
}
