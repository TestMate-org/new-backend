<?php

namespace App\Http\Controllers\Api\v1;

use App\Actions\SendResponse;
use App\Agama;
use App\Http\Controllers\Controller;

/**
 * Agama controller
 * @author TestMate <dev@testmate.org>
 */
class AgamaController extends Controller
{
    /**
     * @Route(path="api/v1/agamas", method={"GET"})
     */
    public function index()
    {
        $agamas = Agama::orderBy('id')->get();
        return SendResponse::acceptData($agamas);
    }
}
