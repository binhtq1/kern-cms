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
            if (setting('member_enable_recaptcha_in_register_page')) {
                DB::table('settings')->insertOrIgnore([
                    ...(BaseModel::isUsingStringId() ? ['id' => (new BaseModel)->newUniqueId()] : []),
                    'key' => 'enable_recaptcha_kern_member_forms_fronts_auth_register_form',
                    'value' => '1',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            if (setting('member_enable_math_captcha_in_register_page')) {
                DB::table('settings')->insertOrIgnore([
                    ...(BaseModel::isUsingStringId() ? ['id' => (new BaseModel)->newUniqueId()] : []),
                    'key' => 'enable_math_captcha_kern_member_forms_fronts_auth_register_form',
                    'value' => '1',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        });
    }
};
