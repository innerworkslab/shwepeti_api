<?php

namespace App\Services\Cashbooks;

use App\Enums\CashbookTypeEnum;
use App\Models\Cashbook;
use App\Models\CashbookClosing;
use App\Models\CashbookTransaction;
use App\Services\CashbookTransactions\CashbookTransactionService;
use App\Services\Reports\HotelReportExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashbookExportService
{
    public function __construct(
        private readonly CashbookService $cashbookService,
        private readonly CashbookTransactionService $transactionService,
        private readonly HotelReportExportService $exporter,
    ) {}

    /** @param array{date_from?: ?string, date_to?: ?string} $filters */
    public function transactions(Cashbook $cashbook, array $filters, bool $forPos = false): StreamedResponse
    {
        abort_if($forPos && ! in_array($cashbook->type, [CashbookTypeEnum::PosCash, CashbookTypeEnum::PosBank], true), 404);

        $filters['cashbook_id'] = $cashbook->id;
        $rows = $this->transactionService->paginate($filters)->map(fn (CashbookTransaction $transaction): array => [
            'id' => $transaction->id,
            'cashbook_id' => $cashbook->id,
            'reference_no' => $transaction->reference_no,
            'transaction_date' => $transaction->transaction_date->format('Y-m-d H:i:s'),
            'transaction_type' => $transaction->transaction_type->value,
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
            'remark' => $transaction->remark,
            'created_by' => $transaction->created_by,
        ])->all();

        return $this->download('cashbook-transactions', $cashbook, $filters, $rows, $this->transactionService->summary($filters));
    }

    /** @param array{date_from?: ?string, date_to?: ?string} $filters */
    public function closings(Cashbook $cashbook, array $filters, bool $forPos = false): StreamedResponse
    {
        $rows = $this->cashbookService->closings($cashbook, $filters, $forPos)->map(fn (CashbookClosing $closing): array => [
            'id' => $closing->id,
            'cashbook_id' => $cashbook->id,
            'closing_date' => $closing->closing_date->toDateString(),
            'closed_at' => $closing->closed_at?->format('Y-m-d H:i:s'),
            'opening_balance' => $closing->opening_balance,
            'closing_balance' => $closing->closing_balance,
            'remark' => $closing->remark,
            'created_by' => $closing->created_by,
        ])->all();

        return $this->download('cashbook-closings', $cashbook, $filters, $rows);
    }

    /**
     * @param  array{date_from?: ?string, date_to?: ?string}  $filters
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $summary
     */
    private function download(string $report, Cashbook $cashbook, array $filters, array $rows, array $summary = []): StreamedResponse
    {
        $result = [
            'report' => $report,
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'currency' => 'MMK',
            'rows' => $rows,
            'summary' => ['cashbook_id' => $cashbook->id, 'cashbook_name' => $cashbook->name, ...$summary],
        ];

        return response()->streamDownload(
            fn () => $this->exporter->write($result),
            "{$report}_{$cashbook->id}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'private, no-store'],
        );
    }
}
