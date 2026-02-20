<?php

namespace App\Http\Controllers\print;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class PrintInscriptionsController extends Controller
{
    public $config;

    public $inscriptions;

    public function __construct()
    { // Constructor, obtengo de la configuracion los datos del
        //  grupo MAIN en forma de array asociativo ID => VALOR en This->Config
        // obtengo datos del grupo MAIN
        $this->config = \App\Models\Configs::where('group', 'main')->get()->pluck('value', 'id')->toArray();
        // agrego a config datos del grupo INSCRIPTIONS
        $this->config += \App\Models\Configs::where('group', 'inscriptions')->get()->pluck('description', 'id')->toArray();
    }

    public function inscriptions($student, $insc_conf_id)
    {
        return \App\Models\Inscriptions::where('user_id', $student->id)
            ->where('configs_id', $insc_conf_id)->orderBy('subject_id')->get();
    }

    public function index(User $student, Career $career, string $insc_conf_id)
    {
        $inscriptions = $this->inscriptions($student, $insc_conf_id);
        $config = $this->config;

        // this enables static method calls on the PDF class
        $pdf = app('dompdf.wrapper');
        $pdf->loadView(
            'pdf.inscriptionsPDF',
            compact('inscriptions', 'student', 'career', 'config', 'insc_conf_id')
        );

        return $pdf->stream('preview.pdf');
    }

    public function savePDF(User $student, Career $career, string $insc_conf_id)
    {
        $inscriptions = $this->inscriptions($student, $insc_conf_id);
        $config = $this->config;
        // this enables static method calls on the PDF class
        $pdf = app('dompdf.wrapper');
        $pdf->loadView(
            'pdf.inscriptionsPDF',
            compact('inscriptions', 'student', 'career', 'config', 'insc_conf_id')
        );
        $content = $pdf->download()->getOriginalContent();
        Storage::put("private/inscriptions/insc-$student->id-$career->id-$insc_conf_id-.pdf", $content);

        return back();
    }
}
