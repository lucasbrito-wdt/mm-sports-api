<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rg', 20)->nullable()->after('cpf');
            $table->char('gender', 1)->nullable()->after('rg');
            $table->date('birthdate')->nullable()->after('gender');
            $table->string('favorite_team', 64)->nullable()->after('birthdate');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['rg', 'gender', 'birthdate', 'favorite_team']);
        });
    }
};
