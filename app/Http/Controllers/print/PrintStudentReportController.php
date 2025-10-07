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
        // Fetch subject details
        $subject = Subject::with([
            'enrollments.user',
            'classSessions' => function ($query) {
                $query->orderBy('date');
            }
        ])->findOrFail($subject_id);

        // Extract students from enrollments
        $students = $subject->enrollments->map(function ($enrollment) {
            return $enrollment->user;
        })->sortBy('lastname');

        // Get all class sessions for the subject
        $classSessions = $subject->classSessions;

        // Create a report data structure
        $reportData = [];
        foreach ($students as $student) {
            $studentAttendances = [];
            $totalAbsences = 0;

            foreach ($classSessions as $session) {
                $attendance = $student->grades()->where('class_session_id', $session->id)->first();
                $isPresent = !is_null($attendance);
                $studentAttendances[$session->id] = $isPresent;

                if (!$isPresent) {
                    $totalAbsences++;
                }
            }

            $reportData[$student->id] = [
                'student' => $student,
                'attendances' => $studentAttendances,
                'total_absences' => $totalAbsences,
            ];
        }

        // Separate class sessions by quarter
        $total_classes_q1 = $classSessions->where('quarter', 1)->count();
        $total_classes_q2 = $classSessions->where('quarter', 2)->count();

        $config = Configs::find(1);
        if (!$config) {
            $config = new \stdClass();
            $config->logo = 'imgs/logo.png'; // default logo
            $config->longname = \App\Models\Configs::getValue('longname')[0]->value;
        }

        // Pass data to the view
        return view('print.student-attendance-report', compact('subject', 'reportData', 'total_classes_q1', 'total_classes_q2', 'config'));
    }

    public function generateGradesReport($subject_id)
    {
        $cycle = session('cycle');
        if (!$cycle) {
            $cycle = \App\Models\Configs::getValue('cycle')[0]->value;
            session(['cycle' => $cycle]);
        }

        $subject = Subject::with('enrollments.user')->findOrFail($subject_id);

        $classSessions = ClassSession::where('subject_id', $subject_id)
            ->where('unit', '!=', '0')
            ->whereYear('date', $cycle)
            ->orderBy('date')
            ->get();

        $session_ids = $classSessions->pluck('id');

        $students = $subject->enrollments->map(function ($enrollment) {
            return $enrollment->user;
        })->where('role', 'student')->sortBy('lastname');

        $reportData = [];
        foreach ($students as $student) {
            $grades = Grade::where('user_id', $student->id)
                ->whereIn('class_session_id', $session_ids)
                ->get()
                ->keyBy('class_session_id');

            $reportData[$student->id] = [
                'student' => $student,
                'grades' => $grades,
            ];
        }

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

        $total_classes_q1 = $classSessions_q1->count();
        $total_classes_q2 = $classSessions_q2->count();

        $config = new \stdClass();
        $config->logo = 'imgs/logo.png'; // default logo
        $config->longname = \App\Models\Configs::getValue('longname')[0]->value;

        return view('print.student-grades-report', compact('subject', 'reportData', 'total_classes_q1', 'total_classes_q2', 'config', 'classSessions_q1', 'classSessions_q2'));
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

    private function getAverageGrade($grades, $type)
    {
        $filtered_grades = $grades->filter(fn($grade) => str_starts_with($grade->comments, $type));
        return $filtered_grades->avg('grade');
    }

    private function checkRegularization($studentData)
    {
        $first_ev = $studentData['first_semester']['ev'];
        $first_rec_ev = $studentData['first_semester']['rec_ev'];
        $second_ev = $studentData['second_semester']['ev'];
        $second_rec_ev = $studentData['second_semester']['rec_ev'];

        return ($first_ev >= 6 || $first_rec_ev >= 6 || $second_ev >= 6 || $second_rec_ev >= 6);
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
