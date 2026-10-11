<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('provisions a verified operator with a generated password', function (): void {
    $exitCode = Artisan::call('logs:create-admin', [
        'email' => 'operator@example.test',
        '--name' => 'Log Operator',
    ]);

    $user = User::query()->where('email', 'operator@example.test')->firstOrFail();
    $output = json_decode(trim(Artisan::output()), true, flags: JSON_THROW_ON_ERROR);

    expect($exitCode)->toBe(0)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($output['password'])->toBeString()
        ->and(Hash::check($output['password'], $user->password))->toBeTrue();
});
