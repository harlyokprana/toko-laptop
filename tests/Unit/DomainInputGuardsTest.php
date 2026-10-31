<?php

namespace Tests\Unit;

use App\Services\CashLedgerService;
use App\Services\StockService;
use App\Support\CsvSanitizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DomainInputGuardsTest extends TestCase
{
    public function test_stock_mutations_reject_zero_and_negative_quantities(): void
    {
        $service = new StockService();

        foreach ([0, -1] as $quantity) {
            $rejected = false;
            try {
                $service->increase(1, $quantity, 'ADJUSTMENT', 1, 'test');
            } catch (InvalidArgumentException) {
                $rejected = true;
            }
            $this->assertTrue($rejected, 'Expected invalid stock quantity to be rejected.');
        }
    }

    public function test_cash_mutations_reject_non_positive_or_non_finite_amounts(): void
    {
        $service = new CashLedgerService();

        foreach ([0.0, -1.0, INF, NAN] as $amount) {
            foreach (['receipt', 'disbursement'] as $operation) {
                $rejected = false;
                try {
                    $service->{$operation}($amount, 1, 'TEST', null, 'test');
                } catch (InvalidArgumentException) {
                    $rejected = true;
                }
                $this->assertTrue($rejected, 'Expected invalid cash amount to be rejected.');
            }
        }
    }

    public function test_csv_text_values_cannot_be_interpreted_as_spreadsheet_formulas(): void
    {
        $this->assertSame("'=1+1", CsvSanitizer::escapeFormula('=1+1'));
        $this->assertSame("'  @SUM(A1:A2)", CsvSanitizer::escapeFormula('  @SUM(A1:A2)'));
        $this->assertSame('plain text', CsvSanitizer::escapeFormula('plain text'));
        $this->assertSame(123.45, CsvSanitizer::escapeFormula(123.45));
    }
}
