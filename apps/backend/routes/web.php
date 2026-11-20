<?php

use App\Modules\Recruitment\Services\CareersService;
use Illuminate\Support\Facades\Route;

Route::get('/careers/jobs/{slug}', function (string $slug, CareersService $careers) {
    $job = $careers->job($slug);
    $title = $job['title'].' at '.$job['company'];
    $description = trim(mb_substr(strip_tags((string) ($job['description'] ?? '')), 0, 200));
    $origin = rtrim((string) config('app.frontend_url', config('app.url')), '/');

    return response()->view('careers.job', [
        'title' => $title,
        'description' => $description !== '' ? $description : $title,
        'url' => $origin.'/careers/jobs/'.$job['slug'],
        'image' => $job['company_logo'] ? url($job['company_logo']) : null,
    ]);
});

Route::get('/', function () {
    return response()->json([
        'data' => [
            'name' => config('app.name'),
        ],
    ]);
});
