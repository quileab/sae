<?php

namespace App\Http\Controllers\print;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Configs;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PrintStudentReportController extends Controller
{
    public function generateReport(Request $request, $subject_id)
    {
        $subject = Subject::with('career')->findOrFail($subject_id);
        $students = $subject->users()->where('role', 'student')->orderBy('lastname')->orderBy('firstname')->get();

        $first_semester_start = date('Y') . '-03-01';
        $first_semester_end = date('Y') . '-07-15';
        $second_semester_start = date('Y') . '-07-16';
        $second_semester_end = date('Y') . '-11-30';

        $class_sessions = ClassSession::where('subject_id', $subject_id)->where('unit', '!=', '0')->get();
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
        $subject = Subject::with('enrollments.user')->findOrFail($subject_id);

        $students = $subject->enrollments->map(function ($enrollment) {
            return $enrollment->user;
        })->where('role', 'student')->sortBy('lastname');

        $reportData = [];
        foreach ($students as $student) {
            $reportData[$student->id] = $this->getStudentGrades($student, $subject);
            $reportData[$student->id]['student'] = $student;
        }

        $config = new \stdClass();
        $config->logo = 'imgs/logo.png'; // default logo
        $config->longname = \App\Models\Configs::getValue('longname')[0]->value;
        $config->shortname = \App\Models\Configs::getValue('shortname')[0]->value;

        $total_classes_q1 = reset($reportData)['total_classes_q1'];
        $total_classes_q2 = reset($reportData)['total_classes_q2'];

        return view('print.student-attendance-report', compact('subject', 'reportData', 'config', 'total_classes_q1', 'total_classes_q2'));
    }

    public function generateGradesReport($subject_id)
    {
        $subject = Subject::with('enrollments.user')->findOrFail($subject_id);

        $students = $subject->enrollments->map(function ($enrollment) {
            return $enrollment->user;
        })->where('role', 'student')->sortBy('lastname');

        $reportData = [];
        foreach ($students as $student) {
            $reportData[$student->id] = $this->getStudentGrades($student, $subject);
            $reportData[$student->id]['student'] = $student;
        }

        $config = new \stdClass();
        $config->logo = 'imgs/logo.png'; // default logo
        $config->longname = \App\Models\Configs::getValue('longname')[0]->value;

        return view('print.student-grades-report', compact('subject', 'reportData', 'config'));
    }

    private function calculateAttendance($grades, $class_sessions, $start_date, $end_date, $total_classes)
    {
        $semester_sessions = $class_sessions->whereBetween('date', [$start_date, $end_date])->pluck('id');
        $semester_grades = $grades->whereIn('class_session_id', $semester_sessions);

        $attendance_count = $semester_grades->where('attendance', true)->count();

        return [
            'count' => $attendance_count,
            'percentage' => $total_classes > 0 ? ($attendance_count / $total_classes) * 100 : 0,
        ];
    }

    private function processGrades($grades, $class_sessions, $start_date, $end_date, $total_classes)

    {
        $semester_sessions = $class_sessions->whereBetween('date', [$start_date, $end_date])->pluck('id');
        $semester_grades = $grades->whereIn('class_session_id', $semester_sessions);

        $attendance_count = $semester_grades->where('attendance', true)->count();

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

        return ($first_ev >= 6 || $first_rec_ev >= 6 || $second_ev >= 6 || $second_rec_ev >= 6);
    }

