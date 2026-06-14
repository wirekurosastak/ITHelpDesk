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
        if (! Schema::hasTable('attachments')) {
            return;
        }

        Schema::table('attachments', function (Blueprint $table): void {
            if (! Schema::hasColumn('attachments', 'uploaded_by')) {
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('attachments', 'mime_type')) {
                $table->string('mime_type')->nullable();
            }

            if (! Schema::hasColumn('attachments', 'size')) {
                $table->unsignedBigInteger('size')->default(0);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('attachments')) {
            return;
        }

        Schema::table('attachments', function (Blueprint $table): void {
            if (Schema::hasColumn('attachments', 'uploaded_by')) {
                $table->dropConstrainedForeignId('uploaded_by');
            }

            if (Schema::hasColumn('attachments', 'mime_type')) {
                $table->dropColumn('mime_type');
            }

            if (Schema::hasColumn('attachments', 'size')) {
                $table->dropColumn('size');
            }
        });
    }
};
