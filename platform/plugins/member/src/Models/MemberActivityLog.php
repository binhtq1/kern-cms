<?php

namespace Kern\Member\Models;

use Kern\Base\Casts\SafeContent;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\Html;
use Kern\Base\Models\BaseModel;

class MemberActivityLog extends BaseModel
{
    protected $table = 'member_activity_logs';

    protected $fillable = [
        'action',
        'user_agent',
        'reference_url',
        'reference_name',
        'ip_address',
        'member_id',
    ];

    protected $casts = [
        'action' => SafeContent::class,
    ];

    protected static function booted(): void
    {
        self::creating(function ($model): void {
            $model->user_agent = $model->user_agent ?: request()->userAgent();
            $model->ip_address = $model->ip_address ?: request()->ip();
            $model->member_id = $model->member_id ?: auth('member')->id();

            if ($model->reference_ur) {
                $model->reference_url = str_replace(BaseHelper::getHomepageUrl(), '', $model->reference_url);
            }
        });
    }

    public function getDescription(): string
    {
        $name = $this->reference_name;
        if ($this->reference_name && $this->reference_url) {
            $name = Html::link($this->reference_url, $this->reference_name, ['style' => 'color: #1d9977']);
        }

        $time = Html::tag('span', $this->created_at->diffForHumans(), ['class' => 'small italic']);

        return trans('plugins/member::dashboard.actions.'.$this->action, ['name' => $name]).' . '.$time;
    }
}
