<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Str;

trait HasAutoNumber
{
    /**
     * Fill the code column on creating, only when it is still empty.
     * Extension rows copy the original code, so they are left alone.
     */
    public static function bootHasAutoNumber(): void
    {
        static::creating(function ($model): void {
            $column = $model->getAutoNumberColumn();

            if (empty($model->{$column})) {
                $model->{$column} = $model->getAutoNumber();
            }
        });
    }

    /**
     * The column that holds the generated code.
     */
    public function getAutoNumberColumn(): string
    {
        return 'code';
    }

    /**
     * The length of the running number (without the prefix). Reads $auto_number_length, default 3.
     */
    public function autoNumberLength(): int
    {
        if (empty($this->auto_number_length)) {
            return 3;
        }

        return (int) $this->auto_number_length;
    }

    /**
     * The fixed text in front of the running number. Reads $auto_number_prefix, default ''.
     *
     * Models override this when the prefix depends on the instance: a garage's
     * prefix depends on its type (S, C or E), and a rental's prefix is
     * `RNT-` plus the date `YYYYMMDD` plus `-`.
     *
     * @param  array<string, mixed>  $data
     */
    public function autoNumberPrefix(array $data = []): string
    {
        if (empty($this->auto_number_prefix)) {
            return '';
        }

        return (string) $this->auto_number_prefix;
    }

    /**
     * Compute the next code: prefix plus the highest existing number plus one, left-padded with zeros.
     * It does not cap the number, so a longer number simply gives a longer code. A duplicate from two
     * simultaneous creates is rejected by the unique index on the code, so the caller may retry the insert.
     *
     * @param  array<string, mixed>  $data
     */
    public function getAutoNumber(array $data = []): string
    {
        return $this->buildNextAutoNumber($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function buildNextAutoNumber(array $data): string
    {
        $column = $this->getAutoNumberColumn();
        $prefix = $this->autoNumberPrefix($data);
        $length = $this->autoNumberLength();
        $codeLength = mb_strlen($prefix) + $length;

        $query = static::query();
        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);
        $escapedPrefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix);

        $lastCode = $query
            ->whereNotNull($column)
            ->where($column, 'like', $escapedPrefix.'%')
            ->whereRaw('LENGTH('.$wrappedColumn.') = ?', [$codeLength])
            ->orderByDesc($column)
            ->value($column);

        $lastNumber = $lastCode === null ? 0 : (int) Str::after((string) $lastCode, $prefix);

        return $prefix.mb_str_pad((string) ($lastNumber + 1), $length, '0', STR_PAD_LEFT);
    }
}
