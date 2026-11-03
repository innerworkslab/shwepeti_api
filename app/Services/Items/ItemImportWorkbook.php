<?php

namespace App\Services\Items;

use App\Support\ExcelImportWorkbook;
use Illuminate\Http\UploadedFile;

class ItemImportWorkbook
{
    public function __construct(private readonly ExcelImportWorkbook $workbook) {}

    /** @return array<int, array<string, string|null>> */
    public function rows(UploadedFile $file): array
    {
        return $this->workbook->rows($file, 'Items',
            ['code', 'name', 'category_code', 'stock_unit'],
            ['code', 'name', 'category_code', 'stock_unit', 'sku', 'barcode', 'price', 'min_stock', 'is_active', 'from_unit', 'to_unit', 'conversion_factor', 'conversion_is_active', 'description', 'image_url'],
        );
    }
}
