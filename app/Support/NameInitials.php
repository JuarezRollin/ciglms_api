<?php

namespace App\Support;

class NameInitials
{
    public static function fromName(string $name): string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $name, $parts);

        return mb_substr(implode('', array_map(
            fn (string $part): string => mb_strtoupper(mb_substr($part, 0, 1)),
            $parts[0],
        )), 0, 30);
    }
}
