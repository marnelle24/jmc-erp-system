<?php

namespace App\Domains\Inventory\Services;

use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class ImportProductsService
{
    public const MAX_ROWS = 2000;

    public function __construct(
        private CreateProductService $createProduct,
    ) {}

    /**
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function execute(int $tenantId, string $absolutePath): array
    {
        if (! is_readable($absolutePath)) {
            throw new InvalidArgumentException(__('The uploaded file could not be read.'));
        }

        $handle = fopen($absolutePath, 'r');
        if ($handle === false) {
            throw new InvalidArgumentException(__('The uploaded file could not be read.'));
        }

        try {
            $first = fgetcsv($handle);
            if ($first === false || $first === [null] || $this->rowIsEmpty($first)) {
                return [
                    'created' => 0,
                    'skipped' => 0,
                    'errors' => [__('The file is empty or invalid.')],
                ];
            }

            $map = $this->mapFromHeaderRow($first);
            $lineNumber = 2;

            if ($map === null) {
                rewind($handle);
                $map = [
                    'name' => 0,
                    'sku' => 1,
                    'description' => 2,
                ];
                $lineNumber = 1;
            }

            $created = 0;
            $skipped = 0;
            /** @var list<string> $errors */
            $errors = [];
            $seenSkus = [];
            $nonEmptyRowsSeen = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->rowIsEmpty($row)) {
                    $lineNumber++;

                    continue;
                }

                $nonEmptyRowsSeen++;
                if ($nonEmptyRowsSeen > self::MAX_ROWS) {
                    $errors[] = __('Import stopped: more than :max data rows.', ['max' => self::MAX_ROWS]);

                    break;
                }

                $name = $this->pickCell($row, $map['name']);
                $skuRaw = $map['sku'] !== null ? $this->pickCell($row, $map['sku']) : '';
                $description = $map['description'] !== null ? $this->pickCell($row, $map['description']) : '';

                if ($name === '') {
                    $errors[] = __('Line :line: Name is required.', ['line' => $lineNumber]);
                    $lineNumber++;

                    continue;
                }

                $sku = $skuRaw === '' ? null : $skuRaw;

                if ($sku !== null) {
                    $skuKey = mb_strtolower($sku);
                    if (isset($seenSkus[$skuKey])) {
                        $errors[] = __('Line :line: Duplicate SKU in file.', ['line' => $lineNumber]);
                        $skipped++;
                        $lineNumber++;

                        continue;
                    }
                    $seenSkus[$skuKey] = true;

                    if (Product::query()
                        ->where('tenant_id', $tenantId)
                        ->where('sku', $sku)
                        ->exists()) {
                        $skipped++;
                        $lineNumber++;

                        continue;
                    }
                }

                $validator = Validator::make(
                    [
                        'name' => $name,
                        'sku' => $sku,
                        'description' => $description === '' ? null : $description,
                    ],
                    (new StoreProductRequest)->rules()
                );

                if ($validator->fails()) {
                    foreach ($validator->errors()->all() as $message) {
                        $errors[] = __('Line :line: :message', ['line' => $lineNumber, 'message' => $message]);
                    }
                    $skipped++;
                    $lineNumber++;

                    continue;
                }

                $this->createProduct->execute($tenantId, $validator->validated());
                $created++;
                $lineNumber++;
            }

            return [
                'created' => $created,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array{name: int, sku: int|null, description: int|null}|null
     */
    private function mapFromHeaderRow(array $row): ?array
    {
        $lower = array_map(fn ($c) => mb_strtolower(trim((string) $c)), $row);
        $nameIdx = array_search('name', $lower, true);
        if ($nameIdx === false) {
            return null;
        }

        $skuIdx = array_search('sku', $lower, true);
        $descIdx = array_search('description', $lower, true);

        return [
            'name' => $nameIdx,
            'sku' => $skuIdx === false ? null : $skuIdx,
            'description' => $descIdx === false ? null : $descIdx,
        ];
    }

    /**
     * @param  list<string|null>|false  $row
     */
    private function rowIsEmpty(array|false $row): bool
    {
        if ($row === false) {
            return true;
        }

        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function pickCell(array $row, int $index): string
    {
        return trim((string) ($row[$index] ?? ''));
    }
}
