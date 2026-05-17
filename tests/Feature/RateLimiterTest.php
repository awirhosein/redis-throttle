<?php

namespace Awirhosein\RateLimiter\Tests\Feature;

use Awirhosein\RateLimiter\Tests\Fixtures\User;
use Awirhosein\RateLimiter\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;

class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushAll();

        Route::middleware(['redis-throttle:day', 'redis-throttle:minute'])->group(function () {
            Route::get('api/no-auth', fn () => response()->json());

            Route::middleware('auth')->group(function () {
                Route::get('api/tasks', fn () => response()->json());
                Route::get('api/projects', fn () => response()->json());
                Route::post('api/uploads', fn () => response()->json())->middleware('redis-throttle.file');
            });
        });
    }

    #[Test]
    public function free_user_is_limited_to_10_requests_per_minute()
    {
        $user = User::create(['plan' => 'free']);
        $this->actingAs($user);

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('api/tasks')->assertOk();
        }

        $this->getJson('api/tasks')->assertTooManyRequests();
    }

    #[Test]
    public function pro_user_is_limited_to_100_requests_per_minute()
    {
        $user = User::create(['plan' => 'pro']);
        $this->actingAs($user);

        for ($i = 0; $i < 100; $i++) {
            $this->getJson('api/tasks')->assertOk();
        }

        $this->getJson('api/tasks')->assertTooManyRequests();
    }

    #[Test]
    public function enterprise_user_is_limited_to_1000_requests_per_minute()
    {
        $user = User::create(['plan' => 'enterprise']);
        $this->actingAs($user);

        for ($i = 0; $i < 1000; $i++) {
            $this->getJson('api/tasks')->assertOk();
        }

        $this->getJson('api/tasks')->assertTooManyRequests();
    }

    #[Test]
    public function free_user_is_limited_daily()
    {
        config(['rate-limiter.free.per_day' => 5]);

        $user = User::create(['plan' => 'free']);
        $this->actingAs($user);

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('api/tasks')->assertOk();
        }

        $this->getJson('api/tasks')->assertTooManyRequests();
    }

    #[Test]
    public function different_endpoints_have_separate_counters()
    {
        $user = User::create(['plan' => 'free']);
        $this->actingAs($user);

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('api/tasks')->assertOk();
        }

        $this->getJson('api/tasks')->assertTooManyRequests();
        $this->getJson('api/projects')->assertOk();
    }

    #[Test]
    public function retry_after_header_is_present_on_rate_limit_response()
    {
        $user = User::create(['plan' => 'free']);
        $this->actingAs($user);

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('api/tasks')->assertOk();
        }

        $this->getJson('api/tasks')
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    #[Test]
    public function headers_are_present_on_successful_response()
    {
        $response = $this->getJson('api/no-auth');

        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
        $response->assertHeader('X-RateLimit-Reset');
    }

    #[Test]
    public function free_user_is_limited_to_5mb_per_day_for_uploads()
    {
        $user = User::create(['plan' => 'free']);
        $this->actingAs($user);

        // 3 MB
        $file = UploadedFile::fake()->image('test.jpeg')->size(1024 * 3);
        $this->postJson('api/uploads', ['image' => $file])->assertOk();

        // 1 MB
        $file = UploadedFile::fake()->image('test.jpeg')->size(1024);
        $this->postJson('api/uploads', ['image' => $file])->assertOk();

        // 0.5 MB
        $file = UploadedFile::fake()->image('test.jpeg')->size(1024 / 2);
        $this->postJson('api/uploads', ['image' => $file])->assertOk();

        // 2 MB (would exceed 5 MB total)
        $file = UploadedFile::fake()->image('test.jpeg')->size(1024 * 2);
        $this->postJson('api/uploads', ['image' => $file])->assertTooManyRequests();
    }

    #[Test]
    public function pro_user_has_no_file_upload_limit()
    {
        $user = User::create(['plan' => 'pro']);
        $this->actingAs($user);

        // 10 MB file - Pro has no limit
        $file = UploadedFile::fake()->image('test.jpeg')->size(1024 * 10);
        $this->postJson('api/uploads', ['image' => $file])->assertOk();
    }

    #[Test]
    public function anonymous_user_is_rate_limited_by_ip()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('api/no-auth')->assertOk();
        }

        $this->getJson('api/no-auth')->assertTooManyRequests();
    }
}
