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
        Schema::create('sub_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_question_id')->constrained('questions')->onDelete('cascade');
            $table->foreignId('stimulus_id')->nullable()->constrained()->onDelete('set null'); // কোন উদ্দীপকের সাথে যুক্ত
            $table->enum('format', ['mcq', 'cq']); // সাব-প্রশ্নের ফরম্যাট
            $table->text('question_text'); // যেমন: "1. প্রশ্নটি পড়ে উত্তর দাও"
            $table->text('explanation')->nullable();
            $table->text('mark')->nullable();
            $table->integer('question_number')->default(1); // 1, 2, 3, 4... 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sub_questions');
    }
};
