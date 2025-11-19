<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ElasticsearchService;


class LogRequests
{
    protected $elasticsearchService;

    public function __construct(ElasticsearchService $elasticsearchService)
    {
        $this->elasticsearchService = $elasticsearchService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->segment(3);

        $data = [
            'headers' => $request->headers->all(),
            'token' => $request->segment(3),
            'method' => $request->method(),
            'query' => $request->query(),
            'body' => $request->all(),
            'body_raw' => file_get_contents('php://input'),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent')
        ];

        $this->elasticsearchService->indexRequest($data);

        return response()->json(['message' => 'Request capturada'], 200);
    }
}
