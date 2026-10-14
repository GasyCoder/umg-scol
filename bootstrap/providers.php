<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\StudentPortalServiceProvider;

return [
    AppServiceProvider::class,
    StudentPortalServiceProvider::class,
    FortifyServiceProvider::class,
];
