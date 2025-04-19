<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('groups');
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('avatar')->nullable();
            $table->string('cover_image')->nullable();
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->string('course_code')->nullable();
            $table->string('university')->nullable();
            $table->string('department')->nullable();
            $table->enum('type', ['public', 'private', 'course'])->default('public');
            $table->boolean('requires_approval')->default(false);
            $table->integer('member_count')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
