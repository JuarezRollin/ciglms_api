<?php

namespace App\Services;

use Generator;
use RuntimeException;
use SplFileObject;

final class PsgcSqlDumpReader
{
    /**
     * @param  list<string>  $columns
     * @param  list<string>  $integerColumns
     * @return Generator<int, array<string, int|string|null>>
     */
    public function rows(string $path, string $table, array $columns, array $integerColumns): Generator
    {
        if (! is_readable($path)) {
            throw new RuntimeException("PSGC SQL file is not readable: {$path}");
        }

        $file = new SplFileObject($path, 'rb');
        $lineNumber = 0;

        foreach ($file as $line) {
            $lineNumber++;

            if (! is_string($line)) {
                continue;
            }

            $statement = trim($line);

            if (! str_starts_with($statement, 'INSERT INTO')) {
                continue;
            }

            $pattern = '/^INSERT INTO `'.preg_quote($table, '/').'` VALUES \((.*)\);$/';

            if (preg_match($pattern, $statement, $matches) !== 1) {
                throw new RuntimeException("Invalid PSGC INSERT statement in {$path} on line {$lineNumber}.");
            }

            $values = str_getcsv($matches[1], ',', "'", '\\');

            if (count($values) !== count($columns)) {
                throw new RuntimeException(
                    'Expected '.count($columns)." columns in {$path} on line {$lineNumber}; found ".count($values).'.',
                );
            }

            $row = [];

            foreach ($columns as $index => $column) {
                $value = $values[$index];
                $trimmedValue = trim($value);

                if ($trimmedValue === 'NULL') {
                    $row[$column] = null;

                    continue;
                }

                if (in_array($column, $integerColumns, true)) {
                    if (preg_match('/^\d+$/', $trimmedValue) !== 1) {
                        throw new RuntimeException(
                            "Expected an integer for {$column} in {$path} on line {$lineNumber}.",
                        );
                    }

                    $row[$column] = (int) $trimmedValue;

                    continue;
                }

                $row[$column] = str_replace("\\'", "'", $value);
            }

            yield $row;
        }
    }
}
