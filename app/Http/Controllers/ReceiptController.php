<?php

namespace App\Http\Controllers;

use App\Models\PaymentRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class ReceiptController extends Controller
{
    public function show(PaymentRecord $paymentRecord)
    {
        $user = $paymentRecord->user;
        $data = [
            'user' => $user,
            'payment' => $paymentRecord,
            'paymentDescription' => $paymentRecord->description,
            'paymentAmount' => $paymentRecord->paymentAmount,
            'paymentDate' => $paymentRecord->created_at,
        ];

        $pdf = Pdf::loadView('pdf.paymentReceipt', compact('data'));

        return $pdf->stream('receipt-' . $paymentRecord->id . '.pdf');
    }
}
