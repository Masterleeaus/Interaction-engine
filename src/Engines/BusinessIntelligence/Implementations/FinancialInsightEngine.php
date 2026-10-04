<?php

declare(strict_types=1);

namespace TitanZero\Engines\BusinessIntelligence\Implementations;

use TitanZero\Engines\BusinessIntelligence\Contracts\FinancialInsightEngineInterface;
use Illuminate\Support\Facades\DB;

class FinancialInsightEngine implements FinancialInsightEngineInterface
{
    public function __construct(private readonly array $schema = []) {}

    public function getRevenueForecast(): float
    {
        $invoices = $this->table('invoices');
        $totalColumn = $this->column('invoice_total', 'total');
        $end = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $start = $end->modify('-6 months');
        try {
            $revenue = (float) DB::table($invoices)
                ->whereBetween('created_at', [$start->format(DATE_ATOM), $end->format(DATE_ATOM)])
                ->whereIn('status', ['paid', 'partially_paid', 'partially-paid'])
                ->sum($totalColumn);
        } catch (\Throwable $error) {
            throw new \RuntimeException('Revenue forecast is unavailable until the host invoice table and columns are mapped.', 0, $error);
        }
        return round(($revenue / 6) * 1.0, 2);
    }

    public function getCashFlow(): array
    {
        [$start, $end] = $this->currentMonthWindow();
        try {
            $in = (float) DB::table($this->table('invoices'))
                ->whereBetween('created_at', [$start, $end])
                ->whereIn('status', ['paid', 'partially_paid', 'partially-paid'])
                ->sum($this->column('invoice_total', 'total'));
            $out = (float) DB::table($this->table('expenses'))
                ->whereBetween('created_at', [$start, $end])
                ->sum($this->column('expense_total', 'amount'));
        } catch (\Throwable $error) {
            return [
                'available' => false,
                'in' => null,
                'out' => null,
                'net' => null,
                'reason' => 'Host invoice/expense schema unavailable: ' . $error->getMessage(),
            ];
        }
        return ['available' => true, 'in' => $in, 'out' => $out, 'net' => round($in - $out, 2), 'period_start' => $start, 'period_end' => $end];
    }

    public function getProfitabilityMetrics(): array
    {
        [$start, $end] = $this->currentMonthWindow();
        try {
            $revenue = (float) DB::table($this->table('invoices'))
                ->whereBetween('created_at', [$start, $end])
                ->whereIn('status', ['paid', 'partially_paid', 'partially-paid'])
                ->sum($this->column('invoice_total', 'total'));
            $expenses = (float) DB::table($this->table('expenses'))
                ->whereBetween('created_at', [$start, $end])
                ->sum($this->column('expense_total', 'amount'));
        } catch (\Throwable $error) {
            return ['available' => false, 'gross_margin' => null, 'net_margin' => null, 'reason' => 'Host financial schema unavailable: ' . $error->getMessage()];
        }
        $margin = $revenue <= 0 ? null : round(($revenue - $expenses) / $revenue, 4);
        return [
            'available' => true,
            'revenue' => $revenue,
            'expenses' => $expenses,
            'gross_margin' => null,
            'net_margin' => $margin,
            'net_margin_is_proxy' => true,
            'note' => 'Net margin uses all recorded expenses as a proxy. Gross margin is unavailable without cost-of-goods data.',
            'period_start' => $start,
            'period_end' => $end,
        ];
    }

    private function currentMonthWindow(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        return [$start->format(DATE_ATOM), $now->format(DATE_ATOM)];
    }

    private function table(string $key): string
    {
        $table = (string) ($this->schema[$key] ?? $key);
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) !== 1) {
            throw new \InvalidArgumentException("Invalid configured table name '{$table}'.");
        }
        return $table;
    }

    private function column(string $key, string $default): string
    {
        $column = (string) ($this->schema[$key] ?? $default);
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column) !== 1) {
            throw new \InvalidArgumentException("Invalid configured column name '{$column}'.");
        }
        return $column;
    }
}
