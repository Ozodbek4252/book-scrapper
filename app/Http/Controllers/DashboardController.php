<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Catalogue\CatalogueStatistics;
use App\Models\ScrapeRun;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(CatalogueStatistics $statistics): View
    {
        return view('dashboard', [
            'summary' => $statistics->summary(),
            'sources' => $statistics->sources(),
            'pendingSubmissions' => $statistics->pendingSubmissions(),
            'recentRuns' => ScrapeRun::query()
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
