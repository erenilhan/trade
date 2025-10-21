<x-filament-panels::page>
    <div class="space-y-4">
        <h1 class="text-2xl font-bold">Multi-Coin API Tester</h1>

        <div class="flex items-center space-x-4">
            <x-filament::button wire:click="getStatus" wire:loading.attr="disabled" wire:target="getStatus">
                <x-filament::icon
                    icon="heroicon-o-arrow-path"
                    class="h-5 w-5 mr-1 rtl:ml-1"
                    wire:loading.remove wire:target="getStatus"
                />
                <x-filament::icon
                    icon="heroicon-o-arrow-path"
                    class="h-5 w-5 mr-1 rtl:ml-1 animate-spin"
                    wire:loading wire:target="getStatus"
                />
                Get Status
            </x-filament::button>

            <x-filament::button wire:click="executeTrade" color="success" wire:loading.attr="disabled" wire:target="executeTrade">
                <x-filament::icon
                    icon="heroicon-o-play"
                    class="h-5 w-5 mr-1 rtl:ml-1"
                    wire:loading.remove wire:target="executeTrade"
                />
                <x-filament::icon
                    icon="heroicon-o-play"
                    class="h-5 w-5 mr-1 rtl:ml-1 animate-spin"
                    wire:loading wire:target="executeTrade"
                />
                Execute Trade
            </x-filament::button>

            <x-filament::button wire:click="clearOutput" color="gray" wire:loading.attr="disabled" wire:target="clearOutput">
                <x-filament::icon
                    icon="heroicon-o-x-mark"
                    class="h-5 w-5 mr-1 rtl:ml-1"
                />
                Clear Output
            </x-filament::button>
        </div>

        @if($output)
            <div class="mt-4 p-4 bg-gray-100 rounded-lg dark:bg-gray-800">
                <h2 class="text-lg font-semibold mb-2">API Response</h2>
                <pre class="text-sm whitespace-pre-wrap"><code>{{ $output }}</code></pre>
            </div>
        @endif
    </div>
</x-filament-panels::page>