    private function getStudentGrades($student, $subject)
    {
        $cycle = session('cycle');
        if (!$cycle) {
            $cycle = \App\Models\Configs::getValue('cycle')[0]->value;
            session(['cycle' => $cycle]);
        }

        $classSessions = ClassSession::where('subject_id', $subject->id)
            ->where('unit', '!=', '0')
            ->whereYear('date', $cycle)
            ->orderBy('date')
            ->get();

        $classSessions_q1 = $classSessions->filter(function ($session) {
            $date = new \DateTime($session->date);
            $month = $date->format('m');
            $day = $date->format('d');
            return ($month == 3 && $day >= 1) || ($month > 3 && $month < 7) || ($month == 7 && $day <= 14);
        });

        $classSessions_q2 = $classSessions->filter(function ($session) {
            $date = new \DateTime($session->date);
            $month = $date->format('m');
            $day = $date->format('d');
            return ($month == 7 && $day >= 15) || ($month > 7 && $month < 11) || ($month == 11 && $day <= 30);
        });

        $session_ids_q1 = $classSessions_q1->pluck('id');
        $session_ids_q2 = $classSessions_q2->pluck('id');

        $grades_q1 = [];
        foreach ($session_ids_q1 as $session_id) {
            $grade = Grade::where('user_id', $student->id)
                ->where('class_session_id', $session_id)
                ->first();
            if ($grade) {
                $grades_q1[$session_id] = $grade;
            }
        }
        $grades_q1 = collect($grades_q1);

        $grades_q2 = [];
        foreach ($session_ids_q2 as $session_id) {
            $grade = Grade::where('user_id', $student->id)
                ->where('class_session_id', $session_id)
                ->first();
            if ($grade) {
                $grades_q2[$session_id] = $grade;
            }
        }
        $grades_q2 = collect($grades_q2);

        // dd($grades_q1, $grades_q2);

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

        $avg_ev_q1 = $grades_q1->filter(function ($grade) {
            return str_starts_with(strtolower($grade->comments), 'ev');
        });
        $count_ev_q1 = $avg_ev_q1->count();
        $avg_ev_q1 = $avg_ev_q1->avg('grade');

        $avg_tp_q1 = $grades_q1->filter(function ($grade) {
            return str_starts_with(strtolower($grade->comments), 'tp');
        });
        $count_tp_q1 = $avg_tp_q1->count();
        $avg_tp_q1 = $avg_tp_q1->avg('grade');

        $avg_ev_q2 = $grades_q2->filter(function ($grade) {
            return str_starts_with(strtolower($grade->comments), 'ev');
        });
        $count_ev_q2 = $avg_ev_q2->count();
        $avg_ev_q2 = $avg_ev_q2->avg('grade');

        $avg_tp_q2 = $grades_q2->filter(function ($grade) {
            return str_starts_with(strtolower($grade->comments), 'tp');
        });
        $count_tp_q2 = $avg_tp_q2->count();
        $avg_tp_q2 = $avg_tp_q2->avg('grade');

        $annual_count_ev = $count_ev_q1 + $count_ev_q2;
        $annual_count_tp = $count_tp_q1 + $count_tp_q2;

        $all_grades = $grades_q1->merge($grades_q2);

        $annual_avg_ev = $all_grades->filter(function ($grade) {
            return str_starts_with(strtolower($grade->comments), 'ev');
        })->avg('grade');

        $annual_avg_tp = $all_grades->filter(function ($grade) {
            return str_starts_with(strtolower($grade->comments), 'tp');
        })->avg('grade');

        return [
            'grades_q1' => $grades_q1->keyBy('class_session_id'),
            'grades_q2' => $grades_q2->keyBy('class_session_id'),
            'attendance_q1' => [
                'count' => $grades_q1->count(),
                'percentage' => $attendance_q1,
            ],
            'attendance_q2' => [
                'count' => $grades_q2->count(),
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
        $dateFrom = $request->input('dateFrom');
        $dateTo = $request->input('dateTo');
        $search = $request->input('search');

        $payments = \App\Models\PaymentRecord::with('user')
            ->whereBetween('created_at', [Carbon::parse($dateFrom)->startOfDay(), Carbon::parse($dateTo)->endOfDay()])
            ->when($search, function ($query, $search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('lastname', 'like', '%' . $search . '%')
                        ->orWhere('firstname', 'like', '%' . $search . '%');
                });
            })
            ->get();

        return view('print.students-payments', compact('payments'));
    }
}
