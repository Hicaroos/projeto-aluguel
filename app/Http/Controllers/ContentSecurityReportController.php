<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ContentSecurityReportController extends Controller
{
    /**
     * The parts of a report that tell what was blocked and where.
     *
     * @var list<string>
     */
    private const array FIELDS = ['document-uri', 'violated-directive', 'effective-directive', 'blocked-uri', 'source-file', 'line-number'];

    /**
     * Log what the browser reports the content policy blocked, or would block, on a page.
     */
    public function __invoke(Request $request): Response
    {
        $report = json_decode($request->getContent(), true);
        $violation = is_array($report) ? ($report['csp-report'] ?? null) : null;

        if (is_array($violation)) {
            Log::warning('Content security policy violation', Arr::only($violation, self::FIELDS));
        }

        return response()->noContent();
    }
}
