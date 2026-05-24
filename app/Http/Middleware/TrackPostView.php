<?php

namespace App\Http\Middleware;

use App\Models\Post;
use App\Services\ViewTrackingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPostView
{
    public function __construct(private ViewTrackingService $tracker) {}

    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($response->getStatusCode() !== 200) {
            return;
        }

        $post = $request->attributes->get('tracked_post');
        if (! $post instanceof Post) {
            return;
        }

        try {
            $this->tracker->track(
                $post,
                $request->ip() ?? '0.0.0.0',
                $request->userAgent() ?? '',
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
