<?php

namespace SlowestWind\TabSessionGuard\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SlowestWind\TabSessionGuard\Services\TabGuardService;

class TabGuardController extends Controller
{
    protected TabGuardService $tabGuard;

    public function __construct(TabGuardService $tabGuard)
    {
        $this->tabGuard = $tabGuard;
    }

    /**
     * Close a specific tab
     */
    public function closeTab(Request $request): JsonResponse
    {
        $request->validate([
            'tabId' => 'required|string',
        ]);

        $userId = auth()->id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $this->tabGuard->closeTab($userId, $request->input('tabId'));

        return response()->json(['success' => true]);
    }

    /**
     * Get tab information for the current user
     */
    public function getTabInfo(): JsonResponse
    {
        $userId = auth()->id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $tabInfo = $this->tabGuard->getTabInfo($userId);

        return response()->json($tabInfo);
    }

    /**
     * Heartbeat to keep tab alive
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $request->validate([
            'tabId' => 'required|string',
        ]);

        $userId = auth()->id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Update tab activity timestamp
        $sessionKey = config('tab-session-guard.session.key_prefix') . $userId;
        $tabs = session()->get($sessionKey, []);
        $tabId = $request->input('tabId');

        if (isset($tabs[$tabId])) {
            $tabs[$tabId]['last_activity'] = now();
            session()->put($sessionKey, $tabs);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Get current status of tab guard
     */
    public function status(): JsonResponse
    {
        $config = config('tab-session-guard');
        
        return response()->json([
            'enabled' => $config['enabled'],
            'global_max_tabs' => $config['global']['max_tabs'],
            'user_authenticated' => auth()->check(),
            'user_id' => auth()->id(),
        ]);
    }
}
