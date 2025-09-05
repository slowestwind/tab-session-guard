<?php

namespace SlowestWind\TabSessionGuard\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SlowestWind\TabSessionGuard\Providers\TabSessionGuardServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            TabSessionGuardServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('tab-session-guard.enabled', true);
        $app['config']->set('tab-session-guard.global.max_tabs', 5);
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
    }
}
