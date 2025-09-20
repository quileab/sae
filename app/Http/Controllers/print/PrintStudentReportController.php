<?php

namespace App\Http\Controllers\print;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Configs;
use Illuminate\Http\Request;

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

    public function generateAttendanceReport(Request $request, $subject_id)
    {
        $subject = Subject::with('career')->findOrFail($subject_id);
        $students = $subject->users()->where('role', 'student')->orderBy('lastname')->orderBy('firstname')->get();

        $q1_start = date('Y') . '-01-01';
        $q1_end = date('Y') . '-07-14';
        $q2_start = date('Y') . '-07-15';
        $q2_end = date('Y') . '-12-30';

        $class_sessions = ClassSession::where('subject_id', $subject_id)->where('unit', '!=', '0')->get();

        $total_classes_q1 = $class_sessions->whereBetween('date', [$q1_start, $q1_end])->count();
        $total_classes_q2 = $class_sessions->whereBetween('date', [$q2_start, $q2_end])->count();

        $student_ids = $students->pluck('id');
        $all_grades = Grade::whereIn('user_id', $student_ids)
            ->whereIn('class_session_id', $class_sessions->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $reportData = [];
        foreach ($students as $student) {
            $grades = $all_grades->get($student->id, collect());

            $attendance_q1 = $this->calculateAttendance($grades, $class_sessions, $q1_start, $q1_end, $total_classes_q1);
            $attendance_q2 = $this->calculateAttendance($grades, $class_sessions, $q2_start, $q2_end, $total_classes_q2);

            $reportData[] = [
                'student' => $student,
                'attendance_q1' => $attendance_q1,
                'attendance_q2' => $attendance_q2,
            ];
        }

        $config = Configs::all()->pluck('value', 'id');
        return view('print.student-attendance-report', compact('subject', 'reportData', 'total_classes_q1', 'total_classes_q2', 'config'));
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
}
