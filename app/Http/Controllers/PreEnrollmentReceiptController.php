<?php

namespace App\Http\Controllers;

use App\Models\PreEnrollmentPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PreEnrollmentReceiptController extends Controller
{
    public function show(PreEnrollmentPayment $preEnrollmentPayment): Response
    {
        if (! auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'treasurer'])) {
            abort(403, 'No tienes permiso para ver este recibo.');
        }

        $preEnrollmentPayment->load(['creator', 'relatedPayment']);

        $data = [
            'payment' => $preEnrollmentPayment,
            'payer_name' => $preEnrollmentPayment->payer_name,
            'paymentDescription' => $preEnrollmentPayment->concept,
            'paymentAmount' => $preEnrollmentPayment->amount,
            'career_name' => $preEnrollmentPayment->career_name,
            'paymentDate' => $preEnrollmentPayment->created_at,
            'receiptTitle' => $preEnrollmentPayment->type === 'refund' ? 'RECIBO DE DEVOLUCIÓN / REINTEGRO' : 'RECIBO DE PREINSCRIPCIÓN',
        ];

        $pdf = Pdf::loadView('pdf.paymentReceipt', compact('data'));

        $prefix = $preEnrollmentPayment->type === 'refund' ? 'reintegro-preinsc' : 'recibo-preinsc';

        return $pdf->stream($prefix.'-'.$preEnrollmentPayment->id.'.pdf');
    }
}
