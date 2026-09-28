<?php
/**
 * 管理画面トップ: 取込結果・現在のデータ・CSV取込・取込履歴
 *
 * @var array<string, string> $meta
 * @var bool $unreadable
 * @var bool $mastersChanged
 * @var Qryno\ImportReport|null $report
 * @var string $error
 * @var list<array<string, mixed>> $history
 * @var array<string, list<string>> $columns
 * @var string $uploadLimit
 * @var bool $uploadLimitLow
 * @var string $token
 */

use Qryno\ImportReport;

$number = static fn (int|string $value): string => number_format((int) $value);
$statusLabels = [
    ImportReport::APPLIED => '取込完了',
    ImportReport::VALIDATED => '検証のみ',
    ImportReport::FAILED => '中止',
];
// 見出しが項目名と違うときだけ「項目名＝見出し」と表示する
$columnNames = static fn (array $columns): array => array_map(
    static fn (string $label, string $header): string => $label === $header ? $label : $label . '＝「' . $header . '」',
    array_keys($columns),
    $columns
);
$fieldLabels = ['member_no' => '会員番号', 'kyokai' => '所属協会', 'sei' => '姓', 'mei' => '名', 'name' => '氏名（姓・名の列がない場合）', 'company' => '会社名'];
?>
<?php if ($error !== ''): ?>
  <p class="adminMsg adminMsgError"><?= h($error) ?></p>
<?php endif; ?>

<?php if ($report !== null): ?>
  <section class="adminSection adminReport adminReport-<?= h($report->status) ?>">
    <h2>
<?php if ($report->status === ImportReport::APPLIED): ?>
      取込が完了しました
<?php elseif ($report->status === ImportReport::VALIDATED): ?>
      検証が完了しました（会員データは置き換えていません）
<?php else: ?>
      取込を中止しました（会員データは以前のままです）
<?php endif; ?>
    </h2>
<?php if ($report->failure !== null): ?>
    <p class="adminMsg adminMsgError"><?= h($report->failure) ?></p>
<?php endif; ?>
    <table class="adminTable adminKV">
      <tr><th>ファイル</th><td><?= h($report->sourceName) ?><?= $report->fileSize > 0 ? '（' . h($number($report->fileSize)) . 'バイト）' : '' ?></td></tr>
<?php if ($report->encoding !== ''): ?>
      <tr><th>文字コード／区切り</th><td><?= h($report->encoding) ?>／<?= h($report->delimiter) ?></td></tr>
<?php endif; ?>
<?php if ($report->headerRow > 0): ?>
      <tr><th>見出し行</th><td><?= h($report->headerRow) ?>行目（<?= h(implode('、', $columnNames($report->columns))) ?>）</td></tr>
      <tr><th>データ行数</th><td><?= h($number($report->dataRows)) ?>行</td></tr>
      <tr><th>取込件数</th><td><strong><?= h($number($report->importedRows)) ?>件</strong><?= $report->status !== ImportReport::APPLIED ? '（取り込める件数）' : '' ?></td></tr>
      <tr><th>エラー行</th><td><?= h($number($report->errorRows)) ?>行<?= $report->errorRows > 0 ? '（これらの行は取り込んでいません）' : '' ?></td></tr>
<?php endif; ?>
      <tr><th>取込前の件数</th><td><?= h($number($report->previousCount)) ?>件</td></tr>
      <tr><th>処理時間</th><td><?= h($report->seconds) ?>秒（<?= h($report->finishedAt) ?>）</td></tr>
    </table>

<?php foreach ($report->warnings as $warning): ?>
    <div class="adminMsg adminMsgWarning">
      <p><?= h($warning['message']) ?></p>
<?php if ($warning['items'] !== []): ?>
      <ul>
<?php foreach ($warning['items'] as $item): ?>
        <li><?= h($item) ?></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </div>
<?php endforeach; ?>

<?php if ($report->errors !== []): ?>
    <h3>エラーの内容<?= $report->errorRows > count($report->errors) ? '（先頭' . h($number(count($report->errors))) . '件を表示）' : '' ?></h3>
    <table class="adminTable adminErrors">
      <tr><th>行</th><th>内容</th></tr>
<?php foreach ($report->errors as $row): ?>
      <tr><td><?= h($row['row']) ?></td><td><?= h($row['message']) ?></td></tr>
<?php endforeach; ?>
    </table>
<?php endif; ?>
  </section>
<?php endif; ?>

  <section class="adminSection">
    <h2>現在の会員データ</h2>
<?php if ($unreadable): ?>
    <p class="adminMsg adminMsgError">会員データ（data/members.sqlite）を読み込めません。ファイルが壊れている可能性があります。CSVを取り込み直してください。</p>
