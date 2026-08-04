<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_registrations', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 120);
            $table->string('phone', 30);
            $table->string('email', 190)->nullable();
            $table->string('country', 32);
            $table->string('city', 120);
            $table->string('vehicle_type', 120);
            $table->string('vehicle_model', 120);
            $table->unsignedSmallInteger('vehicle_year')->nullable();
            $table->string('service', 32);
            $table->string('preferred_branch', 64)->nullable();
            $table->text('notes')->nullable();
            $table->string('locale', 5)->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();

            $table->index(['country', 'created_at']);
            $table->index(['service', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_registrations');
    }
};
