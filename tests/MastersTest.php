<?php

declare(strict_types=1);

use Qryno\Masters;

test('マスタ: 元システムと同じ選択肢（所属協会54件・会社名24件、同じ順序）', static function (): void {
    $masters = masters();
    assertSame(54, count($masters->kyokaiOptions()));
    assertSame('010　札幌', $masters->kyokaiOptions()[0]);
    assertSame('470　沖縄県', $masters->kyokaiOptions()[53]);
    assertSame(24, count($masters->companyOptions()));
    assertSame('代理店', $masters->companyOptions()[0]);
    assertSame('エイアイジー・スター生命保険株式会社', $masters->companyOptions()[23]);
});

test('マスタ: 所属協会は「コード　名称」「コード」「名称」のどれでも同じキー', static function (): void {
    $masters = masters();
    foreach (['130　東京', '130 東京', '130', '東京', '１３０'] as $value) {
        assertSame('130', $masters->resolveKyokai($value), $value);
    }
    assertSame('010', $masters->resolveKyokai('10'), 'Excelで先頭の0が消えた場合');
    assertSame('010', $masters->resolveKyokai('札幌'));
    assertSame(null, $masters->resolveKyokai('999'));
    assertSame(null, $masters->resolveKyokai(''));
    assertSame('130　東京', $masters->kyokaiLabel('130'));
});

test('マスタ: 会社名は全角/半角の違いを同一とみなす', static function (): void {
    $masters = masters();
    $key = $masters->resolveCompany('ＳＯＭＰＯひまわり生命保険株式会社');
    assertTrue($key !== null);
    assertSame($key, $masters->resolveCompany('SOMPOひまわり生命保険株式会社'));
    assertSame('ＳＯＭＰＯひまわり生命保険株式会社', $masters->companyLabel((string) $key));
    assertSame(null, $masters->resolveCompany('日本生命'), '略称は別物');
});

test('マスタ: 重複した項目はエラー', static function (): void {
    $cases = [
        '協会名の重複' => [['010　札幌', '011　札幌'], []],
        'コードの重複' => [['010　札幌', '010　函館'], []],
        '会社名の重複（全角/半角違い）' => [['010　札幌'], ['ＡＢＣ生命', 'ABC生命']],
    ];
    foreach ($cases as $case => [$kyokai, $companies]) {
        try {
            new Masters($kyokai, $companies);
            throw new AssertionFailed("{$case}: 例外が発生しませんでした");
        } catch (InvalidArgumentException) {
        }
    }
});

test('マスタ: 指紋はキーが変わったときだけ変わる', static function (): void {
    $a = new Masters(['010　札幌', '011　函館'], ['代理店', 'A生命']);
    $reordered = new Masters(['011　函館', '010　札幌'], ['A生命', '代理店']);
    $renamedKyokai = new Masters(['010　札幌市', '011　函館'], ['代理店', 'A生命']);
    $renamedCompany = new Masters(['010　札幌', '011　函館'], ['代理店', 'B生命']);
    assertSame($a->fingerprint(), $reordered->fingerprint());
    assertSame($a->fingerprint(), $renamedKyokai->fingerprint(), '協会名だけの変更はコードが同じなので影響なし');
    assertTrue($a->fingerprint() !== $renamedCompany->fingerprint());
});
