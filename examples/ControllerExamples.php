<?php

/**
 * Example Controller Implementations for Laravel Tab Session Guard
 * 
 * These are example controllers showing different ways to integrate
 * the Tab Session Guard package. Add proper imports in your actual implementation.
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Profile;
use App\Models\Application;
use SlowestWind\TabSessionGuard\Services\TabGuardService;
use SlowestWind\TabSessionGuard\Facades\TabGuard;

// Note: This example file may show lint errors because it references
// models and helper functions that don't exist in the package context.
// In your actual Laravel application, these would be available.

class ProfileController extends Controller
{
    /**
     * Using dependency injection
     */
    public function show(Request $request, $id, TabGuardService $tabGuard)
    {
        // Option 1: Manual check
        if ($tabGuard->shouldGuard($request)) {
            $validation = $tabGuard->validateTabLimits($request);
            
            if (!$validation['allowed']) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'error' => $validation['message'],
                        'validation' => $validation
                    ], 403);
                }
                
                return redirect()->route('dashboard')
                    ->with('error', $validation['message']);
            }
        }
        
        $profile = Profile::findOrFail($id);
        return view('profile.show', compact('profile'));
    }
    
    /**
     * Using facade
     */
    public function edit(Request $request, $id)
    {
        // Get current tab information
        $tabInfo = TabGuard::getTabInfo(auth()->id());
        
        // Check if we're approaching limits
        if ($tabInfo['total_tabs'] >= $tabInfo['global_limit'] - 1) {
            session()->flash('warning', 'You are approaching your tab limit.');
        }
        
        $profile = Profile::findOrFail($id);
        return view('profile.edit', compact('profile', 'tabInfo'));
    }
}

// Example Application Controller
class ApplicationController extends Controller
{
    public function show(Request $request, $id)
    {
        // This route will be automatically protected due to route-specific rules
        // for 'application.*' in the configuration
        
        $application = Application::findOrFail($id);
        return view('application.show', compact('application'));
    }
    
    public function create(Request $request)
    {
        // Check current user's tab info before allowing new application creation
        $tabInfo = TabGuard::getTabInfo(auth()->id());
        
        // If user already has an application tab open, redirect them there
        foreach ($tabInfo['tabs'] as $tab) {
            if (str_contains($tab['route'], 'application.')) {
                return redirect()->route('application.show', ['id' => 1])
                    ->with('info', 'You already have an application open. Please complete it first.');
            }
        }
        
        return view('application.create');
    }
}
