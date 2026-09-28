<?php
/**
 * 問い合わせ先（本部事務局）
 *
 * @var array<string, mixed> $contact
 */
?>
    <p class="orgName"><?= implode(' <br class="br-sp">', array_map('h', (array) ($contact['org_name_lines'] ?? []))) ?></p>
    <p class="telNum">受付時間：<?= h($contact['hours'] ?? '') ?><br>
    電話：<?= implode('<br class="br-sp">・', array_map('h', (array) ($contact['tel'] ?? []))) ?></p>
