<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class ApiTester extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static string|null|\UnitEnum $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.api-tester';

    public $output;
    public $loading = false;

    public function getStatus()
    {
        try {
            $this->loading = true;
            $response = Http::timeout(60)->get(url('/api/multi-coin/status'));

            if ($response->successful()) {
                $this->output = json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                $this->output = "Error {$response->status()}: " . $response->body();
            }
        } catch (\Exception $e) {
            $this->output = "Exception: " . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function executeTrade()
    {
        try {
            $this->loading = true;
            $response = Http::timeout(120)->post(url('/api/multi-coin/execute'));

            if ($response->successful()) {
                $this->output = json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                $this->output = "Error {$response->status()}: " . $response->body();
            }
        } catch (\Exception $e) {
            $this->output = "Exception: " . $e->getMessage();
        } finally {
            $this->loading = false;
        }
    }

    public function clearOutput()
    {
        $this->output = null;
    }
}
