<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Kern\Base\Models\BaseModel;

return new class extends Migration
{
    public function up(): void
    {
        rescue(function (): void {
            $value = 0;
            if (setting('analytics_property_id') && setting('analytics_service_account_credentials')) {
                $value = 1;
            }

            DB::table('settings')->insertOrIgnore([
                ...(BaseModel::isUsingStringId() ? ['id' => (new BaseModel)->newUniqueId()] : []),
                'key' => 'analytics_dashboard_widgets',
                'value' => $value,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        });
    }
};
