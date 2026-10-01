<?php

namespace App\Services;

use App\Models\PreEnrollment;
use FPDM;

class PreEnrollmentPdfService
{
    private const TEMPLATE = 'resources/templates/sigae-4013-official.pdf';

    public function generate(PreEnrollment $preEnrollment): string
    {
        $payload = $preEnrollment->payload;
        $careerName = $preEnrollment->career->name ?? '';

        $data = [
            // Page 1 - Datos principales
            'establecimiento' => 'ISPI 4013, Padre Joaquín Bonaldo',
            'ciclo' => $preEnrollment->cycle_id,
            'carrera' => $careerName,
            'date' => date('d-m-Y'),
            'apeynom' => ($payload['lname'] ?? '').', '.($payload['fname'] ?? ''),
            'documento' => ($payload['doc_type'] ?? $preEnrollment->doc_type).' '.$preEnrollment->doc_number,
            'email' => $preEnrollment->email ?? ($payload['email'] ?? ''),
            'phone' => $preEnrollment->phone ?? ($payload['phone'] ?? ''),
            'sexM' => (($payload['sexo'] ?? '') === 'M') ? 'X' : '',
            'sexF' => (($payload['sexo'] ?? '') === 'F') ? 'X' : '',
            'fnacimiento' => isset($payload['fnacimiento']) ? date('d-m-Y', strtotime($payload['fnacimiento'])) : '',
            'nacionalidad' => $payload['nacionalidad'] ?? '',
            'nacimlocalid' => $payload['nacimlocalid'] ?? '',
            'nacimpais' => $payload['nacimpais'] ?? '',
            'estadocivil' => $payload['estadocivil'] ?? '',
            'puebloorig' => (($payload['puebloorig'] ?? '') === 'Si') ? 'X' : '',
            'etnia' => $payload['etnia'] ?? '',
            'comunrefer' => $payload['comunrefer'] ?? '',
            'dirStreet' => $payload['dirStreet'] ?? '',
            'dirNumber' => $payload['dirNumber'] ?? '',
            'dirFloor' => $payload['dirFloor'] ?? '',
            'dirAptmt' => $payload['dirAptmt'] ?? '',
            'dirBlock' => $payload['dirBlock'] ?? '',
            'dirMonoBlock' => $payload['dirMonoBlock'] ?? '',
            'dirNeighbor' => $payload['dirNeighbor'] ?? '',
            'dirCity' => $payload['dirCity'] ?? '',
            'discapacidad' => (($payload['discapacidad'] ?? '') === 'Si') ? 'X' : '',
            'tipodisc' => $payload['tipodisc'] ?? '',
            'ocupacion' => $payload['ocupacion'] ?? '',
            'ocupStreet' => $payload['ocupStreet'] ?? '',
            'ocupNumber' => $payload['ocupNumber'] ?? '',
            'ocupCity' => $payload['ocupCity'] ?? '',
            'ocupPhone' => $payload['ocupPhone'] ?? '',
            'ocuphorario' => $payload['ocuphorario'] ?? '',
            'ocup11' => (($payload['ocupestado'] ?? 0) == 0) ? 'X' : '',
            'ocup12' => (($payload['ocupestado'] ?? 0) == 1) ? 'X' : '',
            'ocup13' => (($payload['ocupestado'] ?? 0) == 2) ? 'X' : '',
            'ocup14' => (($payload['ocupestado'] ?? 0) == 3) ? 'X' : '',

            'respaspraSI' => (($payload['respaspra'] ?? false) === true) ? 'X' : '',
            'respaspraNO' => (($payload['respaspra'] ?? false) === true) ? '' : 'X',
            'regintestSI' => (($payload['regIntern'] ?? false) === true) ? 'X' : '',
            'regintestNO' => (($payload['regIntern'] ?? false) === true) ? '' : 'X',
            'regintfueSI' => (($payload['regInternFuera'] ?? false) === true) ? 'X' : '',
            'regintfueNO' => (($payload['regInternFuera'] ?? false) === true) ? '' : 'X',
            'ambrurSI' => (($payload['ambitorural'] ?? false) === true) ? 'X' : '',
            'ambrurNO' => (($payload['ambitorural'] ?? false) === true) ? '' : 'X',
            'conencSI' => (($payload['contexEncierro'] ?? false) === true) ? 'X' : '',
            'conencNO' => (($payload['contexEncierro'] ?? false) === true) ? '' : 'X',

            'observaciones' => $payload['observaciones'] ?? '',
            'motivoProcedencia' => $payload['motivoProcedencia'] ?? '',
            'titulo' => $payload['titulo'] ?? '',
            'titotorgado' => $payload['titotorgado'] ?? '',
            'tituloyear' => $payload['tityear'] ?? '',

            // Ficha Medica - Page 2+
            'apeynom1' => ($payload['lname'] ?? '').', '.($payload['fname'] ?? ''),
            'documento1' => ($payload['doc_type'] ?? $preEnrollment->doc_type).' '.$preEnrollment->doc_number,
            'fnacimiento1' => isset($payload['fnacimiento']) ? date('d-m-Y', strtotime($payload['fnacimiento'])) : '',
            'phone1' => $preEnrollment->phone ?? ($payload['phone'] ?? ''),
            'fulladdress' => ($payload['dirStreet'] ?? '').' nº'.($payload['dirNumber'] ?? '').
                ' (P.'.($payload['dirFloor'] ?? '').', Dpto. '.($payload['dirAptmt'] ?? '').
                ', Mza.'.($payload['dirBlock'] ?? '').'/'.($payload['dirMonoBlock'] ?? '').
                ', Bº.'.($payload['dirNeighbor'] ?? '').' Cdad. '.($payload['dirCity'] ?? '').')',
        ];

        $templatePath = resource_path('templates/sigae-4013-official.pdf');

        $pdf = new FPDM($templatePath);
        $pdf->useCheckboxParser = true;
        $pdf->Load($data, true);
        $pdf->Merge();

        $path = 'private/pre_enrollments/pre-'.$preEnrollment->id.'.pdf';
        $fullPath = storage_path('app/'.$path);
        if (! is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        $pdf->Output('F', $fullPath);

        return $path;
    }
}
