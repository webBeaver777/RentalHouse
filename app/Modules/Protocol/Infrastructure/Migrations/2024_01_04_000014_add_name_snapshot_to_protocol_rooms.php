<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * G5: Add frozen name snapshot to protocol rooms.
 *
 * Room names must be immutable after protocol creation
 * to prevent catalog changes from affecting historical documents.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('protocol_rooms', function (Blueprint $table): void {
            $table->jsonb('name_snapshot')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('protocol_rooms', function (Blueprint $table): void {
            $table->dropColumn('name_snapshot');
        });
    }
};
