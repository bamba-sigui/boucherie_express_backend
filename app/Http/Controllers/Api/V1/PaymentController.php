<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    // Sprint 4 — CinetPay (à implémenter)
    public function initialize(Request $request)
    {
        return $this->fail('Paiement non encore configuré', 501);
    }

    public function status(Request $request, string $ref)
    {
        return $this->fail('Paiement non encore configuré', 501);
    }

    public function webhook(Request $request)
    {
        return response('OK', 200);
    }
}
