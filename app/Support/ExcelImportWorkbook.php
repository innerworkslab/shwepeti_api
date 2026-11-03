<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class ExcelImportWorkbook
{
    /**
     * @param  list<string>  $requiredHeaders
     * @param  list<string>  $allowedHeaders
     * @return array<int, array<string, string|null>>
     */
    public function rows(UploadedFile $file, string $sheetName, array $requiredHeaders, array $allowedHeaders): array
    {
        $zip = new ZipArchive;
        if ($zip->open($file->getPathname()) !== true) {
            throw ValidationException::withMessages(['file' => 'Upload a valid Excel .xlsx workbook.']);
        }

        try {
            $size = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $size += $zip->statIndex($index)['size'];
                if ($size > 10 * 1024 * 1024) {
                    throw ValidationException::withMessages(['file' => 'The expanded workbook must be no larger than 10 MB.']);
                }
            }

            $sheetId = null;
            foreach ($this->xml($zip, 'xl/workbook.xml')->xpath('//*[local-name()="sheet"]') as $sheet) {
                if ((string) $sheet['name'] === $sheetName) {
                    $sheetId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                }
            }
            $sheetPath = null;
            foreach ($this->xml($zip, 'xl/_rels/workbook.xml.rels')->xpath('/*/*[local-name()="Relationship"]') as $relationship) {
                if ($sheetId !== null && (string) $relationship['Id'] === $sheetId && (string) $relationship['TargetMode'] !== 'External') {
                    $target = (string) $relationship['Target'];
                    $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.$target;
                }
            }
            if ($sheetPath === null) {
                throw ValidationException::withMessages(['file' => "The workbook must contain a {$sheetName} worksheet."]);
            }

            $strings = [];
            if ($zip->locateName('xl/sharedStrings.xml') !== false) {
                foreach ($this->xml($zip, 'xl/sharedStrings.xml')->xpath('/*/*[local-name()="si"]') as $string) {
                    $strings[] = $this->text($string);
                }
            }
            $rows = [];
            $headers = [];
            foreach ($this->xml($zip, $sheetPath)->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $number = (int) $row['r'];
                if ($number < 1 || $number > 1001 || isset($rows[$number])) {
                    throw ValidationException::withMessages(['file' => 'Use a header row and at most 1,000 data rows.']);
                }
                $cells = [];
                foreach ($row->xpath('./*[local-name()="c"]') as $cell) {
                    if ($cell->xpath('./*[local-name()="f"]') || (string) $cell['t'] === 'e') {
                        throw ValidationException::withMessages(["rows.{$number}" => 'Use plain values, not formulas or Excel errors.']);
                    }
                    preg_match('/^([A-Z]+)[0-9]+$/', (string) $cell['r'], $match);
                    $column = $match[1] ?? '';
                    $rawValue = (string) ($cell->xpath('./*[local-name()="v"]')[0] ?? '');
                    $value = match ((string) $cell['t']) {
                        's' => $strings[(int) $rawValue] ?? '',
                        'inlineStr' => $this->text($cell),
                        default => $rawValue,
                    };
                    $cells[$column] = trim($value) === '' ? null : trim($value);
                }
                if ($number === 1) {
                    $headers = array_filter($cells, fn (?string $value): bool => $value !== null);
                    if (count($headers) !== count(array_unique($headers)) || array_diff($requiredHeaders, $headers)) {
                        throw ValidationException::withMessages(['file' => 'Row 1 must have unique headers including '.implode(', ', $requiredHeaders).'.']);
                    }
                    if (array_diff($headers, $allowedHeaders)) {
                        throw ValidationException::withMessages(['file' => 'Unknown column headers. Use the headers from the sample workbook.']);
                    }

                    continue;
                }
                if (! array_filter($cells, fn (?string $value): bool => $value !== null)) {
                    continue;
                }
                foreach ($headers as $column => $header) {
                    $rows[$number][$header] = $cells[$column] ?? null;
                }
            }
            if ($headers === [] || $rows === []) {
                throw ValidationException::withMessages(['file' => "The {$sheetName} worksheet must contain headers and at least one data row."]);
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $path): SimpleXMLElement
    {
        $contents = $zip->getFromName($path);
        if ($contents === false || stripos($contents, '<!DOCTYPE') !== false || stripos($contents, '<!ENTITY') !== false) {
            throw ValidationException::withMessages(['file' => 'The workbook contains missing or unsupported XML.']);
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET);
            if ($xml === false) {
                throw ValidationException::withMessages(['file' => 'The workbook contains invalid XML.']);
            }

            return $xml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function text(SimpleXMLElement $element): string
    {
        return implode('', array_map(fn (SimpleXMLElement $text): string => (string) $text, $element->xpath('.//*[local-name()="t"]')));
    }
}
