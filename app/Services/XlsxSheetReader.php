<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Generator;
use RuntimeException;
use ZipArchive;

class XlsxSheetReader
{
    private const SPREADSHEET_NAMESPACE = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const RELATIONSHIP_NAMESPACE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @return Generator<int, array{row: int, cells: array<string, string>}> */
    public function rows(string $path, string $sheetName): Generator
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException("Unable to open workbook: {$path}");
        }

        try {
            $sharedStrings = $this->sharedStrings($archive);
            $sheetPath = $this->sheetPath($archive, $sheetName);
            $sheet = $this->document($archive, $sheetPath);
            $xpath = $this->xpath($sheet);

            foreach ($xpath->query('/m:worksheet/m:sheetData/m:row') as $row) {
                $cells = [];

                foreach ($xpath->query('./m:c', $row) as $cell) {
                    $column = preg_replace('/\d+/', '', $cell->getAttribute('r'));
                    $value = $xpath->query('./m:v', $cell)->item(0)?->textContent;

                    if ($cell->getAttribute('t') === 's' && $value !== null) {
                        $value = $sharedStrings[(int) $value] ?? null;
                    } elseif ($cell->getAttribute('t') === 'inlineStr') {
                        $value = $this->textNodes($xpath, './/m:t', $cell);
                    }

                    if ($value !== null) {
                        $cells[$column] = $value;
                    }
                }

                yield ['row' => (int) $row->getAttribute('r'), 'cells' => $cells];
            }
        } finally {
            $archive->close();
        }
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $archive): array
    {
        if ($archive->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xpath = $this->xpath($this->document($archive, 'xl/sharedStrings.xml'));
        $strings = [];

        foreach ($xpath->query('/m:sst/m:si') as $item) {
            $strings[] = $this->textNodes($xpath, './/m:t', $item);
        }

        return $strings;
    }

    private function sheetPath(ZipArchive $archive, string $sheetName): string
    {
        $workbook = $this->xpath($this->document($archive, 'xl/workbook.xml'));
        $sheet = null;

        foreach ($workbook->query('/m:workbook/m:sheets/m:sheet') as $candidate) {
            if ($candidate->getAttribute('name') === $sheetName) {
                $sheet = $candidate;

                break;
            }
        }

        if (! $sheet instanceof DOMElement || $sheet->getAttribute('name') !== $sheetName) {
            throw new RuntimeException("The {$sheetName} sheet is missing from the workbook.");
        }

        $relationshipId = $sheet->getAttributeNS(self::RELATIONSHIP_NAMESPACE, 'id');
        $relationships = $this->document($archive, 'xl/_rels/workbook.xml.rels');

        foreach ($relationships->getElementsByTagName('Relationship') as $relationship) {
            if ($relationship->getAttribute('Id') === $relationshipId) {
                $target = $relationship->getAttribute('Target');

                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
            }
        }

        throw new RuntimeException("The {$sheetName} sheet could not be located.");
    }

    private function document(ZipArchive $archive, string $path): DOMDocument
    {
        $content = $archive->getFromName($path);

        if ($content === false) {
            throw new RuntimeException("Missing workbook component: {$path}");
        }

        $document = new DOMDocument;

        if (! @$document->loadXML($content, LIBXML_NONET)) {
            throw new RuntimeException("Invalid workbook component: {$path}");
        }

        return $document;
    }

    private function xpath(DOMDocument $document): DOMXPath
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('m', self::SPREADSHEET_NAMESPACE);

        return $xpath;
    }

    private function textNodes(DOMXPath $xpath, string $expression, DOMElement $element): string
    {
        $text = '';

        foreach ($xpath->query($expression, $element) as $node) {
            $text .= $node->textContent;
        }

        return $text;
    }
}
