<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ProcessTransactions extends Command
{
    protected $signature = 'transactions:process {file}';
    protected $description = 'Process transactions from a file and calculate commissions.';

    private const BIN_URL = 'https://lookup.binlist.net/';
    private const EXCHANGE_URL = 'https://api.exchangeratesapi.io/latest';
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR',
        'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PO', 'PT', 'RO',
        'SE', 'SI', 'SK'
    ];

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error('File not found!');
            return 1;
        }

        $transactions = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($transactions as $transaction) {
            $transactionData = json_decode($transaction, true);

            if (!$transactionData) {
                $this->error('Invalid transaction format.');
                continue;
            }

            $commission = $this->calculateCommission($transactionData);
            $this->line(number_format($commission, 2, '.', ''));
        }

        return 0;
    }

    private function calculateCommission(array $transaction): float
    {
        $binInfo = $this->getBinInfo($transaction['bin']);
        $isEu = $this->isEu($binInfo['country']['alpha2']);
        $rate = $this->getExchangeRate($transaction['currency']);
        $amountInEur = $transaction['currency'] === 'EUR' ? $transaction['amount'] : $transaction['amount'] / $rate;

        $commissionRate = $isEu ? 0.01 : 0.02;
        return ceil($amountInEur * $commissionRate * 100) / 100;
    }

    private function getBinInfo(string $bin): array
    {
        $response = Http::get(self::BIN_URL . $bin);
        return $response->json();
    }

    private function getExchangeRate(string $currency): float
    {
        if ($currency === 'EUR') {
            return 1.0;
        }

        $response = Http::get(self::EXCHANGE_URL, [
            'access_key' => '8d8857202c3a6d3b2bad5a819c90b72e',
        ]);
        $rates = $response->json()['rates'];

        return $rates[$currency] ?? 0.0;
    }

    private function isEu(string $countryCode): bool
    {
        return in_array($countryCode, self::EU_COUNTRIES, true);
    }
}
