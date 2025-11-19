<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ElasticsearchService;

class ApiRequestController extends Controller
{
    protected $elasticsearchService;

    public function __construct(ElasticsearchService $elasticsearchService)
    {
        $this->elasticsearchService = $elasticsearchService;
    }

    public function captureRequest(Request $request)
    {
        $token = $request->segment(3);

        $data = [
            'token' => $token,
            'headers' => $request->headers->all(),
            'method' => $request->method(),
            'query' => $request->query(),
            'body' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent')
        ];

        $this->elasticsearchService->indexRequest($data);

        return response()->json(['message' => 'Request capturada'], 200);
    }
}
