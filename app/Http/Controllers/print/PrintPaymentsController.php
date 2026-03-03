<?php

namespace App\Http\Controllers\print;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPayments;
use App\Models\PaymentRecord;
use Illuminate\Http\Request;

class PrintPaymentsController extends Controller
{
    public function summary(User $user)
    {
        // Authorization check - only admins or the student themselves
        if (!auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative']) && auth()->id() !== $user->id) {
            abort(403);
        }

        $payments = UserPayments::where('user_id', $user->id)
            ->orderBy('date', 'asc')
            ->get();

        $receipts = PaymentRecord::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('print.user-payments-summary', compact('user', 'payments', 'receipts'));
    }
}
