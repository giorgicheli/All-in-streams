<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AccountVerificationController extends Controller
{
    public function pending(Request $request)
    {
        if ($request->user()->hasVerifiedPartnerAccount()) {
            return redirect(app(AuthController::class)->home($request->user()));
        }
        return response()->view('account.pending', [
            'rejected' => $request->user()->partner_verification_status === 'rejected',
        ])->header('Cache-Control', 'private, no-store');
    }
}