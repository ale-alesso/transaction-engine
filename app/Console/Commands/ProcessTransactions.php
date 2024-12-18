<?php

namespace App\Console\Commands;

use App\Exceptions\InvalidAccessKeyException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessTransactions extends Command
{
    protected $signature = 'transactions:process {file}';
    protected $description = 'Process transactions from a file and calculate commissions.';

    private const BIN_URL = 'https://lookup.binlist.net/';
    private const EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'ES', 'FI', 'FR', 'GR',
        'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PO', 'PT', 'RO',
        'SE', 'SI', 'SK'
    ];

    public function handle()
    {
        $filePath = $this->argument('file');

        if (!file_exists($filePath)) {
            $this->error('File not found');

            return 1;
        }

        $transactions = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($transactions as $transaction) {
            $transactionData = json_decode($transaction, true);

            if (!$transactionData) {
                $this->error('Invalid transaction format');

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

    private function getExchangeRate(string $currency): float|array
    {
        if ($currency === 'EUR') {
            return 1.0;
        }

        try {
            $response = Http::get(config('exchange.api_url'), [
                'access_key' => config('exchange.api_access_key'),
            ]);

            if ($response->failed() || $response->json('error.code') === 101) {
                throw new InvalidAccessKeyException($response->json('error.info'));
            }

            $rates = $response->json()['rates'];

            return $rates[$currency] ?? 0.0;
        } catch (RequestException $e) {
            Log::error('Error HTTP-request: ' . $e->getMessage());

            return ['error' => 'Unable to fetch exchange rates'];
        } catch (Exception $e) {
            Log::error('Failed to request: ' . $e->getMessage());

            return ['error' => $e->getMessage()];
        }
    }

    private function isEu(string $countryCode): bool
    {
        return in_array($countryCode, self::EU_COUNTRIES, true);
    }
}
