<?php

declare(strict_types=1);

namespace Qryno;

/**
 * 照合用の文字列正規化
 *
 * 入力方法の違いで一致しなくなるものだけをそろえます。
 *   - 全角英数字・記号 → 半角、半角カタカナ → 全角
 *   - 空白（全角空白・タブ等）→ 半角空白1つ、前後の空白は除去
 *   - 英字は大文字にそろえる
 * 漢字の字体（髙/高、﨑/崎 など）・ひらがな/カタカナはそろえません（完全一致）。
 */
final class Text
{
    public static function normalize(string $value): string
    {
        if ($value === '' || !mb_check_encoding($value, 'UTF-8')) {
            return '';
        }
        $value = mb_convert_kana($value, 'asKV', 'UTF-8');
        // ゼロ幅空白などの書式文字は削除し、制御文字は空白として扱う
        $value = (string) preg_replace('/\p{Cf}+/u', '', $value);
        $value = (string) preg_replace('/[\p{Cc}\p{Z}\s]+/u', ' ', $value);

        return strtoupper(trim($value, ' '));
    }
}
