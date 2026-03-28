<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates 10 fixed demo users for development and manual testing.
 *
 * Credentials are documented in docs/SEED_CREDENTIALS.md.
 * All users share the password "password".
 */
class UserSeeder extends Seeder
{
    /** @var array<int, array{name: string, email: string}> */
    private const USERS = [
        ['name' => 'Alice Adams',  'email' => 'alice@mediaflow.test'],
        ['name' => 'Bob Baker',    'email' => 'bob@mediaflow.test'],
        ['name' => 'Carol Chen',   'email' => 'carol@mediaflow.test'],
        ['name' => 'Dave Dixon',   'email' => 'dave@mediaflow.test'],
        ['name' => 'Eve Evans',    'email' => 'eve@mediaflow.test'],
        ['name' => 'Frank Foster', 'email' => 'frank@mediaflow.test'],
        ['name' => 'Grace Green',  'email' => 'grace@mediaflow.test'],
        ['name' => 'Henry Hill',   'email' => 'henry@mediaflow.test'],
        ['name' => 'Iris Ibarra',  'email' => 'iris@mediaflow.test'],
        ['name' => 'Jack Jones',   'email' => 'jack@mediaflow.test'],
    ];

    public function run(): void
    {
        $hashed = Hash::make('password');

        foreach (self::USERS as $data) {
            User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'              => $data['name'],
                    'password'          => $hashed,
                    'email_verified_at' => now(),
                ],
            );
        }

        $this->command->info('  <fg=green>✓</> 10 demo users seeded  (password: <options=bold>password</>)');
        $this->command->line('    See <options=bold>docs/SEED_CREDENTIALS.md</> for the full credential table.');
    }
}
