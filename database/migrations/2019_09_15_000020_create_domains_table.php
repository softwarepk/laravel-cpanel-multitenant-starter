<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table): void {
            $table->id();
            $table->string('domain')->unique();
            $table->string('tenant_id');
            $table->string('type')->default('platform');
            $table->string('status')->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('dns_verified_at')->nullable();
            $table->timestamp('cpanel_verified_at')->nullable();
            $table->timestamp('ssl_verified_at')->nullable();
            $table->text('verification_error')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->index(['tenant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
