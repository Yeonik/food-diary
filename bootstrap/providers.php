<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\NutritionServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    NutritionServiceProvider::class,
];
