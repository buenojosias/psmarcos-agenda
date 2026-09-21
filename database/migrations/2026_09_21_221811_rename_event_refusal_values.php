<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('events')
            ->where('status', 'rejected')
            ->update(['status' => 'refused']);

        DB::table('event_logs')
            ->where('action', 'rejected')
            ->update(['action' => 'refused']);

        DB::table('event_logs')
            ->where('from_status', 'rejected')
            ->update(['from_status' => 'refused']);

        DB::table('event_logs')
            ->where('to_status', 'rejected')
            ->update(['to_status' => 'refused']);
    }

    public function down(): void
    {
        DB::table('events')
            ->where('status', 'refused')
            ->update(['status' => 'rejected']);

        DB::table('event_logs')
            ->where('action', 'refused')
            ->update(['action' => 'rejected']);

        DB::table('event_logs')
            ->where('from_status', 'refused')
            ->update(['from_status' => 'rejected']);

        DB::table('event_logs')
            ->where('to_status', 'refused')
            ->update(['to_status' => 'rejected']);
    }
};
