<?php

declare(strict_types=1);

use Qryno\SearchForm;

$valid = ['kyokai' => '130　東京', 'sei' => '山田', 'mei' => '太郎', 'company' => '代理店'];

test('入力チェック: 4項目そろえば検索用キーを作る', static function () use ($valid): void {
    $form = SearchForm::validate($valid, masters(), 50);
    assertTrue($form->isValid());
    assertSame(['kyokai' => '130', 'sei' => '山田', 'mei' => '太郎', 'company' => '代理店'], $form->keys);
});

test('入力チェック: 未入力・空白だけの項目はエラー（入力値は再表示用に保持）', static function () use ($valid): void {
    $form = SearchForm::validate(['sei' => '　'] + $valid, masters(), 50);
    assertTrue(!$form->isValid());
    assertTrue($form->hasError('sei'));
    assertTrue(!$form->hasError('mei'));
    assertSame('　', $form->values['sei']);
    assertSame([], $form->keys);
});

test('入力チェック: 選択肢にない所属協会・会社名（改ざん）はエラー', static function () use ($valid): void {
    $form = SearchForm::validate(['kyokai' => '999　どこか', 'company' => '存在しない生命'] + $valid, masters(), 50);
    assertTrue($form->hasError('kyokai'));
    assertTrue($form->hasError('company'));
});

test('入力チェック: 長すぎる氏名はエラー', static function () use ($valid): void {
    $form = SearchForm::validate(['mei' => str_repeat('あ', 51)] + $valid, masters(), 50);
    assertTrue($form->hasError('mei'));
});
