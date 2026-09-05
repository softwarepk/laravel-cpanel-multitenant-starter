<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('central_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('central_admin_id')->nullable()->constrained('central_admins')->nullOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('action')->index();
            $table->text('description')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('central_audit_logs');
    }
};
