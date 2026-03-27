<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 5.3 — Security Review
 *
 * Covers: CSRF, XSS, SQL injection surface, route authentication,
 * input character limits, private channel access, file format security,
 * rate limiting, information disclosure, and Livewire property safety.
 */
class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        Queue::fake();

        // Mirror the channel-registration fix from BroadcastChannelTest:
        // channels.php registers callbacks on the null broadcaster (phpunit.xml default).
        // Copy them to the pusher broadcaster so /broadcasting/auth runs the real callback.
        $manager  = $this->app->make(\Illuminate\Broadcasting\BroadcastManager::class);
        $channels = $manager->driver()->getChannels()->all();
        config(['broadcasting.default' => 'pusher']);
        foreach ($channels as $pattern => $callback) {
            $manager->driver()->channel($pattern, $callback);
        }
    }

    // -----------------------------------------------------------------------
    // CSRF — all mutating routes sit inside the web middleware group,
    //        which registers PreventRequestForgery (Laravel's CSRF guard).
    //        We assert the middleware stack directly: asserting a 419 via the
    //        test HTTP client is unreliable because actingAs() establishes a
    //        real session that the client reuses, and the test kernel re-uses
    //        that session's _token automatically, so the guard never fires.
    // -----------------------------------------------------------------------

    public function test_web_middleware_group_includes_csrf_protection(): void
    {
        $webGroup = app(\Illuminate\Routing\Router::class)->getMiddlewareGroups()['web'];

        $this->assertContains(
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
            $webGroup,
            'The web middleware group must include PreventRequestForgery (CSRF guard)'
        );
    }

    public function test_upload_route_belongs_to_web_middleware_group(): void
    {
        $route = app('router')->getRoutes()->getByAction(
            \App\Http\Controllers\MediaController::class . '@store'
        );

        $this->assertContains('web', $route->middleware());
    }

    public function test_delete_route_belongs_to_web_middleware_group(): void
    {
        $route = app('router')->getRoutes()->getByAction(
            \App\Http\Controllers\MediaController::class . '@destroy'
        );

        $this->assertContains('web', $route->middleware());
    }

    public function test_retry_route_belongs_to_web_middleware_group(): void
    {
        $route = app('router')->getRoutes()->getByAction(
            \App\Http\Controllers\MediaController::class . '@retry'
        );

        $this->assertContains('web', $route->middleware());
    }

    // -----------------------------------------------------------------------
    // Route authentication — all media endpoints require a logged-in user
    // -----------------------------------------------------------------------

    public function test_unauthenticated_upload_is_rejected(): void
    {
        $response = $this->postJson('/media', [
            'file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        ]);

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_show_is_rejected(): void
    {
        $media = Media::factory()->create();

        $this->getJson("/media/{$media->uuid}")->assertUnauthorized();
    }

    public function test_unauthenticated_delete_is_rejected(): void
    {
        $media = Media::factory()->create();

        $this->deleteJson("/media/{$media->uuid}")->assertUnauthorized();
    }

    public function test_unauthenticated_retry_is_rejected(): void
    {
        $media = Media::factory()->create(['status' => 'failed']);

        $this->postJson("/media/{$media->uuid}/retry")->assertUnauthorized();
    }

    public function test_unauthenticated_thumbnail_is_rejected(): void
    {
        $media = Media::factory()->create();

        $this->get("/media/{$media->uuid}/thumbnail")->assertRedirect('/login');
    }

    // -----------------------------------------------------------------------
    // Ownership enforcement — cross-user access blocked on every endpoint
    // -----------------------------------------------------------------------

    public function test_other_user_cannot_view_media(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)->getJson("/media/{$media->uuid}")->assertForbidden();
    }

    public function test_other_user_cannot_delete_media(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($other)->deleteJson("/media/{$media->uuid}")->assertForbidden();
        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_other_user_cannot_retry_media(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $owner->id, 'status' => 'failed']);

        $this->actingAs($other)->postJson("/media/{$media->uuid}/retry")->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Rate limiting
    // -----------------------------------------------------------------------

    public function test_upload_endpoint_is_rate_limited_after_10_requests(): void
    {
        $user = User::factory()->create();

        // Clear any residual rate-limit state from previous tests
        RateLimiter::clear('10,1:' . $user->id);

        // Submit 10 valid uploads — all should succeed (201)
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson('/media', [
                'file' => UploadedFile::fake()->image("photo{$i}.jpg", 800, 600),
            ])->assertStatus(201);
        }

        // The 11th request within the same minute must be throttled
        $response = $this->actingAs($user)->postJson('/media', [
            'file' => UploadedFile::fake()->image('photo11.jpg', 800, 600),
        ]);

        $response->assertStatus(429);
    }

    public function test_retry_endpoint_is_rate_limited_after_5_requests(): void
    {
        $user = User::factory()->create();

        // Create 5 failed media items
        $items = Media::factory()->count(5)->create([
            'user_id' => $user->id,
            'status'  => 'failed',
        ]);

        // 5 retries should succeed
        foreach ($items as $media) {
            $this->actingAs($user)->postJson("/media/{$media->uuid}/retry")
                ->assertOk();
        }

        // A fresh failed item — 6th retry request must be throttled
        $extra = Media::factory()->create(['user_id' => $user->id, 'status' => 'failed']);

        $this->actingAs($user)->postJson("/media/{$extra->uuid}/retry")
            ->assertStatus(429);
    }

    // -----------------------------------------------------------------------
    // XSS — user-controlled values are always HTML-escaped in output
    // -----------------------------------------------------------------------

    public function test_xss_payload_in_original_filename_is_stored_safely(): void
    {
        // PHP's temp-file layer strips angle brackets from filenames on some
        // systems, so we cannot carry '<script>…</script>' through fake()->image().
        // Instead seed the record directly: this confirms the DB column accepts
        // and round-trips the raw string without any server-side sanitisation
        // (escaping is a render-time responsibility, not a storage responsibility).
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id'           => $user->id,
            'original_filename' => '<script>alert(1)</script>.jpg',
        ]);

        $this->assertSame('<script>alert(1)</script>.jpg', $media->fresh()->original_filename);
    }

    public function test_xss_payload_in_original_filename_is_escaped_in_api_response(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create([
            'user_id'           => $user->id,
            'original_filename' => '<script>alert(1)</script>.jpg',
        ]);

        // JSON response must contain the escaped entity or the raw string in JSON encoding;
        // the key check is that it is NOT served as raw unescaped HTML tags in JSON
        $response = $this->actingAs($user)->getJson("/media/{$media->uuid}");

        $response->assertOk();
        // JSON-encoded angle brackets are safe — asserting the raw JSON does not
        // contain an unquoted script tag as executable HTML
        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $response->content(),
            'XSS payload must be JSON-encoded, not returned as raw HTML'
        );
    }

    // -----------------------------------------------------------------------
    // SQL injection — parameters are always bound, never interpolated
    // -----------------------------------------------------------------------

    public function test_sql_injection_in_uuid_path_returns_404_not_error(): void
    {
        $user = User::factory()->create();

        // A classic SQL injection in the route segment — Eloquent binds parameters,
        // so this cannot alter the query and must simply return 404.
        $response = $this->actingAs($user)
            ->getJson("/media/'; DROP TABLE media; --");

        // 404 — no record found, query was parameterized
        $response->assertNotFound();
        $this->assertDatabaseCount('media', 0);
    }

    // -----------------------------------------------------------------------
    // Input character limits
    // -----------------------------------------------------------------------

    public function test_filename_exceeding_255_characters_returns_422(): void
    {
        $user     = User::factory()->create();
        $longName = str_repeat('a', 256) . '.jpg';
        $file     = UploadedFile::fake()->image($longName, 800, 600);

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_filename_at_exactly_255_characters_is_accepted(): void
    {
        $user = User::factory()->create();
        // 251 a's + ".jpg" = 255 characters exactly
        $name = str_repeat('a', 251) . '.jpg';
        $file = UploadedFile::fake()->image($name, 800, 600);

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $response->assertStatus(201);
    }

    // -----------------------------------------------------------------------
    // Information disclosure — stored_filename must not be in API response
    // -----------------------------------------------------------------------

    public function test_show_response_does_not_expose_stored_filename(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/media/{$media->uuid}");

        $response->assertOk();
        $this->assertArrayNotHasKey(
            'stored_filename',
            $response->json(),
            'Internal storage path must not be exposed in the API response'
        );
    }

    public function test_show_response_contains_expected_public_fields(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson("/media/{$media->uuid}");

        $response->assertOk();
        $response->assertJsonStructure(['uuid', 'status', 'original_filename', 'mime_type']);
    }

    // -----------------------------------------------------------------------
    // Private channel access — channel auth enforces ownership
    // -----------------------------------------------------------------------

    public function test_owner_can_authenticate_their_private_channel(): void
    {
        $user  = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson('/broadcasting/auth', [
            'channel_name' => "private-media.{$media->uuid}",
            'socket_id'    => '1234.5678',
        ]);

        $response->assertOk();
    }

    public function test_other_user_cannot_authenticate_owners_private_channel(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = Media::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)->postJson('/broadcasting/auth', [
            'channel_name' => "private-media.{$media->uuid}",
            'socket_id'    => '1234.5678',
        ]);

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_authenticate_private_channel(): void
    {
        $media = Media::factory()->create();

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => "private-media.{$media->uuid}",
            'socket_id'    => '1234.5678',
        ]);

        // 401 or redirect — not 200
        $this->assertNotEquals(200, $response->status());
    }

    // -----------------------------------------------------------------------
    // File format security — MIME validation reads file content, not extension
    // -----------------------------------------------------------------------

    public function test_pdf_disguised_as_jpg_is_rejected(): void
    {
        $user = User::factory()->create();
        $file = new \Illuminate\Http\UploadedFile(
            base_path('tests/fixtures/pdf-disguised-as-jpg.jpg'),
            'photo.jpg',
            'image/jpeg',
            null,
            true
        );

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_executable_file_is_rejected_regardless_of_extension(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('photo.jpg', 100, 'application/octet-stream');

        $response = $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('media', 0);
    }

    public function test_stored_filename_never_contains_user_supplied_path_components(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('../../etc/passwd.jpg', 800, 600);

        $this->actingAs($user)->postJson('/media', ['file' => $file]);

        $media = Media::where('user_id', $user->id)->first();
        $this->assertNotNull($media);
        $this->assertStringNotContainsString('passwd', $media->stored_filename);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f\-]{36}\.[a-z]+$/',
            $media->stored_filename
        );
    }

    // -----------------------------------------------------------------------
    // Livewire property safety — $statusFilter whitelisted on the server
    // -----------------------------------------------------------------------

    public function test_invalid_status_filter_is_silently_reset_to_all(): void
    {
        $user      = User::factory()->create();
        $component = \Livewire\Livewire::actingAs($user)->test(\App\Livewire\MediaLibrary::class);

        // Attempt to set a non-whitelisted value via Livewire's property setter
        $component->set('statusFilter', "'; DROP TABLE media; --");

        $this->assertSame('all', $component->get('statusFilter'));
    }

    public function test_valid_status_filter_values_are_accepted(): void
    {
        $user      = User::factory()->create();
        $component = \Livewire\Livewire::actingAs($user)->test(\App\Livewire\MediaLibrary::class);

        foreach (['all', 'pending', 'processing', 'completed', 'failed'] as $value) {
            $component->set('statusFilter', $value);
            $this->assertSame($value, $component->get('statusFilter'));
        }
    }
}