<?php elseif ($meta === []): ?>
    <p class="adminMsg adminMsgError">会員データがまだ取り込まれていません。検索画面は「ご利用いただけません」と表示されます。</p>
<?php else: ?>
    <table class="adminTable adminKV">
      <tr><th>登録件数</th><td><strong><?= h($number($meta['row_count'] ?? 0)) ?>件</strong></td></tr>
      <tr><th>取込日時</th><td><?= h($meta['imported_at'] ?? '') ?></td></tr>
      <tr><th>元ファイル</th><td><?= h($meta['source_name'] ?? '') ?>（<?= h($meta['encoding'] ?? '') ?>）</td></tr>
      <tr><th>SHA-256</th><td class="adminHash"><?= h($meta['source_sha256'] ?? '') ?></td></tr>
    </table>
<?php if ($mastersChanged): ?>
    <p class="adminMsg adminMsgWarning">取込後にマスタ（config/masters.php）が変更されています。検索結果に反映するため、CSVを取り込み直してください。</p>
<?php endif; ?>
<?php endif; ?>
  </section>

  <section class="adminSection">
    <h2>CSVを取り込む</h2>
    <p class="notice">取り込んだCSVの内容で会員データをすべて置き換えます。エラーのある行は取り込みません。</p>
    <form action="admin.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <input type="hidden" name="action" value="import">
      <dl>
        <dt><label for="csv">CSVファイル</label></dt>
        <dd><input type="file" name="csv" id="csv" accept=".csv,.tsv,.txt,text/csv" required></dd>
      </dl>
      <dl>
        <dt>オプション</dt>
        <dd>
          <label class="adminCheck"><input type="checkbox" name="dry_run" value="1"> 検証のみ（会員データは置き換えない）</label>
          <label class="adminCheck"><input type="checkbox" name="force" value="1"> 件数が大幅に減っても置き換える</label>
        </dd>
      </dl>
      <div class="form2025BtnArea">
        <input type="submit" value="取り込む" class="formBtn btnSearch">
      </div>
    </form>
    <p class="notice">アップロードできるファイルサイズ: <?= h($uploadLimit) ?>まで（サーバー設定 upload_max_filesize、post_max_size とシステム設定の小さい方）</p>
<?php if ($uploadLimitLow): ?>
    <p class="adminMsg adminMsgWarning">アップロード上限が小さいため、4万件規模のCSV（数MB）は取り込めない可能性があります。PHPの upload_max_filesize と post_max_size を大きくするか、コマンド（php bin/import.php）で取り込んでください。</p>
<?php endif; ?>
  </section>

  <section class="adminSection">
    <h2>CSVの形式</h2>
    <ul class="adminList">
      <li>1行目に見出し、2行目以降に会員1人を1行で入れます。<a href="admin.php?action=template">見出しだけのテンプレートをダウンロード</a></li>
      <li>文字コードは Shift_JIS（Excelの「CSV（コンマ区切り）」）・UTF-8 のどちらでも読み込めます。タブ区切りも可。</li>
      <li>所属協会は「130　東京」「130」「東京」のどの書き方でも構いません。</li>
      <li>会社名は検索画面の選択肢と同じ表記にしてください（全角・半角の違いは問いません）。</li>
      <li>Excelで編集すると会員番号の先頭の「0」が消えることがあります。会員番号の列は「文字列」にしてください。</li>
    </ul>
    <table class="adminTable">
      <tr><th>項目</th><th>認識する見出し</th></tr>
<?php foreach ($columns as $field => $aliases): ?>
      <tr><td><?= h($fieldLabels[$field] ?? $field) ?></td><td><?= h(implode('、', $aliases)) ?></td></tr>
<?php endforeach; ?>
    </table>
  </section>

<?php if ($history !== []): ?>
  <section class="adminSection">
    <h2>取込履歴（直近<?= count($history) ?>件）</h2>
    <table class="adminTable">
      <tr><th>日時</th><th>結果</th><th>ファイル</th><th>取込件数</th><th>エラー行</th><th>実行者</th></tr>
<?php foreach ($history as $entry): ?>
      <tr>
        <td><?= h($entry['at'] ?? '') ?></td>
        <td><?= h($statusLabels[$entry['status'] ?? ''] ?? '') ?></td>
        <td><?= h($entry['source_name'] ?? '') ?></td>
        <td class="adminNum"><?= h($number($entry['imported_rows'] ?? 0)) ?></td>
        <td class="adminNum"><?= h($number($entry['error_rows'] ?? 0)) ?></td>
        <td><?= h($entry['operator'] ?? '') ?></td>
      </tr>
<?php endforeach; ?>
    </table>
  </section>
<?php endif; ?>
