<?php

namespace App\Http\Controllers;

use App\Models\Classbook;
use App\Models\Config;
use App\Models\Grade;
use App\Models\PaymentRecord;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;

class PrintStudentsStatsController extends Controller
{
    private $subject;

    private $filterWords;

    private $dateFrom;

    private $dateTo;

    private function getCycle(Request $request)
    {
        return $request->query('cycle') ?? session('cycle_id') ?? date('Y');
    }

    public function listAttendance(Request $request, $subject)
    {
        $cycle = $this->getCycle($request);
        $this->dateFrom = $cycle.'-01-01';
        $this->dateTo = $cycle.'-12-31';
        $this->subject = $subject;
        $config = Config::where('group', 'main')->get()->pluck('value', 'id')->toArray();
        $classCount = Classbook::where('subject_id', $subject)
            ->where('Unit', '>', 0)
            ->whereBetween('date_id', [$this->dateFrom, $this->dateTo])
            ->count();
        if ($classCount == 0) {
            return '⚠️ No existen clases aún para el ciclo '.$cycle;
        }
        $data = [];
        $data['subject'] = Subject::find($subject);
        $data['user'] = auth()->user();
        $students = Subject::find($subject)->students();

        foreach ($students as $student) {
            $student->attendance = Grade::where('user_id', $student->id)
                ->where('subject_id', $this->subject)
                ->whereBetween('date_id', [$this->dateFrom, $this->dateTo])
                ->sum('attendance');
            $student->attendance = ceil($student->attendance / $classCount);
        }

        return view('print.students-attendance', compact(['classCount', 'students', 'data', 'config']));
    }

    public function studentClasses(Request $request, $student, $subject)
    {
        $cycle = $this->getCycle($request);
        $this->dateFrom = $cycle.'-01-01';
        $this->dateTo = $cycle.'-12-31';

        $this->subject = $subject;
        $data = [];
        $data['classCount'] = Classbook::where('subject_id', $subject)
            ->where('Unit', '>', 0)
            ->whereBetween('date_id', [$this->dateFrom, $this->dateTo])
            ->count();
        $data['config'] = Config::where('group', 'main')->get()->pluck('value', 'id')->toArray();

        $classes = Grade::where('user_id', $student)
            ->where('subject_id', $subject)
            ->whereBetween('date_id', [$this->dateFrom, $this->dateTo])
            ->orderBy('date_id', 'ASC')->get();

        // calculate sums and add attibutes
        if ($classes->count() == 0) {
            return '⚠️ No existen clases aún para el ciclo '.$cycle;
        }

        $sum_att = 0;
        $sum_EV = 0;
        $sum_TP = 0;
        $count_EV = 0;
        $count_TP = 0;
        $attendance = 0;
        foreach ($classes as $class) {
            $attendance += $class->attendance;
            $type = substr(mb_strtoupper($class->name), 0, 2);
            switch ($type) {
                case 'EV':
                    $count_EV++;
                    $sum_EV += $class->grade;
                    $class->type = 'EV';
                    break;
                case 'TP':
                    $count_TP++;
                    $sum_TP += $class->grade;
                    $class->type = 'TP';
                    break;
                case 'FI': $class->type = 'FI';
                    break;
                default:
                    $class->type = '';
            }
            $class->type = $type;
        }
        $data['countEV'] = $count_EV;
        $data['countTP'] = $count_TP;
        $data['sumEV'] = $sum_EV;
        $data['sumTP'] = $sum_TP;
        $data['sumAttendance'] = $attendance;
        $student = User::find($student);
        $subject = Subject::find($subject);

        return view('print.students-stats', compact(['classes', 'student', 'subject', 'data']));
    }

    public function studentReportCard(Request $request, $student)
    {
        $cycle = $this->getCycle($request);
        $this->dateFrom = $cycle.'-01-01';
        $this->dateTo = $cycle.'-12-31';

        $data = [];
        $data = Config::where('group', 'main')->get()->pluck('value', 'id')->toArray();
        // todo in config file
        $filterReporCard = 'tp%|ev%|regular%|final%';
        $this->filterWords = explode('|', $filterReporCard);

        $grades = Grade::where('user_id', $student)
            ->with('subject')
            ->where('grade', '>', 0)
            ->whereBetween('date_id', [$this->dateFrom, $this->dateTo])
            ->where(function ($query) {
                foreach ($this->filterWords as $filter) {
                    $query->orWhere('name', 'LIKE', $filter);
                }
            });
        $grades = $grades->orderBy('subject_id', 'ASC')
            ->orderBy('date_id', 'DESC')->get();
        $student = User::find($student);

        return view('print.students-report-card', compact(['grades', 'student', 'data']));
    }

    public function paymentsReport($dateFrom, $dateTo, $search = null)
    {
        // purge $search as filterwords (globally)
        $this->filterWords = trim($search);
        $this->filterWords = strip_tags($search);
        $this->filterWords = stripslashes($search);
        $this->filterWords = htmlspecialchars($search);

        $records = PaymentRecord::whereBetween('created_at', [$dateFrom.' 00:00:00', $dateTo.' 23:59:59'])
            ->with('user');
        if ($this->filterWords != '') {
            $records = $records
                ->whereHas('user', function ($q) {
                    $q->where('lastname', 'like', '%'.$this->filterWords.'%')
                        ->orWhere('firstname', 'like', '%'.$this->filterWords.'%');
                });
        }
        $records = $records->get();

        $total = 0;
        foreach ($records as $record) {
            $total += $record['paymentAmount'];
        }
        $data['total'] = $total;
        $data['dateFrom'] = $dateFrom;
        $data['dateTo'] = $dateTo;
        $data['search'] = $search;

        return view('printPayments', compact(['records', 'data']));
    }
}
