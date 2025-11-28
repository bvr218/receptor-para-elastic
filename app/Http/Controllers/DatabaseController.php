<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DatabaseService;

class DatabaseController extends Controller
{
    protected $databaseService;

    public function __construct(DatabaseService $databaseService)
    {
        $this->databaseService = $databaseService;
    }

    public function getDevice(Request $request){
        return $this->databaseService->getDeviceByToken();
    }

}
