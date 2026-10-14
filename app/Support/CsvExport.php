<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Spreadsheet-safe CSV download: UTF-8 BOM, ';' separator, formula injection guard on every cell. */
final class CsvExport
{
    /**
     * @param  list<string>  $headers
     * @param  callable(callable(array<int,mixed>):void):void  $rows  receives a writer to call once per row
     */
    public static function download(string $filename, array $headers, callable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                return;
            }
            fwrite($stream, "\xEF\xBB\xBF");
            $write = function (array $cells) use ($stream): void {
                fputcsv($stream, array_map(self::cell(...), $cells), ';', '"', '');
            };
            $write($headers);
            $rows($write);
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public static function cell(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
