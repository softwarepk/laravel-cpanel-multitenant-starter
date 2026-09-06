<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_deletion_records', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('tenant_name')->nullable();
            $table->string('database_name')->nullable();
            $table->string('platform_domain')->nullable();
            $table->json('custom_domains')->nullable();
            $table->foreignId('central_admin_id')->nullable()->constrained('central_admins')->nullOnDelete();
            $table->string('status')->default('started')->index();
            $table->json('cleanup_results')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_deletion_records');
    }
};
