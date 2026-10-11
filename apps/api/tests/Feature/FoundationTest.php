<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Sanctum\TransientToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        config(['sanctum.stateful' => ['localhost:3000']]);
    }

    private function registration(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Researcher',
            'email' => 'researcher@example.test',
            'password' => 'valid-test-password',
            'password_confirmation' => 'valid-test-password',
        ], $overrides);
    }

    private function user(): User
    {
        return User::factory()->create(['email' => 'researcher@example.test', 'password' => 'valid-test-password']);
    }

    public function test_postgresql_vector_and_readiness(): void
    {
        $this->assertTrue(DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'vector') AS enabled")->enabled);
        $response = $this->getJson('/api/v1/health')->assertOk()->assertExactJson(['data' => ['status' => 'ready']]);
        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-ID')));
        $this->getJson('/api/user')->assertNotFound()->assertJsonPath('error.code', 'not_found');
    }

    public function test_readiness_failure_does_not_leak_database_details(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new \RuntimeException('secret database hostname/password'));
        $response = $this->withHeader('Origin', 'http://localhost:3000')->getJson('/api/v1/health')->assertStatus(503)->assertJsonPath('error.code', 'not_ready');
        $this->assertStringNotContainsString('secret', $response->getContent());
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('error.request_id'));
    }

    public function test_registration_normalizes_email_hashes_password_and_does_not_authenticate(): void
    {
        $this->postJson('/api/v1/auth/register', $this->registration(['email' => ' Researcher@Example.Test ']))
            ->assertCreated()->assertJsonPath('data.email', 'researcher@example.test')->assertJsonMissingPath('data.password');
        $user = User::firstOrFail();
        $this->assertTrue(Hash::check('valid-test-password', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertGuest('web');
    }

    public function test_registration_validation_and_duplicate_email(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [])->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_error')->assertJsonStructure(['error' => ['fields' => ['name', 'email', 'password'], 'request_id']]);
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('error.request_id'));
        $this->user();
        $this->postJson('/api/v1/auth/register', $this->registration(['email' => 'RESEARCHER@EXAMPLE.TEST']))
            ->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['email']]]);
        $this->postJson('/api/v1/auth/register', $this->registration(['password_confirmation' => 'different']))->assertUnprocessable();
    }

    public function test_invalid_login_and_token_credentials_have_safe_errors(): void
    {
        $this->user();
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/v1/auth/login', [
            'email' => 'researcher@example.test', 'password' => 'wrong',
        ])->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
        $this->flushHeaders();
        $this->postJson('/api/v1/auth/tokens', ['email' => 'nobody@example.test', 'password' => 'wrong', 'device_name' => 'Phone'])
            ->assertUnauthorized()->assertJsonPath('error.message', 'Authentication failed.');
    }

    public function test_web_login_session_me_and_logout(): void
    {
        $user = $this->user();
        $mobileTokens = [$user->createToken('Phone')->plainTextToken, $user->createToken('Tablet')->plainTextToken];
        $this->withHeader('Origin', 'http://localhost:3000');
        $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $oldSession = session()->getId();
        $this->postJson('/api/v1/auth/login', ['email' => ' Researcher@Example.Test ', 'password' => 'valid-test-password'])
            ->assertOk()->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.token');
        $this->assertNotSame($oldSession, session()->getId());
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
        $oldCsrf = session()->token();
        $this->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('data.message', 'Logged out.');
        $this->assertNotSame($oldCsrf, session()->token());
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertSame(2, $user->tokens()->count());
        $this->flushHeaders();
        foreach ($mobileTokens as $token) {
            Auth::forgetGuards();
            $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
        }
    }

    public function test_web_login_requires_first_party_session(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'researcher@example.test', 'password' => 'valid-test-password'])
            ->assertStatus(400)->assertJsonPath('error.code', 'bad_request');
    }

    public function test_mobile_token_me_and_current_token_revocation_preserves_other_devices(): void
    {
        $user = $this->user();
        $other = $user->createToken('Other device')->plainTextToken;
        $response = $this->postJson('/api/v1/auth/tokens', [
            'email' => 'researcher@example.test', 'password' => 'valid-test-password', 'device_name' => 'Phone',
        ])->assertCreated()->assertJsonPath('data.token_type', 'Bearer')->assertJsonStructure(['data' => ['token', 'expires_at', 'user']]);
        $token = $response->json('data.token');
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'Phone']);
        $storedToken = $user->tokens()->where('name', 'Phone')->first();
        $this->assertSame(hash('sha256', explode('|', $token, 2)[1]), $storedToken->token);
        $this->assertGuest('web');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
        Auth::forgetGuards();
        $this->deleteJson('/api/v1/auth/tokens/current')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $storedToken->id]);
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
        Auth::forgetGuards();
        $this->withToken($other)->getJson('/api/v1/me')->assertOk();
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_session_cannot_revoke_mobile_tokens(): void
    {
        $user = $this->user();
        $user->createToken('Phone');
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000')
            ->deleteJson('/api/v1/auth/tokens/current')->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_mobile_token_cannot_log_out_a_web_session(): void
    {
        $user = $this->user();
        $token = $user->createToken('Phone')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertStatus(400);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_unauthenticated_me_and_request_id_are_normalized_even_without_accept(): void
    {
        $response = $this->withHeader('X-Request-ID', 'untrusted-id')->get('/api/v1/me')
            ->assertUnauthorized()->assertJsonPath('error.code', 'unauthenticated');
        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-ID')));
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('error.request_id'));
        $this->assertSame('{}', json_encode($response->getData()->error->fields));
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/register', [])->assertUnprocessable();
        }
        $response = $this->postJson('/api/v1/auth/register', [])->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
        $this->assertNotNull($response->headers->get('Retry-After'));
    }

    public function test_login_and_token_issuance_are_rate_limited(): void
    {
        foreach (['login', 'tokens'] as $endpoint) {
            $this->withHeader('Origin', 'http://localhost:3000');
            for ($attempt = 0; $attempt < 10; $attempt++) {
                $this->postJson('/api/v1/auth/'.$endpoint, [
                    'email' => $endpoint.'@example.test', 'password' => 'invalid', 'device_name' => 'Phone',
                ])->assertUnauthorized();
            }
            $this->postJson('/api/v1/auth/'.$endpoint, [])->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
        }
    }

    public function test_csrf_is_enforced_for_spa_writes(): void
    {
        $this->app['env'] = 'local'; // Laravel normally skips CSRF only inside tests.
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/v1/auth/login', [])
            ->assertStatus(419)->assertJsonPath('error.code', 'csrf_mismatch');
        $this->withSession(['_token' => 'valid-csrf-token'])->postJson('/api/v1/auth/register', [
            ...$this->registration(), '_token' => 'valid-csrf-token',
        ])->assertCreated();
    }

    public function test_cors_exposes_request_id_for_only_the_configured_origin(): void
    {
        $response = $this->withHeader('Origin', 'http://localhost:3000')->getJson('/api/v1/health')->assertOk();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
        $this->assertStringContainsString('X-Request-ID', $response->headers->get('Access-Control-Expose-Headers'));
        // A single configured origin may be returned even for a different Origin;
        // browsers reject it because it does not match the requesting origin.
        $this->withHeader('Origin', 'https://untrusted.example')->getJson('/api/v1/health')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000');
    }

    public function test_unexpected_errors_are_safe_even_when_debug_is_enabled_and_logs_are_sanitized(): void
    {
        config(['app.debug' => true]);
        Route::middleware('api')->get('/api/v1/test-failure', fn () => throw new \RuntimeException('secret password SQL token'));
        Log::spy();
        $response = $this->getJson('/api/v1/test-failure')->assertStatus(500)->assertJsonPath('error.code', 'server_error');
        $this->assertStringNotContainsString('secret', $response->getContent());
        Log::shouldHaveReceived('error')->with('API request failed.', \Mockery::on(fn ($context) => $context['exception_type'] === \RuntimeException::class
            && $context['request_id'] === $response->headers->get('X-Request-ID')
            && count($context) === 2
        ));
    }

    public function test_csrf_bootstrap_cookie_flags_and_method_errors(): void
    {
        $response = $this->withHeader('Origin', 'http://localhost:3000')->get('/sanctum/csrf-cookie')->assertNoContent();
        $this->assertTrue($response->getCookie(config('session.cookie'))->isHttpOnly());
        $this->assertFalse($response->getCookie('XSRF-TOKEN')->isHttpOnly());
        $response = $this->postJson('/api/v1/health')->assertStatus(405)->assertJsonPath('error.code', 'method_not_allowed');
        $this->assertSame($response->headers->get('X-Request-ID'), $response->json('error.request_id'));
    }

    public function test_expired_mobile_token_is_rejected_and_device_name_is_required(): void
    {
        $user = $this->user();
        $token = $user->createToken('Expired', ['*'], now()->subMinute())->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->flushHeaders();
        $this->postJson('/api/v1/auth/tokens', ['email' => $user->email, 'password' => 'valid-test-password'])
            ->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['device_name']]]);
    }

    public function test_mobile_failures_verify_passwords_on_both_account_paths(): void
    {
        $user = $this->user();
        $hasher = Hash::driver();
        Hash::partialMock()->shouldReceive('check')->once()->with('wrong', $user->password)
            ->andReturnUsing(fn ($password, $hash) => $hasher->check($password, $hash));
        Hash::shouldReceive('check')->once()->with('wrong', \Mockery::on(fn ($hash) => $hash !== $user->password && password_get_info($hash)['algoName'] === 'bcrypt'
        ))->andReturnUsing(fn ($password, $hash) => $hasher->check($password, $hash));

        $errors = [];
        foreach ([$user->email, 'nonexistent@example.test'] as $email) {
            $response = $this->postJson('/api/v1/auth/tokens', ['email' => $email, 'password' => 'wrong', 'device_name' => 'Phone'])
                ->assertUnauthorized()->assertJsonPath('error.message', 'Authentication failed.');
            $error = $response->json('error');
            $this->assertTrue(Str::isUuid($error['request_id']));
            unset($error['request_id']);
            $errors[] = $error;
            $this->assertGuest('web');
        }
        $this->assertSame($errors[0], $errors[1]);
        $this->assertSame(1, User::count());
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_dummy_password_never_authenticates_a_nonexistent_account(): void
    {
        $this->postJson('/api/v1/auth/tokens', [
            'email' => 'nonexistent@example.test', 'password' => 'foundation-dummy-password', 'device_name' => 'Phone',
        ])->assertUnauthorized();
        $this->assertSame(0, User::count());
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertGuest('web');
    }

    public static function passwordByteCases(): array
    {
        return [
            'ASCII under limit' => ['valid-ascii-password', true],
            'ASCII exactly 72 bytes' => [str_repeat('a', 72), true],
            'ASCII over 72 bytes' => [str_repeat('a', 73), false],
            'UTF-8 under limit' => [str_repeat('é', 20), true],
            'UTF-8 exactly 72 bytes' => [str_repeat('é', 36), true],
            'UTF-8 over 72 bytes' => [str_repeat('é', 37), false],
            'null byte unsupported by bcrypt' => ["valid-password\0suffix", false],
        ];
    }

    #[DataProvider('passwordByteCases')]
    public function test_password_byte_policy_across_authentication(string $password, bool $valid): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->registration([
            'password' => $password, 'password_confirmation' => $password,
        ]))->assertStatus($valid ? 201 : 422);
        if ($valid) {
            $this->assertTrue(Hash::check($password, User::firstOrFail()->password));
        } else {
            $response->assertJsonStructure(['error' => ['fields' => ['password']]]);
            $this->assertSame(0, User::count());
        }
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/v1/auth/login', [
            'email' => 'researcher@example.test', 'password' => $password,
        ])->assertStatus($valid ? 200 : 422);
        $this->flushHeaders();
        $this->postJson('/api/v1/auth/tokens', [
            'email' => 'researcher@example.test', 'password' => $password, 'device_name' => 'Phone',
        ])->assertStatus($valid ? 201 : 422);
    }

    public function test_registration_minimum_and_multibyte_confirmation(): void
    {
        foreach ([['short', 'short'], [str_repeat('é', 20), str_repeat('è', 20)]] as [$password, $confirmation]) {
            $this->postJson('/api/v1/auth/register', $this->registration([
                'password' => $password, 'password_confirmation' => $confirmation,
            ]))->assertUnprocessable()->assertJsonStructure(['error' => ['fields' => ['password']]]);
        }
        $this->assertSame(0, User::count());
    }

    public function test_mobile_expiration_uses_configured_minutes(): void
    {
        $user = $this->user();
        $this->freezeSecond();
        $this->assertSame(43200, config('sanctum.mobile_token_expiration'));
        foreach ([43200, 90] as $minutes) {
            config(['sanctum.mobile_token_expiration' => $minutes]);
            $response = $this->postJson('/api/v1/auth/tokens', [
                'email' => $user->email, 'password' => 'valid-test-password', 'device_name' => 'Phone-'.$minutes,
            ])->assertCreated()->assertJsonPath('data.expires_at', now()->addMinutes($minutes)->toISOString());
            $stored = $user->tokens()->where('name', 'Phone-'.$minutes)->firstOrFail();
            $this->assertTrue($stored->expires_at->equalTo(now()->addMinutes($minutes)));
            $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertOk();
            Auth::forgetGuards();
            $this->flushHeaders();
        }
        $this->travel(91)->minutes();
        $short = $user->tokens()->where('name', 'Phone-90')->firstOrFail();
        $this->assertTrue($short->expires_at->isPast());
        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_session_plus_bearer_cannot_revoke_mobile_tokens(): void
    {
        $user = $this->user();
        $token = $user->createToken('Phone')->plainTextToken;
        $this->withHeader('Origin', 'http://localhost:3000')->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'valid-test-password',
        ])->assertOk();
        Auth::forgetGuards();
        $this->withToken($token)->deleteJson('/api/v1/auth/tokens/current')
            ->assertForbidden()->assertJsonPath('error.code', 'forbidden');
        $this->assertInstanceOf(TransientToken::class, Auth::guard('web')->user()->currentAccessToken());
        $this->assertSame(1, $user->tokens()->count());
        $this->flushHeaders();
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    }

    public function test_email_rate_limits_share_case_and_trim_variants_across_ips(): void
    {
        foreach (['register' => 5, 'login' => 10, 'tokens' => 10] as $endpoint => $limit) {
            $this->withHeader('Origin', 'http://localhost:3000');
            for ($attempt = 0; $attempt < $limit; $attempt++) {
                $email = ($attempt % 2 === 0) ? ' '.$endpoint.'@EXAMPLE.TEST ' : $endpoint.'@example.test';
                $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)])
                    ->postJson('/api/v1/auth/'.$endpoint, ['email' => $email, 'password' => 'wrong', 'device_name' => 'Phone'])
                    ->assertStatus($endpoint === 'register' ? 422 : 401);
            }
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.100'])
                ->postJson('/api/v1/auth/'.$endpoint, ['email' => strtoupper($endpoint).'@example.test', 'password' => 'wrong', 'device_name' => 'Phone'])
                ->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
        }
    }

    public function test_referer_alone_establishes_web_session_context(): void
    {
        $user = $this->user();
        $this->withHeader('Referer', 'http://localhost:3000/login')->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'valid-test-password',
        ])->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertOk();
        $this->postJson('/api/v1/auth/logout')->assertOk();
        Auth::forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public static function expirationConfigCases(): array
    {
        return [
            'default' => [null, 43200],
            'override' => ['90', 90],
            'zero' => ['0', null],
            'negative' => ['-1', null],
            'fraction' => ['1.5', null],
            'empty' => ['', null],
            'text' => ['invalid', null],
            'boolean' => ['false', null],
            'out of integer range' => ['2147483648', null],
        ];
    }

    #[DataProvider('expirationConfigCases')]
    public function test_mobile_expiration_configuration_is_validated(?string $value, ?int $expected): void
    {
        $repository = Env::getRepository();
        $name = 'SANCTUM_MOBILE_TOKEN_EXPIRATION';
        $previous = $repository->get($name);
        try {
            if ($value === null) {
                $repository->clear($name);
            } else {
                $repository->set($name, $value);
            }
            if ($expected === null) {
                $this->expectException(\InvalidArgumentException::class);
            }
            $configuration = require config_path('sanctum.php');
            $this->assertSame($expected, $configuration['mobile_token_expiration']);
            $this->assertNull($configuration['expiration']);
        } finally {
            if ($previous === null) {
                $repository->clear($name);
            } else {
                $repository->set($name, $previous);
            }
        }
    }
}
