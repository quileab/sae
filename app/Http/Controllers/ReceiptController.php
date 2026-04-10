<?php

namespace App\Http\Controllers;

use App\Models\PaymentRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function show(PaymentRecord $paymentRecord)
    {
        // Authorization check: Only admins or the owner of the payment record
        if (! auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'treasurer']) && auth()->id() !== $paymentRecord->user_id) {
            abort(403, 'No tienes permiso para ver este recibo.');
        }

        $user = $paymentRecord->user;
        $data = [
            'user' => $user,
            'payment' => $paymentRecord,
            'paymentDescription' => $paymentRecord->description,
            'paymentAmount' => $paymentRecord->paymentAmount,
            'paymentDate' => $paymentRecord->created_at,
        ];

        $pdf = Pdf::loadView('pdf.paymentReceipt', compact('data'));

        return $pdf->stream('receipt-'.$paymentRecord->id.'.pdf');
    }
}
