<?php

namespace App\Http\Controllers\Api\v3;

use App\Actions\SendResponse;
use App\Http\Controllers\Controller;
use TestMate\Services\Agama\AgamaService;

/**
 * Agama controller
 * @author TestMate <dev@testmate.org>
 */
class AgamaController extends Controller
{
    /**
     * @Route(path="api/v3/agamas", method={"GET"})
     */
    public function index(AgamaService $agamaService)
    {
        $agamas = $agamaService->fetchAll();
        return SendResponse::acceptData($agamas ? $agamas : []);
    }
}
