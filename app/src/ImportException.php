<?php

declare(strict_types=1);

namespace Qryno;

use RuntimeException;

/** CSV取込を中止する理由（画面にそのまま表示できる文言） */
final class ImportException extends RuntimeException
{
}
