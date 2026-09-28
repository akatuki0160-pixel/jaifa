<?php

declare(strict_types=1);

/**
 * 画面の通し確認: PHP組み込みサーバーを起動し、ブラウザと同じようにリクエストを送ります。
 */

const ADMIN_PASSWORD = 'test-password-1234';

/**
 * テスト用サーバーを起動してURLを返します（同じ設定なら使い回し）。
 *
 * @param array<string, mixed> $overrides
 */
function httpServer(array $overrides = []): string
{
    static $servers = [];
    $id = md5(serialize($overrides));
    if (isset($servers[$id])) {
        return $servers[$id];
    }

    $dir = tempDir();
    $GLOBALS['httpServerLogs'][] = $dir . '/server.log';
    $config = Qryno\Config::merge([
        'data_dir' => $dir . '/data',
        'admin' => ['password_hash' => password_hash(ADMIN_PASSWORD, PASSWORD_DEFAULT)],
        'rate_limit' => ['search' => ['max' => 5, 'window' => 600]],
    ], $overrides);
    file_put_contents($dir . '/config.php', '<?php return ' . var_export($config, true) . ';');
    $dataDir = $config['data_dir'];

    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr((string) strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);

    $env = getenv();
    $env['QRYNO_CONFIG'] = $dir . '/config.php';
    $log = ['file', $dir . '/server.log', 'a'];
    $process = proc_open(
        [PHP_BINARY, '-S', "127.0.0.1:{$port}", '-t', dirname(__DIR__) . '/public'],
        [0 => ['pipe', 'r'], 1 => $log, 2 => $log],
        $pipes,
        null,
        $env
    );
    Tests::onFinish(static function () use ($process): void {
        proc_terminate($process);
        proc_close($process);
    });

    $base = "http://127.0.0.1:{$port}";
    for ($i = 0; $i < 50; $i++) {
        $connection = @fsockopen('127.0.0.1', $port);
        if ($connection !== false) {
            fclose($connection);

            $GLOBALS['httpServerDataDirs'][$base] = $dataDir;

            return $servers[$id] = $base;
        }
        usleep(100_000);
    }
    throw new RuntimeException('テスト用サーバーが起動しませんでした');
}

/** Cookieを保持する簡易ブラウザ */
final class Browser
{
    private string $cookieJar;

