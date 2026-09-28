<?php

/**
 * 動作確認・負荷確認用のダミー会員CSVを作ります（実在の会員データではありません）。
 *
 *   php bin/make-sample.php [件数=40000] [出力先=sample/members_dummy.csv] [--sjis]
 *
 * 既定は Excel でそのまま開ける BOM付きUTF-8、--sjis で Shift_JIS(CP932) です。
 * 同じ件数なら毎回同じ内容になります。
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$sjis = in_array('--sjis', $args, true);
$positional = array_values(array_filter($args, static fn (string $arg): bool => !str_starts_with($arg, '--')));
$count = (int) ($positional[0] ?? 40000);
$output = $positional[1] ?? $root . '/sample/members_dummy.csv';
if ($count < 1) {
    fwrite(STDERR, "使い方: php bin/make-sample.php [件数] [出力先] [--sjis]\n");
    exit(2);
}

$masters = require $root . '/config/masters.php';
$surnames = ['佐藤', '鈴木', '高橋', '田中', '伊藤', '渡辺', '山本', '中村', '小林', '加藤', '吉田', '山田', '佐々木', '山口', '松本',
    '井上', '木村', '林', '斎藤', '清水', '山崎', '森', '池田', '橋本', '阿部', '石川', '山下', '中島', '石井', '小川', '前田', '岡田',
    '長谷川', '藤田', '後藤', '近藤', '村上', '遠藤', '青木', '坂本', '斉藤', '福田', '太田', '西村', '藤井', '金子', '岡本', '藤原',
    '中野', '三浦', '原田', '中川', '松田', '竹内', '小野', '田村', '中山', '和田', '石田', '森田', '上田', '原', '内田', '柴田',
    '酒井', '宮崎', '横山', '高木', '安藤', '宮本', '大野', '小島', '工藤', '谷口', '今井', '髙橋', '山﨑', '渡邉', '齋藤', '吉﨑'];
$givenNames = ['太郎', '一郎', '健一', '誠', '翔太', '大輔', '直樹', '拓也', '健太', '隆', '浩', '学', '修', '剛', '聡', '亮',
    '花子', '恵子', '美穂', '裕子', '陽子', '真由美', '由美子', '久美子', '幸子', '直美', '明美', '智子', '香織', '愛', '彩', '舞',
    '和也', '達也', '雄一', '秀樹', '正人', '哲也', '大介', '慎一', '陽介', '俊介', '優子', '真理子', '理恵', '麻衣', '沙織', '千尋'];

mt_srand(20260928 + $count);
$pick = static fn (array $items): string => $items[mt_rand(0, count($items) - 1)];

$handle = fopen($output, 'wb');
if ($handle === false) {
    fwrite(STDERR, "出力先に書き込めません: {$output}\n");
    exit(1);
}
$write = static function (array $fields) use ($handle, $sjis): void {
    $line = implode(',', $fields) . "\r\n";
    fwrite($handle, $sjis ? mb_convert_encoding($line, 'CP932', 'UTF-8') : $line);
};

if (!$sjis) {
    fwrite($handle, "\xEF\xBB\xBF");
}
$write(['会員番号', '所属協会', '姓', '名', '会社名']);
for ($i = 1; $i <= $count; $i++) {
    $write([sprintf('%08d', $i), $pick($masters['kyokai']), $pick($surnames), $pick($givenNames), $pick($masters['company'])]);
}
fclose($handle);

echo sprintf("%s件のダミーデータを書き出しました: %s（%s）\n", number_format($count), $output, $sjis ? 'Shift_JIS' : 'UTF-8 BOM付き');
