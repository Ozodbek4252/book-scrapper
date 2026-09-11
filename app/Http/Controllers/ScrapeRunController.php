<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ScrapeRun;
use App\Scraping\SourceRegistry;
use App\Scraping\StartScrapeRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScrapeRunController extends Controller
{
    public function index(SourceRegistry $registry): View
    {
        return view('scrape-runs.index', [
            'runs' => ScrapeRun::query()
                ->latest('id')
                ->paginate(20),
            'sourceKeys' => $registry->runnableKeys(),
        ]);
    }

    public function store(Request $request, SourceRegistry $registry, StartScrapeRun $startRun): RedirectResponse
    {
        $validated = $request->validate([
            'source_key' => ['required', 'string', Rule::in($registry->runnableKeys())],
        ]);

        $run = $startRun->handle($validated['source_key']);

        return redirect()
            ->route('scrape-runs.show', $run)
            ->with('status', "Queued a run for {$run->source_key}.");
    }

    public function show(ScrapeRun $scrapeRun): View
    {
        return view('scrape-runs.show', [
            'run' => $scrapeRun,
            'errors' => $scrapeRun->errors()->latest('id')->paginate(50),
        ]);
    }
}
