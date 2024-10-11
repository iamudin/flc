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
            Schema::create('files', function (Blueprint $table) {
                $table->id();
                $table->string('file_path');
                $table->string('file_type');
                $table->string('file_name')->index();
                $table->string('host')->index();
                $table->string('purpose')->index()->nullable();
                $table->string('child_id')->index()->nullable();
                $table->string('file_size')->nullable();
                $table->bigInteger('file_auth')->nullable();
                $table->bigInteger('file_hits')->default(0);
                $table->bigInteger('user_id')->index()->nullable();
                $table->morphs('fileable'); // Akan membuat fileable_id dan fileable_type
                $table->timestamps();
            });
        }

        public function down()
        {
            Schema::dropIfExists('files');
        }
};
