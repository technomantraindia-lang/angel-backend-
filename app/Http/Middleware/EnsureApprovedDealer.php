<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class EnsureApprovedDealer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && $request->user()->role === 'dealer', 403);
        abort_unless($request->user()->approval_status === 'approved', 403, 'Dealer approval is pending.');
        return $next($request);
    }
}
