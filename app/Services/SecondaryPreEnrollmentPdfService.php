<?php

namespace App\Services;

use App\Models\PreEnrollment;
use FPDM;

class SecondaryPreEnrollmentPdfService
{
    public function generate(PreEnrollment $preEnrollment): string
    {
        $payload = $preEnrollment->payload;
        
        // Determinar "encargado" para campos CM
        $encargado = 'Padre / Madre';
        $cbFullname = '';
        $cbId = '';
        $cbEmail = '';
        $cbPhone = '';
        
        if ($payload['padremadre1_firma'] ?? false) {
            $cbFullname = ucwords(strtolower(($payload['lname1'] ?? '') . ", " . ($payload['fname1'] ?? '')));
            $cbId = ($payload['doctipo1'] ?? '') . ": " . ($payload['docnum1'] ?? '');
            $cbEmail = $payload['email1'] ?? '';
            $cbPhone = $payload['phone1'] ?? '';
        } elseif ($payload['padremadre2_firma'] ?? false) {
            $cbFullname = ucwords(strtolower(($payload['lname2'] ?? '') . ", " . ($payload['fname2'] ?? '')));
            $cbId = ($payload['doctipo2'] ?? '') . ": " . ($payload['docnum2'] ?? '');
            $cbEmail = $payload['email2'] ?? '';
            $cbPhone = $payload['phone2'] ?? '';
        } elseif ($payload['tutor_firma'] ?? false) {
            $encargado = 'Tutor';
            $cbFullname = ucwords(strtolower(($payload['lname3'] ?? '') . ", " . ($payload['fname3'] ?? '')));
            $cbId = ($payload['doctipo3'] ?? '') . ": " . ($payload['docnum3'] ?? '');
            $cbEmail = $payload['email3'] ?? '';
            $cbPhone = $payload['phone3'] ?? '';
        }
        
        $data = [
            'inscription_schoolyear' => $preEnrollment->cycle_id,
            'insc_escuela_procedencia' => ($payload['escuela'] ?? '') . " / División:" . ($payload['division'] ?? '') . " / Turno:" . ($payload['turno'] ?? ''),
            'inscription_date' => date('d-m-Y'),
            'repeater_Yes' => (($payload['repitente'] ?? 'No') == 'Si') ? 'X' : '',
            'repeater_No' => (($payload['repitente'] ?? 'No') != 'Si') ? 'X' : '',
            'student_fullname' => ucwords(strtolower(($payload['lname'] ?? '') . ", " . ($payload['fname'] ?? ''))),
            'student_id' => ($payload['doc_type'] ?? $preEnrollment->doc_type) . ': ' . $preEnrollment->doc_number,
            'student_email' => $preEnrollment->email ?? ($payload['email'] ?? ''),
            'student_phone' => $preEnrollment->phone ?? ($payload['phone'] ?? ''),
            'student_sexM' => (($payload['sexo'] ?? '') == 'M') ? 'X' : '',
            'student_sexF' => (($payload['sexo'] ?? '') == 'F') ? 'X' : '',
            'student_date_of_birth' => isset($payload['fnacimiento']) ? date("d-m-Y", strtotime($payload['fnacimiento'])) : '',
            'student_current_nationality' => $payload['nacionalidad'] ?? '',
            'student_city_of_birth' => $payload['nacimlocalid'] ?? '',
            'student_country_of_birth' => $payload['nacimpais'] ?? '',
            'student_marital_status' => $payload['estadocivil'] ?? '',
            'student_native' => $payload['puebloorig'] ?? '',
            'student_ethnic' => $payload['etnia'] ?? '',
            'student_community_referer' => $payload['comunrefer'] ?? '',
            'student_home_street' => $payload['dirStreet'] ?? '',
            'student_home_number' => $payload['dirNumber'] ?? '',
            'student_home_floor' => $payload['dirFloor'] ?? '',
            'student_home_apartment' => $payload['dirAptmt'] ?? '',
            'student_home_block' => $payload['dirBlock'] ?? '',
            'student_home_monoblock' => $payload['dirMonoBlock'] ?? '',
            'student_home_neighborhood' => $payload['dirNeighbor'] ?? '',
            'student_home_city' => $payload['dirCity'] ?? '',
            'student_has_disability' => (($payload['discapacidad'] ?? 'No') == 'Si') ? 'X' : '',
            'student_disability' => $payload['tipodisc'] ?? '',
            'student_has_cud' => (($payload['cud'] ?? 'No') == 'Si') ? 'X' : '',
            'student_cud_expiry_date' => (isset($payload['cud_expiry_date']) && !empty($payload['cud_expiry_date'])) ? date("d-m-Y", strtotime($payload['cud_expiry_date'])) : '',
            'student_disability_school' => $payload['disability_school'] ?? '',
            'student_disability_school_start_date' => (isset($payload['disability_school_start_date']) && !empty($payload['disability_school_start_date'])) ? date("d-m-Y", strtotime($payload['disability_school_start_date'])) : '',

            // Padre / Madre #1
            'parent1_fullname' => ucwords(strtolower(($payload['lname1'] ?? '') . ", " . ($payload['fname1'] ?? ''))),
            'parent1_id' => ($payload['doctipo1'] ?? '') . ": " . ($payload['docnum1'] ?? ''),
            'parent1_email' => $payload['email1'] ?? '',
            'parent1_phone' => $payload['phone1'] ?? '',
            'parent1_sexM' => (($payload['sexo1'] ?? '') == 'M') ? 'X' : '',
            'parent1_sexF' => (($payload['sexo1'] ?? '') == 'F') ? 'X' : '',
            'parent1_sexX' => (($payload['sexo1'] ?? '') == 'X') ? 'X' : '',
            'parent1_date_of_birth' => isset($payload['fnacimiento1']) && !empty($payload['fnacimiento1']) ? date("d-m-Y", strtotime($payload['fnacimiento1'])) : '',
            'parent1_current_nationality' => $payload['nacionalidad1'] ?? '',
            'parent1_city_of_birth' => $payload['nacimlocalid1'] ?? '',
            'parent1_country_of_birth' => $payload['nacimpais1'] ?? '',
            'parent1_marital_status' => $payload['estadocivil1'] ?? '',
            'parent1_native' => $payload['puebloorig1'] ?? '',
            'parent1_ethnic' => $payload['etnia1'] ?? '',
            'parent1_community_referer' => $payload['comunrefer1'] ?? '',
            'parent1_alive_yes' => (($payload['fallecido1'] ?? 'No') == 'Si') ? 'X' : '',
            'parent1_alive_no' => (($payload['fallecido1'] ?? 'No') == 'No') ? 'X' : '',
            'parent1_decease_date' => (($payload['fallecido1'] ?? 'No') == 'Si' && !empty($payload['fechFall1'])) ? date("d-m-Y", strtotime($payload['fechFall1'])) : '',
            'parent1_education_level' => $payload['nivelInstr1'] ?? '',
            'parent1_home_street' => $payload['dirStreet1'] ?? '',
            'parent1_home_number' => $payload['dirNumber1'] ?? '',
            'parent1_home_floor' => $payload['dirFloor1'] ?? '',
            'parent1_home_apartment' => $payload['dirAptmt1'] ?? '',
            'parent1_home_block' => $payload['dirBlock1'] ?? '',
            'parent1_home_monoblock' => $payload['dirMonoBlock1'] ?? '',
            'parent1_home_neighborhood' => $payload['dirNeighbor1'] ?? '',
            'parent1_home_city' => $payload['dirCity1'] ?? '',
            'parent1_occupation' => $payload['ocupacion1'] ?? '',
            'parent1_occupation_street' => $payload['ocupStreet1'] ?? '',
            'parent1_occupation_number' => $payload['ocupNumber1'] ?? '',
            'parent1_occupation_city' => $payload['ocupCity1'] ?? '',
            'parent1_occupation_phone' => $payload['ocupPhone1'] ?? '',
            'parent1_occupation_work_schedule' => $payload['ocuphorario1'] ?? '',
            'parent1_occupation_type1' => (isset($payload['ocupestado1']) && (int)$payload['ocupestado1'] === 0) ? 'X' : '',
            'parent1_occupation_type2' => (isset($payload['ocupestado1']) && (int)$payload['ocupestado1'] === 1) ? 'X' : '',
            'parent1_occupation_type3' => (isset($payload['ocupestado1']) && (int)$payload['ocupestado1'] === 2) ? 'X' : '',
            'parent1_occupation_type4' => (isset($payload['ocupestado1']) && (int)$payload['ocupestado1'] === 3) ? 'X' : '',

            // Padre / Madre #2
            'parent2_fullname' => ucwords(strtolower(($payload['lname2'] ?? '') . ", " . ($payload['fname2'] ?? ''))),
            'parent2_id' => ($payload['doctipo2'] ?? '') . ": " . ($payload['docnum2'] ?? ''),
            'parent2_email' => $payload['email2'] ?? '',
            'parent2_phone' => $payload['phone2'] ?? '',
            'parent2_sexM' => (($payload['sexo2'] ?? '') == 'M') ? 'X' : '',
            'parent2_sexF' => (($payload['sexo2'] ?? '') == 'F') ? 'X' : '',
            'parent2_sexX' => (($payload['sexo2'] ?? '') == 'X') ? 'X' : '',
            'parent2_date_of_birth' => isset($payload['fnacimiento2']) && !empty($payload['fnacimiento2']) ? date("d-m-Y", strtotime($payload['fnacimiento2'])) : '',
            'parent2_current_nationality' => $payload['nacionalidad2'] ?? '',
            'parent2_city_of_birth' => $payload['nacimlocalid2'] ?? '',
            'parent2_country_of_birth' => $payload['nacimpais2'] ?? '',
            'parent2_marital_status' => $payload['estadocivil2'] ?? '',
            'parent2_native' => $payload['puebloorig2'] ?? '',
            'parent2_ethnic' => $payload['etnia2'] ?? '',
            'parent2_community_referer' => $payload['comunrefer2'] ?? '',
            'parent2_alive_yes' => (($payload['fallecido2'] ?? 'No') == 'Si') ? 'X' : '',
            'parent2_alive_no' => (($payload['fallecido2'] ?? 'No') == 'No') ? 'X' : '',
            'parent2_decease_date' => (($payload['fallecido2'] ?? 'No') == 'Si' && !empty($payload['fechFall2'])) ? date("d-m-Y", strtotime($payload['fechFall2'])) : '',
            'parent2_education_level' => $payload['nivelInstr2'] ?? '',
            'parent2_home_street' => $payload['dirStreet2'] ?? '',
            'parent2_home_number' => $payload['dirNumber2'] ?? '',
            'parent2_home_floor' => $payload['dirFloor2'] ?? '',
            'parent2_home_apartment' => $payload['dirAptmt2'] ?? '',
            'parent2_home_block' => $payload['dirBlock2'] ?? '',
            'parent2_home_monoblock' => $payload['dirMonoBlock2'] ?? '',
            'parent2_home_neighborhood' => $payload['dirNeighbor2'] ?? '',
            'parent2_home_city' => $payload['dirCity2'] ?? '',
            'parent2_occupation' => $payload['ocupacion2'] ?? '',
            'parent2_occupation_street' => $payload['ocupStreet2'] ?? '',
            'parent2_occupation_number' => $payload['ocupNumber2'] ?? '',
            'parent2_occupation_city' => $payload['ocupCity2'] ?? '',
            'parent2_occupation_phone' => $payload['ocupPhone2'] ?? '',
            'parent2_occupation_work_schedule' => $payload['ocuphorario2'] ?? '',
            'parent2_occupation_type1' => (isset($payload['ocupestado2']) && (int)$payload['ocupestado2'] === 0) ? 'X' : '',
            'parent2_occupation_type2' => (isset($payload['ocupestado2']) && (int)$payload['ocupestado2'] === 1) ? 'X' : '',
            'parent2_occupation_type3' => (isset($payload['ocupestado2']) && (int)$payload['ocupestado2'] === 2) ? 'X' : '',
            'parent2_occupation_type4' => (isset($payload['ocupestado2']) && (int)$payload['ocupestado2'] === 3) ? 'X' : '',

            // TUTOR #3
            'tutor_fullname' => ucwords(strtolower(($payload['lname3'] ?? '') . ", " . ($payload['fname3'] ?? ''))),
            'tutor_id' => ($payload['doctipo3'] ?? '') . ": " . ($payload['docnum3'] ?? ''),
            'tutor_email' => $payload['email3'] ?? '',
            'tutor_phone' => $payload['phone3'] ?? '',
            'tutor_sexM' => (($payload['sexo3'] ?? '') == 'M') ? 'X' : '',
            'tutor_sexF' => (($payload['sexo3'] ?? '') == 'F') ? 'X' : '',
            'tutor_sexX' => (($payload['sexo3'] ?? '') == 'X') ? 'X' : '',
            'tutor_date_of_birth' => isset($payload['fnacimiento3']) && !empty($payload['fnacimiento3']) ? date("d-m-Y", strtotime($payload['fnacimiento3'])) : '',
            'tutor_current_nationality' => $payload['nacionalidad3'] ?? '',
            'tutor_city_of_birth' => $payload['nacimlocalid3'] ?? '',
            'tutor_country_of_birth' => $payload['nacimpais3'] ?? '',
            'tutor_marital_status' => $payload['estadocivil3'] ?? '',
            'tutor_native' => $payload['puebloorig3'] ?? '',
            'tutor_ethnic' => $payload['etnia3'] ?? '',
            'tutor_community_referer' => $payload['comunrefer3'] ?? '',
            'tutor_education_level' => $payload['nivelInstr3'] ?? '',
            'tutor_relationship' => $payload['parentesco'] ?? '',
            'tutor_tutor' => (($payload['tutorencargado'] ?? '') == 'Tutor') ? 'X' : '',
            'tutor_manager' => (($payload['tutorencargado'] ?? '') == 'Encargado') ? 'X' : '',
            'tutor_home_street' => $payload['dirStreet3'] ?? '',
            'tutor_home_number' => $payload['dirNumber3'] ?? '',
            'tutor_home_floor' => $payload['dirFloor3'] ?? '',
            'tutor_home_apartment' => $payload['dirAptmt3'] ?? '',
            'tutor_home_block' => $payload['dirBlock3'] ?? '',
            'tutor_home_monoblock' => $payload['dirMonoBlock3'] ?? '',
            'tutor_home_neighborhood' => $payload['dirNeighbor3'] ?? '',
            'tutor_home_city' => $payload['dirCity3'] ?? '',
            'tutor_occupation' => $payload['ocupacion3'] ?? '',
            'tutor_occupation_street' => $payload['ocupStreet3'] ?? '',
            'tutor_occupation_number' => $payload['ocupNumber3'] ?? '',
            'tutor_occupation_city' => $payload['ocupCity3'] ?? '',
            'tutor_occupation_phone' => $payload['ocupPhone3'] ?? '',
            'tutor_occupation_work_schedule' => $payload['ocuphorario3'] ?? '',
            'tutor_occupation_type1' => (isset($payload['ocupestado3']) && (int)$payload['ocupestado3'] === 0) ? 'X' : '',
            'tutor_occupation_type2' => (isset($payload['ocupestado3']) && (int)$payload['ocupestado3'] === 1) ? 'X' : '',
            'tutor_occupation_type3' => (isset($payload['ocupestado3']) && (int)$payload['ocupestado3'] === 2) ? 'X' : '',
            'tutor_occupation_type4' => (isset($payload['ocupestado3']) && (int)$payload['ocupestado3'] === 3) ? 'X' : '',
            
            // Hoja 3 / Insc
            'insc_at_hosp_dom_ins_yes' => ($payload['atencHospitIns'] ?? false) ? 'X' : '',
            'insc_at_hosp_dom_ins_no' => !($payload['atencHospitIns'] ?? false) ? 'X' : '',
            'insc_sala5_yes' => ($payload['salacinco'] ?? false) ? 'X' : '',
            'insc_sala5_no' => !($payload['salacinco'] ?? false) ? 'X' : '',
            'insc_reg_inter_yes' => ($payload['regIntern'] ?? false) ? 'X' : '',
            'insc_reg_inter_no' => !($payload['regIntern'] ?? false) ? 'X' : '',
            'insc_reg_int_fuera_yes' => ($payload['regInternFuera'] ?? false) ? 'X' : '',
            'insc_reg_int_fuera_no' => !($payload['regInternFuera'] ?? false) ? 'X' : '',
            'insc_at_hosp_dom_ant_yes' => ($payload['atencHospit'] ?? false) ? 'X' : '',
            'insc_at_hosp_dom_ant_no' => !($payload['atencHospit'] ?? false) ? 'X' : '',
            'insc_amb_rural_yes' => ($payload['ambitorural'] ?? false) ? 'X' : '',
            'insc_amb_rural_no' => !($payload['ambitorural'] ?? false) ? 'X' : '',
            'insc_contex_enc_yes' => ($payload['contexEncierro'] ?? false) ? 'X' : '',
            'insc_contex_enc_no' => !($payload['contexEncierro'] ?? false) ? 'X' : '',
            'insc_menor_jud_yes' => ($payload['menorJud'] ?? false) ? 'X' : '',
            'insc_menor_jud_no' => !($payload['menorJud'] ?? false) ? 'X' : '',
            'insc_cen_det_prov' => $payload['cen_det_prov'] ?? '',
            'insc_observaciones' => $payload['observaciones'] ?? '',
            'insc_motivo_procedencia' => $payload['motivoProcedencia'] ?? '',

            // Compromiso Bono
            // 'CBapeynom' => $cbFullname,
            // 'CBaynalum' => ucwords(strtolower(($payload['lname'] ?? '') . ", " . ($payload['fname'] ?? ''))),
            // 'CBdni' => $cbId,
            // 'CBemail' => $cbEmail,
            // 'CBphone' => $cbPhone,

            // Convenio Matrícula
            'cm_fullname' => $cbFullname,
            'cm_id' => $cbId,
            'cm_student_fullname' => ucwords(strtolower(($payload['lname'] ?? '') . ", " . ($payload['fname'] ?? ''))),
            'cm_student_fullname1' => ucwords(strtolower(($payload['lname'] ?? '') . ", " . ($payload['fname'] ?? ''))),
            'cm_student_fullname2' => ucwords(strtolower(($payload['lname'] ?? '') . ", " . ($payload['fname'] ?? ''))),
            'cm_student_id' => ($payload['doc_type'] ?? $preEnrollment->doc_type) . ": " . $preEnrollment->doc_number,
            'cm_schoolyear' => $preEnrollment->cycle_id,
            'cm_month_large' => ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'][date('m')] ?? date('m'),
            'cm_day' => date('d'),
            'cm_year' => date('Y'),
            'cm_tutor_type' => $encargado,

            // SSA Fields
            'ssa_schoolyear' => $preEnrollment->cycle_id,
            'ssa_date' => date("d-m-Y"),
            'ssa_repeater_yes' => (($payload['repitente'] ?? 'No') == 'Si') ? 'X' : '',
            'ssa_repeater_no' => (($payload['repitente'] ?? 'No') != 'Si') ? 'X' : '',
            'ssa_student_fullname' => ucwords(strtolower(($payload['lname'] ?? '') . ", " . ($payload['fname'] ?? ''))),
            'ssa_student_id' => ($payload['doc_type'] ?? $preEnrollment->doc_type) . ": " . $preEnrollment->doc_number,
            'ssa_copaleche_yes' => ($payload['copaleche'] ?? false) ? 'X' : '',

            // MIE Fields
            'mie_schoolyear' => $preEnrollment->cycle_id,
            'mie_student_lastname' => ucwords(strtolower($payload['lname'] ?? '')),
            'mie_student_firstname' => ucwords(strtolower($payload['fname'] ?? '')),
            'mie_id_type' => $payload['doc_type'] ?? $preEnrollment->doc_type,
            'mie_id' => $preEnrollment->doc_number,
            'mie_sexF' => (($payload['sexo'] ?? '') == 'F') ? 'X' : '',
            'mie_sexM' => (($payload['sexo'] ?? '') == 'M') ? 'X' : '',
            'mie_phone' => $preEnrollment->phone ?? ($payload['phone'] ?? ''),
            'mie_phone_prov1' => (($payload['phoneprovider'] ?? '') == 'P') ? 'X' : '',
            'mie_phone_prov2' => (($payload['phoneprovider'] ?? '') == 'M') ? 'X' : '',
            'mie_phone_prov3' => (($payload['phoneprovider'] ?? '') == 'C') ? 'X' : '',
            'mie_phone_prov4' => (!in_array($payload['phoneprovider'] ?? '', ['P', 'M', 'C']) && !empty($payload['phoneprovider'])) ? 'X' : '',
            
            'mie_parent1_lastname' => ucwords(strtolower($payload['lname1'] ?? '')),
            'mie_parent1_firstname' => ucwords(strtolower($payload['fname1'] ?? '')),
            'mie_parent1_relationship' => 'Padre/Madre',
            'mie_parent1_sexF' => (($payload['sexo1'] ?? '') == 'F') ? 'X' : '',
            'mie_parent1_sexM' => (($payload['sexo1'] ?? '') == 'M') ? 'X' : '',
            'mie_parent1_full_address' => ($payload['dirStreet1'] ?? '') . ' ' . ($payload['dirNumber1'] ?? ''),
            'mie_parent1_id_type' => $payload['doctipo1'] ?? '',
            'mie_parent1_id' => $payload['docnum1'] ?? '',
            'mie_parent1_email' => $payload['email1'] ?? '',
            'mie_parent1_phone' => $payload['phone1'] ?? '',
            'mie_parent1_phone_prov1' => (($payload['phoneprovider1'] ?? '') == 'P') ? 'X' : '',
            'mie_parent1_phone_prov2' => (($payload['phoneprovider1'] ?? '') == 'M') ? 'X' : '',
            'mie_parent1_phone_prov3' => (($payload['phoneprovider1'] ?? '') == 'C') ? 'X' : '',
            'mie_parent1_phone_prov4' => (!in_array($payload['phoneprovider1'] ?? '', ['P', 'M', 'C']) && !empty($payload['phoneprovider1'])) ? 'X' : '',
            
            'mie_parent2_lastname' => ucwords(strtolower($payload['lname2'] ?? '')),
            'mie_parent2_firstname' => ucwords(strtolower($payload['fname2'] ?? '')),
            'mie_parent2_relationship' => 'Padre/Madre',
            'mie_parent2_sexF' => (($payload['sexo2'] ?? '') == 'F') ? 'X' : '',
            'mie_parent2_sexM' => (($payload['sexo2'] ?? '') == 'M') ? 'X' : '',
            'mie_parent2_full_address' => ($payload['dirStreet2'] ?? '') . ' ' . ($payload['dirNumber2'] ?? ''),
            'mie_parent2_id_type' => $payload['doctipo2'] ?? '',
            'mie_parent2_id' => $payload['docnum2'] ?? '',
            'mie_parent2_email' => $payload['email2'] ?? '',
            'mie_parent2_phone' => $payload['phone2'] ?? '',
            'mie_parent2_phone_prov1' => (($payload['phoneprovider2'] ?? '') == 'P') ? 'X' : '',
            'mie_parent2_phone_prov2' => (($payload['phoneprovider2'] ?? '') == 'M') ? 'X' : '',
            'mie_parent2_phone_prov3' => (($payload['phoneprovider2'] ?? '') == 'C') ? 'X' : '',
            'mie_parent2_phone_prov4' => (!in_array($payload['phoneprovider2'] ?? '', ['P', 'M', 'C']) && !empty($payload['phoneprovider2'])) ? 'X' : '',
        ];
        
        $templatePath = resource_path('templates/sigae-secundario.pdf');

        $pdf = new FPDM($templatePath);
        $pdf->useCheckboxParser = true;
        $pdf->Load($data, true);
        $pdf->Merge();

        $path = 'private/pre_enrollments/pre-' . $preEnrollment->id . '.pdf';
        $fullPath = storage_path('app/' . $path);
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        $pdf->Output('F', $fullPath);

        return $path;
    }
}
