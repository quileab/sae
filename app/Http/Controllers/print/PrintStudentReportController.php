<?php

namespace App\Http\Controllers\print;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Config;
use App\Models\Grade;
use App\Models\PaymentRecord;
use App\Models\Subject;
use App\Services\AcademicCycle;
use App\Services\EnrollmentAcademicStatusService;
use Illuminate\Http\Request;

class PrintStudentReportController extends Controller
{
    public function generateReport(Request $request, $subject_id)
    {
        // Authorization check
        if (! auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'teacher', 'preceptor'])) {
            abort(403);
        }

        $subject = Subject::with('career')->findOrFail($subject_id);
        $students = $subject->users()->where('role', 'student')->orderBy('lastname')->orderBy('firstname')->get();

        $cycle = $request->query('cycle', date('Y'));
        [$first_semester_start, $first_semester_end] = AcademicCycle::firstQuarter($cycle);
        [$second_semester_start, $second_semester_end] = AcademicCycle::secondQuarter($cycle);

        $class_sessions = ClassSession::where('subject_id', $subject_id)
            ->where('unit', '!=', '0')
            ->whereYear('date', $cycle)
            ->get();
        $total_classes_1 = $class_sessions->whereBetween('date', [$first_semester_start, $first_semester_end])->count();
        $total_classes_2 = $class_sessions->whereBetween('date', [$second_semester_start, $second_semester_end])->count();

        $student_ids = $students->pluck('id');
        $all_grades = Grade::whereIn('user_id', $student_ids)
            ->whereIn('class_session_id', $class_sessions->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $reportData = [];
        foreach ($students as $student) {
            $grades = $all_grades->get($student->id, collect());

            $studentData = [
                'student' => $student,
                'first_semester' => $this->processGrades($grades, $class_sessions, $first_semester_start, $first_semester_end, $total_classes_1),
                'second_semester' => $this->processGrades($grades, $class_sessions, $second_semester_start, $second_semester_end, $total_classes_2),
            ];

            $studentData['regularized'] = $this->checkRegularization($studentData);
            $reportData[] = $studentData;
        }

        return view('print.student-report', compact('subject', 'reportData'));
    }

    public function generateAttendanceReport($subject_id)
    {
        // Authorization check
        if (! auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'teacher', 'preceptor'])) {
            abort(403);
        }

        $subject = Subject::with('enrollments.user')->findOrFail($subject_id);

        $students = $subject->enrollments->map(function ($enrollment) {
            return $enrollment->user;
        })->where('role', 'student')->sortBy('lastname');

        $reportData = [];
        foreach ($students as $student) {
            $reportData[$student->id] = $this->getStudentGrades($student, $subject);
            $reportData[$student->id]['student'] = $student;
        }

        $config = (object) [
            'logo' => 'imgs/logo.png',
            'longname' => Config::get('longname', 'Nombre de la Institución'),
            'shortname' => Config::get('shortname', 'SAE'),
        ];

        $firstItem = ! empty($reportData) ? reset($reportData) : [];
        $total_classes_q1 = $firstItem['total_classes_q1'] ?? 0;
        $total_classes_q2 = $firstItem['total_classes_q2'] ?? 0;

        return view('print.student-attendance-report', compact('subject', 'reportData', 'config', 'total_classes_q1', 'total_classes_q2'));
    }

    public function generateGradesReport($subject_id)
    {
        // Authorization check
        if (! auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'teacher', 'preceptor'])) {
            abort(403);
        }

        $subject = Subject::with('enrollments.user')->findOrFail($subject_id);

        $students = $subject->enrollments->map(function ($enrollment) {
            return $enrollment->user;
        })->where('role', 'student')->sortBy('lastname');

        $reportData = [];
        foreach ($students as $student) {
            $reportData[$student->id] = $this->getStudentGrades($student, $subject);
            $reportData[$student->id]['student'] = $student;
        }

        $config = (object) [
            'logo' => 'imgs/logo.png',
            'longname' => Config::get('longname', 'Nombre de la Institución'),
            'shortname' => Config::get('shortname', 'SAE'),
        ];

        return view('print.student-grades-report', compact('subject', 'reportData', 'config'));
    }

    private function calculateAttendance($grades, $class_sessions, $start_date, $end_date, $total_classes)
    {
        $semester_sessions = $class_sessions->whereBetween('date', [$start_date, $end_date])->pluck('id');
        $semester_grades = $grades->whereIn('class_session_id', $semester_sessions);

        $attendance_count = $semester_grades->where('attendance', '>', 0)->count();

        return [
            'count' => $attendance_count,
            'percentage' => $total_classes > 0 ? ($attendance_count / $total_classes) * 100 : 0,
        ];
    }

    private function processGrades($grades, $class_sessions, $start_date, $end_date, $total_classes)
    {
        $semester_sessions = $class_sessions->whereBetween('date', [$start_date, $end_date])->pluck('id');
        $semester_grades = $grades->whereIn('class_session_id', $semester_sessions);

        $attendance_count = $semester_grades->where('attendance', '>', 0)->count();

        return [
            'tp' => $this->getAverageGrade($semester_grades, 'TP'),
            'rec_tp' => $this->getAverageGrade($semester_grades, 'RecTP'),
            'ev' => $this->getAverageGrade($semester_grades, 'EV'),
            'rec_ev' => $this->getAverageGrade($semester_grades, 'RecEV'),
            'attendance' => $total_classes > 0 ? ($attendance_count / $total_classes) * 100 : 0,
        ];
    }

    private function checkRegularization($studentData)
    {
        $first_ev = $studentData['first_semester']['ev'];
        $first_rec_ev = $studentData['first_semester']['rec_ev'];
        $second_ev = $studentData['second_semester']['ev'];
        $second_rec_ev = $studentData['second_semester']['rec_ev'];

        return $first_ev >= 6 || $first_rec_ev >= 6 || $second_ev >= 6 || $second_rec_ev >= 6;
    }

    private function getStudentGrades($student, $subject)
    {
        $cycle = (int) request('cycle', session('cycle_id') ?? date('Y'));

        $classSessions = ClassSession::where('subject_id', $subject->id)
            ->where('unit', '!=', '0')
            ->whereYear('date', $cycle)
            ->orderBy('date')
            ->get();

        [$q1Start, $q1End] = AcademicCycle::firstQuarter($cycle);
        [$q2Start, $q2End] = AcademicCycle::secondQuarter($cycle);

        $classSessions_q1 = $classSessions->filter(function ($session) use ($q1Start, $q1End) {
            return $session->date->between($q1Start, $q1End);
        });

        $classSessions_q2 = $classSessions->filter(function ($session) use ($q2Start, $q2End) {
            return $session->date->between($q2Start, $q2End);
        });

        $session_ids_q1 = $classSessions_q1->pluck('id');
        $session_ids_q2 = $classSessions_q2->pluck('id');

        $grades_q1 = Grade::where('user_id', $student->id)
            ->whereIn('class_session_id', $session_ids_q1)
            ->get()
            ->keyBy('class_session_id');

        $grades_q2 = Grade::where('user_id', $student->id)
            ->whereIn('class_session_id', $session_ids_q2)
            ->get()
            ->keyBy('class_session_id');

        $total_attendance_q1 = 0;
        foreach ($classSessions_q1 as $session) {
            $grade = $grades_q1->firstWhere('class_session_id', $session->id);
            if ($grade) {
                $total_attendance_q1 += $grade->attendance;
            }
        }
        $attendance_q1 = ($classSessions_q1->count() > 0) ? $total_attendance_q1 / $classSessions_q1->count() : 0;

        $total_attendance_q2 = 0;
        foreach ($classSessions_q2 as $session) {
            $grade = $grades_q2->firstWhere('class_session_id', $session->id);
            if ($grade) {
                $total_attendance_q2 += $grade->attendance;
            }
        }
        $attendance_q2 = ($classSessions_q2->count() > 0) ? $total_attendance_q2 / $classSessions_q2->count() : 0;

        $isEvaluation = fn (Grade $grade) => $grade->isEvaluation();

        $effectiveEvQ1 = app(EnrollmentAcademicStatusService::class)
            ->resolveEffectiveEvaluations($grades_q1, $isEvaluation);
        $avg_ev_q1 = $effectiveEvQ1->avg('grade');
        $count_ev_q1 = $effectiveEvQ1->count();

        $avg_tp_q1 = $grades_q1->filter(fn (Grade $grade) => $grade->isPracticalWork());
        $count_tp_q1 = $avg_tp_q1->count();
        $avg_tp_q1 = $avg_tp_q1->avg('grade');

        $effectiveEvQ2 = app(EnrollmentAcademicStatusService::class)
            ->resolveEffectiveEvaluations($grades_q2, $isEvaluation);
        $avg_ev_q2 = $effectiveEvQ2->avg('grade');
        $count_ev_q2 = $effectiveEvQ2->count();

        $avg_tp_q2 = $grades_q2->filter(fn (Grade $grade) => $grade->isPracticalWork());
        $count_tp_q2 = $avg_tp_q2->count();
        $avg_tp_q2 = $avg_tp_q2->avg('grade');

        $annual_count_ev = $count_ev_q1 + $count_ev_q2;
        $annual_count_tp = $count_tp_q1 + $count_tp_q2;

        $all_grades = $grades_q1->merge($grades_q2);

        $annual_avg_ev = app(EnrollmentAcademicStatusService::class)
            ->resolveEffectiveEvaluations($all_grades, $isEvaluation)
            ->avg('grade');

        $annual_avg_tp = $all_grades->filter(fn (Grade $grade) => $grade->isPracticalWork())->avg('grade');

        return [
            'grades_q1' => $grades_q1->keyBy('class_session_id'),
            'grades_q2' => $grades_q2->keyBy('class_session_id'),
            'attendance_q1' => [
                'count' => $grades_q1->where('attendance', '>', 0)->count(),
                'percentage' => $attendance_q1,
            ],
            'attendance_q2' => [
                'count' => $grades_q2->where('attendance', '>', 0)->count(),
                'percentage' => $attendance_q2,
            ],
            'avg_ev_q1' => $avg_ev_q1,
            'count_ev_q1' => $count_ev_q1,
            'avg_tp_q1' => $avg_tp_q1,
            'count_tp_q1' => $count_tp_q1,
            'avg_ev_q2' => $avg_ev_q2,
            'count_ev_q2' => $count_ev_q2,
            'avg_tp_q2' => $avg_tp_q2,
            'count_tp_q2' => $count_tp_q2,
            'classSessions_q1' => $classSessions_q1,
            'classSessions_q2' => $classSessions_q2,
            'total_classes_q1' => $classSessions_q1->count(),
            'total_classes_q2' => $classSessions_q2->count(),
            'annual_attendance_percentage' => ($attendance_q1 + $attendance_q2) / 2,
            'annual_avg_tp' => $annual_avg_tp,
            'annual_count_tp' => $annual_count_tp,
            'annual_avg_ev' => $annual_avg_ev,
            'annual_count_ev' => $annual_count_ev,
        ];
    }

    public function printStudentsPayments(Request $request)
    {
        // Authorization check
        if (! auth()->user()->hasAnyRole(['admin', 'principal', 'director', 'administrative', 'treasurer'])) {
            abort(403);
        }

        $dateFrom = $request->input('dateFrom');
        $dateTo = $request->input('dateTo');
        $search = $request->input('search');

        $payments = PaymentRecord::with('user')
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->when($search, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', '%'.$search.'%')
                        ->orWhere('lastname', 'like', '%'.$search.'%')
                        ->orWhere('firstname', 'like', '%'.$search.'%');
                });
            })
            ->get();

        return view('print.students-payments', compact('payments'));
    }

    private function getAverageGrade($grades, $type)
    {
        $lowerType = strtolower($type);
        $filtered = $grades->filter(function (Grade $grade) use ($lowerType) {
            if ($lowerType === 'ev') {
                return $grade->isEvaluation();
            }
            if ($lowerType === 'tp') {
                return $grade->isPracticalWork();
            }

            return str_starts_with(strtolower($grade->comments ?? ''), $lowerType);
        });

        return $filtered->count() > 0 ? $filtered->avg('grade') : 0;
    }
}
