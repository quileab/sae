<?php

namespace App\Http\Controllers\print;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PrintClassbookController extends Controller
{
    public function printClassbooks($subject = null, $user = null)
    {
        if (!$user) {
            $user = Auth::user()->id;
        }

        if (!$subject || !is_numeric($subject)) {
            $subject = session('subject_id', null);
        }
        //dd('printClassbooks', 'Subj:' . $subject, 'User:' . $user);

        $config = \App\Models\Configs::where('group', 'main')->get()->pluck('value', 'id')->toArray();

        // if session cycle is set, use it else set it to the current year
        if (session()->has('cycle')) {
            $cycle = session('cycle');
        } else {
            $cycle = date('Y');
            session()->put('cycle', $cycle);
        }

        $dateFrom = session('cycle') . '-01-01';
        $dateTo = session('cycle') . '-12-31';

        // Obtener todas las sesiones de clase para la materia con datos de calificaciones del usuario
        // with user -> teacher_id
        $classbooks = \App\Models\ClassSession::with(['grades' => function ($query) use ($user) {
            $query->where('user_id', $user);
        }])
            // join get the teacher's name
            ->select('class_sessions.*', 'users.firstname as teacher_firstname', 'users.lastname as teacher_lastname')
            ->join('users', 'users.id', '=', 'class_sessions.teacher_id')
            ->where('subject_id', $subject)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->get();

        // dd($classbooks, $subject, $user, $dateFrom, $dateTo);
        // Si no hay sesiones de clase, devolver 404
        if ($classbooks->isEmpty()) {
            return back()->with('error', 'No encontrado');
        }

        // Crear un array temporal para las calificaciones y asistencia
        $grades = [];
        foreach ($classbooks as $classbook) {
            $grade = $classbook->grades->first();
            $classbook->attendance = $grade->attendance ?? null;
            $classbook->grade = $grade->grade ?? null;
        }

        // TODO: Calcular la asistencia y la calificación promedio
        $attendance = $classbooks->sum('attendance');
        // $totalAttendance = all classbooks sessions with unit > 0
        $totalAttendance = $classbooks->where('unit', '>', 0)->count() * 100;

        // $totalAttendance = $classbooks->count() * 100;

        $data = [];
        $data['subject'] = \App\Models\Subject::find($subject);
        $data['user'] = \App\Models\User::find($user);
        // if (Auth::user()->hasAnyRole(['student', 'teacher'])) {
        // }
        $data['attendance'] = number_format(100 * $attendance / $totalAttendance, 2) . '%';

        // dd($data, $classbooks, $config);

        return view('printClassbook', compact(['classbooks', 'data', 'config']));
    }
}
