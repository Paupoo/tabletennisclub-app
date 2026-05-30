<?php

declare(strict_types=1);

namespace Tests;

use App\Domains\Shared\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    /**
     * Indicates whether the default seeder should run before each test.
     *
     * @var bool
     */
    // Disable automatic seeding before each test to speed up the test suite.
    // We'll run the seeder once for the whole suite instead.
    protected $seed = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Mark setup as complete so existing tests are not redirected to /setup.
        AppSetting::set('setup_completed', '1');
    }
}
