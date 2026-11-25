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
        Schema::create('stimuli', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->onDelete('cascade'); // প্যারেন্ট প্রশ্নের সাথে সংযোগ
            $table->enum('stimulus_type', ['text', 'image', 'audio', 'video', 'table']);
            $table->text('content'); // উদ্দীপকের মূল কন্টেন্ট
            $table->string('media_url')->nullable(); // ইমেজ/অডিও/ভিডিওর জন্য
            $table->text('caption')->nullable(); // ক্যাপশন বা বিবরণ
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stimuli');
    }
};
