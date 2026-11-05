<?php

namespace App\Services\Reports;

use RuntimeException;
use XMLWriter;
use ZipArchive;

class HotelReportExportService
{
    private const COLUMNS = [
        'daily-summary' => ['date', 'total_rooms', 'available_rooms', 'occupied_rooms', 'check_ins', 'check_outs', 'room_revenue', 'food_drink_revenue', 'other_bill_revenue', 'total_revenue', 'total_payment_received', 'refunds', 'net_payment_received', 'outstanding_balance'],
        'room-occupancy' => ['booking_id', 'room', 'category', 'guest', 'check_in', 'check_out', 'duration_days', 'status'],
        'room-revenue' => ['booking_id', 'date', 'room', 'guest', 'stay_type', 'room_charge', 'extra_time', 'discount', 'tax', 'service_charge', 'total'],
        'guest-balances' => ['booking_id', 'room', 'guest', 'total_charges', 'deposit', 'paid', 'refund', 'outstanding', 'payment_status'],
        'food-sales' => ['date', 'order_no', 'room', 'menu_id', 'menu_item', 'category_id', 'category', 'qty', 'unit_price', 'amount', 'status'],
        'top-selling-items' => ['menu_id', 'item', 'qty_sold', 'revenue'],
        'other-bills' => ['date', 'order_no', 'room', 'guest', 'charge_type', 'description', 'qty', 'amount'],
        'inventory-balance' => ['item_id', 'item', 'category', 'unit', 'current_stock', 'minimum_stock', 'status', 'opening', 'stock_in', 'stock_out', 'adjustment'],
        'low-stock' => ['item_id', 'item', 'category', 'unit', 'current_stock', 'minimum_stock', 'status'],
        'depreciation' => ['asset_id', 'asset', 'original_value', 'month', 'monthly_depreciation', 'accumulated', 'book_value'],
    ];

    private const TEXT_COLUMNS = ['date', 'room', 'category', 'guest', 'check_in', 'check_out', 'status', 'stay_type', 'payment_status', 'order_no', 'menu_item', 'item', 'charge_type', 'description', 'unit', 'asset', 'month'];

    /** @param array{report: string, date_from: string, date_to: string, currency: string, rows: array<int, array<string, mixed>>, summary?: array<string, mixed>, notes?: array<int, string>} $result */
    public function write(array $result): void
    {
        $path = tempnam(sys_get_temp_dir(), 'hotel-report-');
        if ($path === false) {
            throw new RuntimeException('Unable to create the report workbook.');
        }

        try {
            $zip = new ZipArchive;
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Unable to open the report workbook.');
            }

            try {
                $parts = [
                    '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',
                    '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
                    'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/><sheet name="Details" sheetId="2" r:id="rId2"/></sheets></workbook>',
                    'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>',
                    'xl/worksheets/sheet1.xml' => $this->reportSheet($result),
                    'xl/worksheets/sheet2.xml' => $this->detailsSheet($result),
                ];
                foreach ($parts as $name => $contents) {
                    if (! $zip->addFromString($name, $contents)) {
                        throw new RuntimeException('Unable to write the report workbook.');
                    }
                }
            } finally {
                if (! $zip->close()) {
                    throw new RuntimeException('Unable to finish the report workbook.');
                }
            }

            if (readfile($path) === false) {
                throw new RuntimeException('Unable to download the report workbook.');
            }
        } finally {
            unlink($path);
        }
    }

    /** @param array{report: string, rows: array<int, array<string, mixed>>} $result */
    private function reportSheet(array $result): string
    {
        $columns = self::COLUMNS[$result['report']];
        if (count($result['rows']) > 1048575) {
            throw new RuntimeException('The report exceeds the Excel worksheet row limit.');
        }
        $xml = $this->worksheet();
        $xml->startElement('sheetViews');
        $xml->startElement('sheetView');
        $xml->writeAttribute('workbookViewId', '0');
        $xml->startElement('pane');
        $xml->writeAttribute('ySplit', '1');
        $xml->writeAttribute('topLeftCell', 'A2');
        $xml->writeAttribute('activePane', 'bottomLeft');
        $xml->writeAttribute('state', 'frozen');
        $xml->endElement();
        $xml->endElement();
        $xml->endElement();
        $xml->startElement('cols');
        $xml->startElement('col');
        $xml->writeAttribute('min', '1');
        $xml->writeAttribute('max', (string) count($columns));
        $xml->writeAttribute('width', '24');
        $xml->writeAttribute('customWidth', '1');
        $xml->endElement();
        $xml->endElement();
        $xml->startElement('sheetData');
        $xml->startElement('row');
        foreach ($columns as $column) {
            $this->cell($xml, ucwords(str_replace('_', ' ', $column)));
        }
        $xml->endElement();
        foreach ($result['rows'] as $row) {
            $xml->startElement('row');
            foreach ($columns as $column) {
                $this->cell($xml, $row[$column] ?? null, ! in_array($column, self::TEXT_COLUMNS, true));
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->startElement('autoFilter');
        $xml->writeAttribute('ref', 'A1:'.chr(64 + count($columns)).(count($result['rows']) + 1));
        $xml->endElement();
        $xml->endElement();

        return $xml->outputMemory();
    }

    /** @param array{report: string, date_from: string, date_to: string, currency: string, summary?: array<string, mixed>, notes?: array<int, string>} $result */
    private function detailsSheet(array $result): string
    {
        $xml = $this->worksheet();
        $xml->startElement('sheetData');
        foreach (['report', 'date_from', 'date_to', 'currency', 'summary', 'notes'] as $key) {
            $this->detailRows($xml, $key, $result[$key] ?? []);
        }
        $xml->endElement();
        $xml->endElement();

        return $xml->outputMemory();
    }

    /** @param array<array-key, mixed>|string|int|float|null $value */
    private function detailRows(XMLWriter $xml, string $label, array|string|int|float|null $value): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $this->detailRows($xml, $label.'.'.$key, $child);
            }

            return;
        }
        $xml->startElement('row');
        $this->cell($xml, ucwords(str_replace(['_', '.'], ' ', $label)));
        $this->cell($xml, $value);
        $xml->endElement();
    }

    private function worksheet(): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, 'worksheet', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        return $xml;
    }

    private function cell(XMLWriter $xml, string|int|float|null $value, bool $numeric = false): void
    {
        $xml->startElement('c');
        if ($value !== null) {
            if ($numeric && is_numeric($value)) {
                $xml->writeElement('v', (string) $value);
            } else {
                $xml->writeAttribute('t', 'inlineStr');
                $xml->startElement('is');
                $xml->startElement('t');
                $xml->writeAttribute('xml:space', 'preserve');
                $xml->text(preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $value) ?? '');
                $xml->endElement();
                $xml->endElement();
            }
        }
        $xml->endElement();
    }
}
