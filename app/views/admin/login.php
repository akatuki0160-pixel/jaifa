<?php
/**
 * 管理画面ログイン
 *
 * @var string $error
 * @var string $token
 */
?>
  <div class="infoArea">
<?php if ($error !== ''): ?>
    <p class="adminMsg adminMsgError"><?= h($error) ?></p>
<?php endif; ?>
    <p>管理者パスワードを入力してください。</p>
    <form action="admin.php" method="post">
      <input type="hidden" name="token" value="<?= h($token) ?>">
      <input type="hidden" name="action" value="login">
      <dl>
        <dt><label for="password">パスワード</label></dt>
        <dd><input type="password" name="password" id="password" class="textField w40per" autocomplete="current-password" required autofocus></dd>
      </dl>
      <div class="form2025BtnArea">
        <input type="submit" value="ログイン" class="formBtn btnSearch">
      </div>
    </form>
  </div>
