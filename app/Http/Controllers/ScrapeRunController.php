<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ScrapeRun;
use Illuminate\View\View;

class ScrapeRunController extends Controller
{
    public function index(): View
    {
        return view('scrape-runs.index', [
            'runs' => ScrapeRun::query()
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function show(ScrapeRun $scrapeRun): View
    {
        return view('scrape-runs.show', [
            'run' => $scrapeRun,
            'errors' => $scrapeRun->errors()->latest('id')->paginate(50),
        ]);
    }
}
