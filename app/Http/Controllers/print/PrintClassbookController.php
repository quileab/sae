<?php

namespace App\Http\Controllers\print;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Config;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PrintClassbookController extends Controller
{
    public function printClassbooks($subject = null, $user = null)
    {
        if (! $user) {
            $user = Auth::user()->id;
        }

        // Authorization check: Only admins or the user themselves
        $authUser = auth()->user();
        if (! $authUser->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'preceptor', 'teacher']) && $authUser->id != $user) {
            abort(403, 'No tienes permiso para ver el libro de clases de otro usuario.');
        }

        // Subject enrollment check for students
        if ($subject && $authUser->hasRole('student') && ! $authUser->hasSubject($subject)) {
            abort(403, 'No estás matriculado en esta materia.');
        }

        if (! $subject || ! is_numeric($subject)) {
            $subject = null;
        }

        $config = [
            'longname' => Config::get('longname', 'Nombre de la Institución'),
            'shortname' => Config::get('shortname', 'SAE'),
        ];

        // Prioritize: 1. URL Parameter, 2. Session, 3. Current Year
        $cycle = request()->query('cycle') ?? session('cycle_id') ?? date('Y');

        $dateFrom = $cycle.'-01-01';
        $dateTo = $cycle.'-12-31';

        // Obtener todas las sesiones de clase para la materia con datos de calificaciones del usuario
        // with user -> teacher_id
        $classbooks = ClassSession::with(['grades' => function ($query) use ($user) {
            $query->where('user_id', $user);
        }])
            // join get the teacher's name
            ->select('class_sessions.*', 'users.firstname as teacher_firstname', 'users.lastname as teacher_lastname')
            ->join('users', 'users.id', '=', 'class_sessions.teacher_id')
            ->where('subject_id', $subject)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('class_sessions.date')
            ->get();

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
        $data['subject'] = Subject::find($subject);
        $data['user'] = User::find($user);
        // if (Auth::user()->hasAnyRole(['student', 'teacher'])) {
        // }
        $data['attendance'] = number_format(100 * $attendance / $totalAttendance, 2).'%';

        return view('print.classbook', compact(['classbooks', 'data', 'config']));
    }
}
