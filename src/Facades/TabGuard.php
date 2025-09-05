<?php

namespace SlowestWind\TabSessionGuard\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool shouldGuard(\Illuminate\Http\Request $request)
 * @method static array validateTabLimits(\Illuminate\Http\Request $request)
 * @method static void closeTab(int $userId, string $tabId)
 * @method static array getTabInfo(int $userId)
 */
class TabGuard extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'tab-guard';
    }
}
