<?php

namespace App\Livewire\Products;

use App\Domains\Inventory\Services\ExportProductsService;
use App\Domains\Inventory\Services\ImportProductsService;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImportExportModal extends Component
{
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $csv = null;

    /** @var list<string> */
    public array $importErrors = [];

    public function export(ExportProductsService $export): StreamedResponse
    {
        Gate::authorize('viewAny', Product::class);

        $tenantId = (int) session('current_tenant_id');

        return $export->download($tenantId);
    }

    public function import(ImportProductsService $import): void
    {
        Gate::authorize('create', Product::class);

        $this->importErrors = [];

        $this->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ], [], ['csv' => __('CSV file')]);

        $tenantId = (int) session('current_tenant_id');

        $path = $this->csv?->getRealPath();
        if ($path === false || $path === null) {
            Flux::toast(variant: 'danger', text: __('Could not read the uploaded file.'));

            return;
        }

        try {
            $result = $import->execute($tenantId, $path);
        } catch (InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->csv = null;

        if ($result['created'] > 0) {
            $this->dispatch('products-imported');
        }

        Flux::toast(
            variant: $result['errors'] === [] ? 'success' : 'warning',
            text: __('Import finished: :created created, :skipped skipped.', [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
            ])
        );

        $this->importErrors = $result['errors'];

        if ($this->importErrors === []) {
            $this->modal('product-import-export')->close();
        }
    }

    public function clearImportErrors(): void
    {
        $this->importErrors = [];
    }

    public function render()
    {
        return view('livewire.products.product-import-export-modal');
    }
}
