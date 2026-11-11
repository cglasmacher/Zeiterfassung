<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            $table->string('normalized_name', 80)->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('reservation_booking_tag', function (Blueprint $table) {
            $table->foreignUuid('booking_id')->constrained('reservation_bookings')->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained('reservation_tags')->cascadeOnDelete();
            $table->primary(['booking_id', 'tag_id']);
        });
        foreach (['Geburtstag', 'Jubiläum', 'Hochzeit', 'Geschäftsessen', 'Familienfeier', 'Trauerfeier', 'Große Gruppe', 'Stammgast', 'VIP', 'Kinder', 'Baby', 'Kinderstuhl', 'Kinderwagen', 'Hund', 'Rollstuhl', 'Barrierefreier Platz', 'Außenplatz gewünscht', 'Fensterplatz gewünscht', 'Ruhiger Platz', 'Vegetarisch', 'Vegan', 'Allergien beachten', 'Glutenfrei', 'Laktosefrei', 'Englische Speisekarte', 'Zeitdruck', 'Menü vorbestellt', 'Küche informieren', 'Service informieren', 'Dekoration gewünscht'] as $name) {
            DB::table('reservation_tags')->insert(['id' => (string) Str::uuid(), 'name' => $name, 'normalized_name' => mb_strtolower($name), 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_booking_tag');
        Schema::dropIfExists('reservation_tags');
    }
};
