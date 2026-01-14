<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ElasticsearchService;
use App\Services\DatabaseService;

class LogRequests
{
    protected $elasticsearchService;
    protected $databaseService;


    public function __construct(ElasticsearchService $elasticsearchService, DatabaseService $databaseService)
    {
        $this->elasticsearchService = $elasticsearchService;
        $this->databaseService = $databaseService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->segment(3);

        $parsing_data=$this->databaseService->dataParsing($token, $request->all());

        $data = [
            'headers' => $request->headers->all(),
            'token' => $request->segment(3),
            'method' => $request->method(),
            'query' => $request->query(),
            'body' => $request->all(),
            'body_calculated' => $parsing_data,
            'body_raw' => file_get_contents('php://input'),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent')
        ];

        $this->elasticsearchService->indexRequest($data);

        return response()->json(['message' => 'Request capturada'], 200);
    }
}
