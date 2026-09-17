<?php

use App\Domain\Identity\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fieldops:ensure-admin', function () {
    $email = 'admin@admin.com';
    $password = '12345678';

    $url = (string) (env('DB_URL') ?: env('DATABASE_URL') ?: '');
    $masked = $url !== '' ? preg_replace('#://([^:]+):([^@]+)@#', '://$1:***@', $url) : config('database.connections.pgsql.host');
    $this->info('Connecting: '.$masked);

    DB::connection()->getPdo();

    $user = User::query()->updateOrCreate(
        ['email' => $email],
        [
            'name' => 'FieldOps Admin',
            'password' => $password,
            'is_super_admin' => true,
        ],
    );

    $this->info("Super admin ready: {$user->email} (id {$user->id})");
    $this->comment('Login: '.$email.' / '.$password);
})->purpose('Create or reset admin@admin.com on the connected database');
