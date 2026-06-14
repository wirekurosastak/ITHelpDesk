<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds an approval gate so newly registered users cannot log in
     * until an Admin explicitly approves their account.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Default false: every new registration starts as pending
            $table->boolean('is_approved')->default(false)->after('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_approved');
        });
    }
};
