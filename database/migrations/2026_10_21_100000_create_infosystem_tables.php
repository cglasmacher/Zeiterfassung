<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_info_admin')->default(false));
        // Existing accounts already administer the application; new registrations do not gain access.
        \Illuminate\Support\Facades\DB::table('users')->update(['is_info_admin' => true]);
        Schema::create('info_displays', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->string('name');
            $t->string('token_hash')->nullable();
            $t->boolean('active')->default(true);
            $t->boolean('show_guest_names')->default(false);
            $t->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });
        Schema::create('info_items', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 20)->index();
            $t->string('title');
            $t->text('body')->nullable();
            $t->foreignId('display_id')->nullable()->constrained('info_displays')->cascadeOnDelete();
            $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('starts_at')->nullable()->index();
            $t->timestamp('ends_at')->nullable();
            $t->timestamp('due_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->unsignedTinyInteger('priority')->default(0);
            $t->boolean('active')->default(true);
            $t->string('media_type', 20)->nullable();
            $t->text('url')->nullable();
            $t->string('file_path')->nullable();
            $t->unsignedInteger('duration')->default(20);
            $t->unsignedInteger('sort_order')->default(0);
            $t->timestamps();
        });
        Schema::create('info_integrations', function (Blueprint $t) {
            $t->id();
            $t->string('facility')->nullable();
            $t->text('authorization')->nullable();
            $t->string('webhook_user')->nullable();
            $t->text('webhook_password')->nullable();
            $t->boolean('enabled')->default(false);
            $t->timestamp('last_success_at')->nullable();
            $t->timestamp('last_attempt_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
        });
        Schema::create('info_reservations', function (Blueprint $t) {
            $t->id();
            $t->string('external_id')->unique();
            $t->string('facility');
            $t->timestamp('starts_at')->index();
            $t->unsignedInteger('people');
            $t->string('guest_name')->nullable();
            $t->string('tables')->nullable();
            $t->string('status')->default('confirmed');
            $t->timestamps();
        });
        \Illuminate\Support\Facades\DB::table('info_displays')->insert([
            'slug' => 'kitchen', 'name' => 'Küche', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_info_admin'));
        foreach (['info_reservations', 'info_integrations', 'info_items', 'info_displays'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
