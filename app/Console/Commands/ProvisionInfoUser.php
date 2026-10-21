<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProvisionInfoUser extends Command
{
    protected $signature = 'users:info {--email=info@hemingway-hilden.de} {--set-password : Passwort verdeckt setzen}';

    protected $description = 'Teamkonto für Abrechnung, Gutscheine und Dienstplanung anlegen/einschränken';

    public function handle(): int
    {
        $email = strtolower(trim($this->option('email')));
        if (Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
            $this->error('Bitte eine gültige E-Mail-Adresse angeben.');

            return self::FAILURE;
        }
        $password = null;
        if ($this->option('set-password')) {
            $password = $this->secret('Neues Passwort (mindestens 12 Zeichen)');
            if (! is_string($password) || mb_strlen($password) < 12 || $password !== $this->secret('Passwort wiederholen')) {
                $this->error('Passwort zu kurz oder Bestätigung abweichend.');

                return self::FAILURE;
            }
        }
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first() ?? new User(['email' => $email, 'name' => 'Hemingway Team']);
        if (! $user->exists || $password !== null) {
            $user->password = Hash::make($password ?? Str::random(64));
        }
        $user->forceFill(['access_role' => 'operations', 'is_info_admin' => false, 'remember_token' => Str::random(60)])->save();
        $this->info('Teamkonto eingerichtet: '.$email);
        if ($password === null) {
            $this->line('Passwort bleibt erhalten. Bei neuen Konten über „Passwort vergessen?“ oder users:info --set-password festlegen.');
        }

        return self::SUCCESS;
    }
}
