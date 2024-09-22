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

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable'); // commentable_id dan commentable_type
            $table->foreignId('user_id')->nullable();
            $table->foreignId('parent_id')->nullable();
            $table->string('name')->nullable();
            $table->longText('content')->nullable();
            $table->string('email')->nullable();
            $table->string('link')->nullable();
            $table->json('comment_meta')->nullable();
            $table->char('pinned',1)->default(0);
            $table->string('status')->index()->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
