<div class="p-6">
    <h2 class="text-xl font-bold mb-4">Bot Status</h2>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="bg-white p-4 rounded-lg shadow">
            <h3 class="font-bold mb-2">Bot Status</h3>
            <div class="flex items-center">
                <div class="w-3 h-3 rounded-full mr-2 {{ $this->getData()['botStatus'] === 'Active' ? 'bg-green-500' : 'bg-red-500' }}"></div>
                <span>{{ $this->getData()['botStatus'] }}</span>
            </div>
        </div>
        
        <div class="bg-white p-4 rounded-lg shadow">
            <h3 class="font-bold mb-2">Win Rate</h3>
            <div class="text-2xl font-bold">{{ $this->getData()['winRate'] }}%</div>
        </div>
    </div>
    
    <div class="bg-white p-4 rounded-lg shadow">
        <h3 class="font-bold mb-2">Recent Activity</h3>
        @if($this->getData()['recentLogs']->count() > 0)
            <ul class="space-y-2">
                @foreach($this->getData()['recentLogs'] as $log)
                    <li class="border-b pb-2 last:border-0 last:pb-0">
                        <div class="flex justify-between">
                            <span class="font-medium">{{ $log->action }}</span>
                            <span class="text-gray-500 text-sm">{{ $log->executed_at->format('M d, H:i') }}</span>
                        </div>
                        <div class="text-sm text-gray-600 mt-1">{{ strlen($log->message) > 100 ? substr($log->message, 0, 100) . '...' : $log->message }}</div>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-gray-500 italic">No recent activity logs available.</p>
        @endif
    </div>
</div>