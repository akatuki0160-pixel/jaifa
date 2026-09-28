<?php

declare(strict_types=1);

use Qryno\Text;

test('正規化: 前後の空白（全角含む）を除く', static function (): void {
    assertSame('山田', Text::normalize("　山田 \t"));
});

test('正規化: 全角英数字は半角・大文字にそろえる', static function (): void {
    assertSame('SOMPOひまわり生命保険株式会社', Text::normalize('ＳＯＭＰＯひまわり生命保険株式会社'));
    assertSame('SMITH', Text::normalize('Ｓｍｉｔｈ'));
    assertSame('SMITH', Text::normalize('smith'));
    assertSame('0123', Text::normalize('０１２３'));
});

test('正規化: 半角カタカナは全角に（濁点も結合）', static function (): void {
    assertSame('ヤマダ タロウ', Text::normalize('ﾔﾏﾀﾞ ﾀﾛｳ'));
    assertSame('ガギ', Text::normalize('ｶﾞｷﾞ'));
});

test('正規化: 途中の空白は半角1つにまとめ、ゼロ幅文字は除く', static function (): void {
    assertSame('ジョン ポール', Text::normalize("ジョン　 ポール"));
    assertSame('山田', Text::normalize("山\u{200B}田\u{FEFF}"));
});

test('正規化: 漢字の字体・ひらがな/カタカナはそろえない（完全一致）', static function (): void {
    assertTrue(Text::normalize('髙橋') !== Text::normalize('高橋'));
    assertTrue(Text::normalize('山﨑') !== Text::normalize('山崎'));
    assertTrue(Text::normalize('さくら') !== Text::normalize('サクラ'));
});

test('正規化: 不正なUTF-8は空文字（未入力扱い）', static function (): void {
    assertSame('', Text::normalize("\xFF\xFE\xFD"));
});
