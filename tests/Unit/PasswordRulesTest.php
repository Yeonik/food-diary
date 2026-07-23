<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\Fortify\PasswordRules;
use Illuminate\Validation\Rules\Password;
use ReflectionProperty;
use Tests\TestCase;

/**
 * The breach check is switched off in the test environment and nowhere else, so
 * the suite cannot assert it by validating a password — doing that would make
 * the network call the switch exists to avoid. What it can assert is that the
 * switch is on the environment and not on something that quietly stays false in
 * production too.
 */
class PasswordRulesTest extends TestCase
{
    private function rules(string $environment): Password
    {
        $this->app['env'] = $environment;

        $subject = new class
        {
            use PasswordRules;

            /** @return array<int, mixed> */
            public function rules(): array
            {
                return $this->passwordRules();
            }
        };

        $password = collect($subject->rules())->first(fn ($rule) => $rule instanceof Password);

        $this->assertInstanceOf(Password::class, $password, 'No password rule was produced at all.');

        return $password;
    }

    private function checksBreaches(Password $rule): bool
    {
        // The flag is private to the rule; reading it is the only way to see the
        // decision without performing it.
        $property = new ReflectionProperty(Password::class, 'uncompromised');

        return (bool) $property->getValue($rule);
    }

    public function test_a_running_instance_refuses_a_breached_password(): void
    {
        $this->assertTrue($this->checksBreaches($this->rules('production')));
        $this->assertTrue($this->checksBreaches($this->rules('local')));
    }

    public function test_the_test_environment_is_the_only_one_that_skips_the_check(): void
    {
        $this->assertFalse($this->checksBreaches($this->rules('testing')));
    }

    public function test_the_length_floor_holds_in_every_environment(): void
    {
        foreach (['testing', 'local', 'production'] as $environment) {
            $length = new ReflectionProperty(Password::class, 'min');

            $this->assertSame(8, $length->getValue($this->rules($environment)));
        }
    }
}
