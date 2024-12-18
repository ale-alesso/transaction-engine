<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessTransactionsTest extends TestCase
{
    public function testCalculateCommissionForEu()
    {
        Http::fake([
            'https://lookup.binlist.net/*' => Http::response(['country' => ['alpha2' => 'DE']]),
            'https://api.exchangeratesapi.io/latest' => Http::response(['rates' => ['USD' => 1.2]])
        ]);

        $this->artisan('transactions:process', ['file' => base_path('tests/fixtures/input.txt')])
            ->expectsOutput('0.96')
            ->assertExitCode(0);
    }

    public function testCalculateCommissionForNonEu()
    {
        Http::fake([
            'https://lookup.binlist.net/*' => Http::response(['country' => ['alpha2' => 'US']]),
            'https://api.exchangeratesapi.io/latest' => Http::response(['rates' => ['USD' => 1.2]])
        ]);

        $this->artisan('transactions:process', ['file' => base_path('tests/fixtures/input.txt')])
            ->expectsOutput('1.91')
            ->assertExitCode(0);
    }
}
