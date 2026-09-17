<?php

namespace App\Http\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthController
{
    public function __invoke(Request $request): JsonResponse
    {
        $url = rtrim((string) config('app.url'), '/');
        $requested = $request->query('url', $request->query('backendurl'));
        $requested = is_string($requested) && $requested !== '' ? rtrim($requested, '/') : null;

        $database = 'ok';
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
        } catch (\Throwable) {
            $database = 'error';
        }

        $ok = $database === 'ok';

        $payload = [
            'ok' => $ok,
            'status' => $ok ? 'ok' : 'degraded',
            'app' => config('app.name'),
            'env' => config('app.env'),
            'url' => $url,
            'database' => $database,
        ];

        if ($requested !== null) {
            $payload['requested_url'] = $requested;
            $payload['url_matches'] = strcasecmp($requested, $url) === 0;
        }

        return response()->json($payload, $ok ? 200 : 503);
    }
}
