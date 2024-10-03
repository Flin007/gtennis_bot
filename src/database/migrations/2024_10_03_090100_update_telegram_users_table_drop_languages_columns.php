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
        $this->dropColumnIfExist('telegram_users', 'selected_language');
        $this->dropColumnIfExist('telegram_users', 'language_code');
    }

    function dropColumnIfExist($myTable, $column)
    {
        if (Schema::hasColumn($myTable, $column))
        {
            Schema::table($myTable, function (Blueprint $table) use ($column)
            {
                $table->dropColumn($column);
            });
        }

    }
};
