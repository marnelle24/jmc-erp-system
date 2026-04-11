<?php

namespace App\Domains\Sales\Services;

use App\Enums\SalesInvoiceStatus;
use App\Enums\SalesOrderStatus;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class IssueSalesInvoiceService
{
    /**
     * @param  list<array{sales_order_line_id: int, quantity_invoiced: string, unit_price?: string|null}>  $lines
     */
    public function execute(
        int $tenantId,
        int $salesOrderId,
        array $lines,
        CarbonInterface|string $issuedAt,
        ?string $customerDocumentReference = null,
        ?string $notes = null,
    ): SalesInvoice {
        return DB::transaction(function () use ($tenantId, $salesOrderId, $lines, $issuedAt, $customerDocumentReference, $notes): SalesInvoice {
            $salesOrder = SalesOrder::query()
                ->where('tenant_id', $tenantId)
                ->with('lines')
                ->whereKey($salesOrderId)
                ->firstOrFail();

            if ($salesOrder->status === SalesOrderStatus::Cancelled) {
                throw new InvalidArgumentException(__('Cannot invoice a cancelled sales order.'));
            }

            $normalized = $this->normalizeLines($lines);
            if ($normalized === []) {
                throw new InvalidArgumentException(__('Enter at least one positive quantity to invoice.'));
            }

            $this->assertLinesBelongToOrder($salesOrder, array_keys($normalized));

            foreach ($normalized as $salesOrderLineId => $quantityInvoiced) {
                $soLine = $salesOrder->lines->firstWhere('id', $salesOrderLineId);
                if (! $soLine instanceof SalesOrderLine) {
                    continue;
                }

                $shipped = $soLine->totalShippedQuantity();
                $alreadyInvoiced = $soLine->totalInvoicedQuantity();
                $nextTotal = bcadd($alreadyInvoiced, $quantityInvoiced, 4);

                if (bccomp($nextTotal, $shipped, 4) === 1) {
                    throw new InvalidArgumentException(
                        __('Cannot invoice more than shipped for :product.', ['product' => '#'.$soLine->product_id])
                    );
                }
            }

            $issuedAtCarbon = $issuedAt instanceof CarbonInterface
                ? $issuedAt
                : Carbon::parse((string) $issuedAt);

            $invoice = SalesInvoice::query()->create([
                'tenant_id' => $tenantId,
                'sales_order_id' => $salesOrder->id,
                'status' => SalesInvoiceStatus::Issued,
                'issued_at' => $issuedAtCarbon->toDateTimeString(),
                'customer_document_reference' => $customerDocumentReference,
                'notes' => $notes,
            ]);

            foreach ($normalized as $salesOrderLineId => $quantityInvoiced) {
                $soLine = $salesOrder->lines->firstWhere('id', $salesOrderLineId);
                if (! $soLine instanceof SalesOrderLine) {
                    continue;
                }

                $unitPrice = $this->resolveUnitPrice($lines, $salesOrderLineId, $soLine);

                SalesInvoiceLine::query()->create([
                    'sales_invoice_id' => $invoice->id,
                    'sales_order_line_id' => $salesOrderLineId,
                    'quantity_invoiced' => $quantityInvoiced,
                    'unit_price' => $unitPrice,
                ]);
            }

            return $invoice->load('lines');
        });
    }

    /**
     * @param  list<array{sales_order_line_id: int, quantity_invoiced: string, unit_price?: string|null}>  $rawLines
     */
    private function resolveUnitPrice(array $rawLines, int $salesOrderLineId, SalesOrderLine $soLine): ?string
    {
        foreach ($rawLines as $row) {
            if ((int) $row['sales_order_line_id'] !== $salesOrderLineId) {
                continue;
            }
            if (isset($row['unit_price']) && $row['unit_price'] !== '' && $row['unit_price'] !== null) {
                return (string) $row['unit_price'];
            }
        }

        return $soLine->unit_price !== null ? (string) $soLine->unit_price : null;
    }

    /**
     * @param  list<array{sales_order_line_id: int, quantity_invoiced: string}>  $lines
     * @return array<int, string> keyed by sales_order_line_id
     */
    private function normalizeLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $row) {
            $qty = isset($row['quantity_invoiced']) ? (string) $row['quantity_invoiced'] : '0';
            if (bccomp($qty, '0', 4) !== 1) {
                continue;
            }
            $lineId = (int) $row['sales_order_line_id'];
            if ($lineId < 1) {
                continue;
            }
            $out[$lineId] = isset($out[$lineId]) ? bcadd($out[$lineId], $qty, 4) : $qty;
        }

        return $out;
    }

    /**
     * @param  list<int>  $salesOrderLineIds
     */
    private function assertLinesBelongToOrder(SalesOrder $salesOrder, array $salesOrderLineIds): void
    {
        $valid = $salesOrder->lines->pluck('id')->all();
        foreach ($salesOrderLineIds as $id) {
            if (! in_array($id, $valid, true)) {
                throw new InvalidArgumentException(__('Invalid sales order line for this order.'));
            }
        }
    }
}