    public function __construct(private readonly string $base)
    {
        $this->cookieJar = tempDir() . '/cookies.txt';
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function get(string $path): array
    {
        return $this->request($path, []);
    }

    /**
     * @param array<string, mixed> $fields CURLFile を含めるとファイル送信
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function post(string $path, array $fields): array
    {
        $multipart = array_filter($fields, static fn ($value): bool => $value instanceof CURLFile) !== [];

        return $this->request($path, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $multipart ? $fields : http_build_query($fields),
        ]);
    }

    /** 画面内のフォームのトークン */
    public static function token(string $body, string $name): string
    {
        preg_match('/name="' . $name . '" value="([^"]+)"/', $body, $m);

        return $m[1] ?? '';
    }

    /**
     * @param array<int, mixed> $options
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    private function request(string $path, array $options): array
    {
        $headers = [];
        $curl = curl_init($this->base . '/' . $path);
        curl_setopt_array($curl, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_PROXY => '',
            CURLOPT_NOPROXY => '*',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }

                return strlen($line);
            },
        ]);
        $body = (string) curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        return ['status' => $status, 'headers' => $headers, 'body' => $body];
    }
}

/** 入力画面を開いてから検索する */
function search(Browser $browser, string $kyokai, string $sei, string $mei, string $company): array
{
    $form = $browser->get('qryNo.php');

    return $browser->post('qryNoExe.php', [
        'sesstxt' => Browser::token($form['body'], 'sesstxt'),
        'kyokai' => $kyokai,
        'sei' => $sei,
        'mei' => $mei,
        'company' => $company,
    ]);
}

function adminLogin(Browser $browser): void
{
    $page = $browser->get('admin.php');
    $response = $browser->post('admin.php', ['token' => Browser::token($page['body'], 'token'), 'action' => 'login', 'password' => ADMIN_PASSWORD]);
    assertSame(303, $response['status'], 'ログイン');
}

test('HTTP: 入力画面（元画面と同じ項目・セキュリティヘッダー）', static function (): void {
    $response = (new Browser(httpServer()))->get('qryNo.php');
    assertSame(200, $response['status']);
    assertContains('<h1>会員番号のお問い合わせ</h1>', $response['body']);
    assertSame(54 + 24 + 2, substr_count($response['body'], '<option '));
    assertContains('<option value="130　東京">130　東京</option>', $response['body']);
    assertContains('name="sesstxt"', $response['body']);
    assertContains('https://f.msgs.jp/n/form/jiv/4eHnpERERae9RZ6rVEeT3', $response['body']);
    assertNotContains('未入力項目があります', $response['body']);
    assertContains('no-store', $response['headers']['cache-control']);
    assertSame('DENY', $response['headers']['x-frame-options']);
    assertContains("frame-ancestors 'none'", $response['headers']['content-security-policy']);
    assertContains('QRYNOSESS=', $response['headers']['set-cookie']);
    assertContains('HttpOnly', $response['headers']['set-cookie']);
});

test('HTTP: 会員データ未登録なら「ご利用いただけません」（503）', static function (): void {
    $response = search(new Browser(httpServer()), '130　東京', '山田', '太郎', '代理店');
    assertSame(503, $response['status']);
    assertContains('ご利用いただけません', $response['body']);
});

test('HTTP: 管理画面でCSVを取り込むと検索に反映される', static function (): void {
    $base = httpServer();
    $browser = new Browser($base);

    $login = $browser->get('admin.php');
    assertContains('type="password"', $login['body']);
    $wrong = $browser->post('admin.php', ['token' => Browser::token($login['body'], 'token'), 'action' => 'login', 'password' => 'wrong']);
    assertContains('パスワードが正しくありません', $wrong['body']);

    adminLogin($browser);
    $dashboard = $browser->get('admin.php');
    assertContains('会員データがまだ取り込まれていません', $dashboard['body']);

    $upload = $browser->post('admin.php', [
        'token' => Browser::token($dashboard['body'], 'token'),
        'action' => 'import',
        'csv' => new CURLFile(dirname(__DIR__) . '/sample/members_sample.csv', 'text/csv', 'members_sample.csv'),
    ]);
    assertSame(303, $upload['status']);
    $result = $browser->get('admin.php');
    assertContains('取込が完了しました', $result['body']);
    assertContains('登録件数</th><td><strong>10件', $result['body']);
    assertContains('小林 大輔', $result['body'], '重複の警告');

    $found = search(new Browser($base), '130　東京', '山田', '太郎', '代理店');
    assertContains('<strong class="memberNo">10000001</strong>', $found['body']);

    $template = $browser->get('admin.php?action=template');
    assertContains('text/csv', $template['headers']['content-type']);
    assertContains('会員番号,所属協会,姓,名,会社名', $template['body']);

    $logout = $browser->post('admin.php', ['token' => Browser::token($result['body'], 'token'), 'action' => 'logout']);
    assertSame(303, $logout['status']);
    assertContains('type="password"', $browser->get('admin.php')['body']);
});

test('HTTP: 検索結果（該当あり・該当なし・複数該当）', static function (): void {
    $browser = new Browser(httpServer());

    $found = search($browser, '010　札幌', ' 髙橋　', '健一', 'ＳＯＭＰＯひまわり生命保険株式会社');
    assertSame(200, $found['status']);
    assertContains('<main id="form2025" class="ok">', $found['body']);
    assertContains('<strong class="memberNo">10000004</strong>', $found['body']);
    assertContains('no-store', $found['headers']['cache-control']);

    $notFound = search($browser, '010　札幌', '高橋', '健一', 'ＳＯＭＰＯひまわり生命保険株式会社');
    assertContains('<main id="form2025" class="ng">', $notFound['body']);
    assertContains('検索できませんでした', $notFound['body']);
    assertContains('070-1595-0337', $notFound['body']);
    assertNotContains('10000004', $notFound['body']);

    $multiple = search($browser, '130　東京', '小林', '大輔', '代理店');
    assertContains('複数登録されているため', $multiple['body']);
    assertNotContains('1000000', $multiple['body'], '候補の会員番号は表示しない');
});

test('HTTP: 未入力・改ざん値・トークン不正・GETでの直接アクセス', static function (): void {
    $browser = new Browser(httpServer());

    $missing = search($browser, '130　東京', '山田', '', '');
    assertContains('未入力項目があります', $missing['body']);
    assertContains('value="山田" class="textField w40per"', $missing['body'], '入力値を保持し、入力済みは赤枠にしない');
    assertContains('name="mei" id="mei" value="" class="textField w40per alart"', $missing['body']);
    assertContains('<select name="company" id="company" class="alart"', $missing['body']);
    assertContains('<option value="130　東京" selected>', $missing['body']);

    $tampered = search($browser, '999　どこか', '山田', '太郎', '代理店');
    assertContains('<select name="kyokai" id="kyokai" class="alart"', $tampered['body']);

    $html = search($browser, '130　東京', '<script>alert(1)</script>', '', '代理店');
    assertContains('&lt;script&gt;alert(1)&lt;/script&gt;', $html['body']);
    assertNotContains('<script>alert(1)', $html['body']);

    $noToken = $browser->post('qryNoExe.php', ['kyokai' => '130　東京', 'sei' => '山田', 'mei' => '太郎', 'company' => '代理店']);
    assertContains('ページの有効期限が切れたため', $noToken['body']);
    assertNotContains('memberNo', $noToken['body']);

    $get = $browser->get('qryNoExe.php');
    assertSame(303, $get['status']);
    assertSame('qryNo.php', $get['headers']['location']);
});

test('HTTP: 管理画面はトークンのない操作を受け付けない', static function (): void {
    $browser = new Browser(httpServer());
    adminLogin($browser);
    $response = $browser->post('admin.php', [
        'action' => 'import',
        'csv' => new CURLFile(dirname(__DIR__) . '/sample/members_sample.csv', 'text/csv', 'x.csv'),
    ]);
    assertSame(303, $response['status']);
    assertContains('ページの有効期限が切れました', $browser->get('admin.php')['body']);
});

test('HTTP: 管理者パスワード未設定なら管理画面は無効', static function (): void {
    $response = (new Browser(httpServer(['admin' => ['password_hash' => '']])))->get('admin.php');
    assertSame(403, $response['status']);
    assertContains('管理画面は無効になっています', $response['body']);
});

test('HTTP: 会員データが壊れたら検索はエラー画面、管理画面から取り込み直して復旧', static function (): void {
    $base = httpServer();
    $database = $GLOBALS['httpServerDataDirs'][$base] . '/members.sqlite';
    file_put_contents($database, str_repeat('broken', 100));

    $error = search(new Browser($base), '130　東京', '山田', '太郎', '代理店');
    assertSame(500, $error['status']);
    assertContains('<h1>システムエラー</h1>', $error['body']);
    assertNotContains('SQLSTATE', $error['body'], '詳細は表示しない');

    $browser = new Browser($base);
    adminLogin($browser);
    $dashboard = $browser->get('admin.php');
    assertSame(200, $dashboard['status']);
    assertContains('会員データ（data/members.sqlite）を読み込めません', $dashboard['body']);
    $browser->post('admin.php', [
        'token' => Browser::token($dashboard['body'], 'token'),
        'action' => 'import',
        'csv' => new CURLFile(dirname(__DIR__) . '/sample/members_sample.csv', 'text/csv', 'members_sample.csv'),
    ]);
    assertContains('取込が完了しました', $browser->get('admin.php')['body']);
    assertContains('memberNo">10000001', search(new Browser($base), '130　東京', '山田', '太郎', '代理店')['body']);
});

test('HTTP: 該当なしが続くと一時的に検索できなくなる（429）', static function (): void {
    $browser = new Browser(httpServer());
    $limited = null;
    for ($i = 0; $i < 10 && $limited === null; $i++) {
        $response = search($browser, '130　東京', '存在', 'しない' . $i, '代理店');
        if ($response['status'] === 429) {
            $limited = $response;
        }
    }
    assertTrue($limited !== null, '429 になりませんでした');
    assertContains('検索の回数が上限に達した', $limited['body']);
    $blocked = search($browser, '130　東京', '山田', '太郎', '代理店');
    assertSame(429, $blocked['status'], '制限中は正しい入力でも検索しない');
});

test('HTTP: サーバー側でPHPの警告・エラーが出ていない', static function (): void {
    foreach ($GLOBALS['httpServerLogs'] ?? [] as $file) {
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_contains($line, 'broken') || str_contains($line, 'file is not a database')) {
                continue; // 復旧テストで意図的に壊したデータベースによるもの
            }
            if (preg_match('/PHP (Warning|Notice|Deprecated|Fatal)|\[qryno\]/', $line) === 1) {
                throw new AssertionFailed('サーバーログ: ' . $line);
            }
        }
    }
});
