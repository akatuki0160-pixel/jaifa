<?php
/**
 * 管理画面が無効（管理者パスワード未設定）
 */
?>
  <div class="infoArea">
    <p>管理画面は無効になっています。</p>
    <p class="notice">使用するには、サーバー上で次のコマンドを実行してパスワードのハッシュを作り、
    <code>config/config.local.php</code> の <code>admin.password_hash</code> に設定してください。</p>
    <pre class="adminCode">php bin/hash-password.php</pre>
    <p class="notice">管理画面を使わずに、コマンドで取り込むこともできます。</p>
    <pre class="adminCode">php bin/import.php 会員データ.csv</pre>
  </div>
