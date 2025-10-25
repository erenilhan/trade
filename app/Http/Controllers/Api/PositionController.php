<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PositionController extends Controller
{
    public function __construct(private TradingService $tradingService)
    {
    }

    public function close(Request $request)
    {
        $request->validate([
            'symbol' => 'required|string',
            'reason' => 'nullable|string',
        ]);

        $symbol = $request->input('symbol');
        $reason = $request->input('reason', 'Manual closure from API');

        try {
            Log::info("Attempting to manually close position for {$symbol} with reason: {$reason}");

            $result = $this->tradingService->closePositionManually($symbol, $reason);

            Log::info("Successfully closed position for {$symbol}", $result);

            return response()->json([
                'success' => true,
                'message' => "Position for {$symbol} closed successfully.",
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to close position for {$symbol}: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
