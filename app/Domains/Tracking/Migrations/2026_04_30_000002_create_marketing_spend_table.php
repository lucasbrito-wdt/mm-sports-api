<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_spend', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date');
            $table->string('canal', 100);
            $table->integer('valor_cents');
            $table->timestamps();

            $table->index(['date', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_spend');
    }
};
