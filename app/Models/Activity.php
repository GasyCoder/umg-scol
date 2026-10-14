<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use Spatie\Activitylog\Models\Activity as BaseActivity;

/** Audit log entry, identified in URLs by its public uuid (configured as activitylog.activity_model). */
class Activity extends BaseActivity
{
    use HasPublicUuid;
}
