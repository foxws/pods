<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('environment_id')->constrained()->cascadeOnDelete();
            $table->string('application');
            $table->string('repository', 2048)->nullable();
            $table->string('reference')->nullable();
            $table->string('path')->nullable();
            $table->string('commit')->nullable();
            $table->text('manifest')->nullable();
            $table->string('manifest_path')->nullable();
            $table->json('checksums');
            $table->json('changes')->nullable();
            $table->json('setup')->nullable();
            $table->string('updated_from')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['environment_id', 'application']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installations');
    }
};
