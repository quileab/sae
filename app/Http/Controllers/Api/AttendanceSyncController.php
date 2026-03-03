<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\Configs;
use App\Models\DailyAttendance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceSyncController extends Controller
{
    /**
     * Devuelve la lista de alumnos por división para cachear en IndexedDB.
     */
    public function students(Request $request): JsonResponse
    {
        $user = $request->user();
        $totalLimit = 300;
        $count = 0;

        $careers = $user->hasAnyRole(['admin', 'director', 'administrative'])
            ? Career::orderBy('name')->get()
            : $user->careers()->orderBy('name')->get();

        $data = $careers->map(function (Career $career) use (&$count, $totalLimit) {
            if ($count >= $totalLimit) {
                return null;
            }

            $remaining = $totalLimit - $count;
            $students = User::query()
                ->where('role', 'student')
                ->where('enabled', true)
                ->whereHas('careers', fn ($q) => $q->where('careers.id', $career->id))
                ->orderBy('lastname')
                ->orderBy('firstname')
                ->take($remaining)
                ->get(['id', 'firstname', 'lastname'])
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'firstname' => $s->firstname,
                    'lastname' => $s->lastname,
                    'name' => $s->lastname.', '.$s->firstname,
                ]);

            $count += $students->count();

            return [
                'career_id' => $career->id,
                'career_name' => $career->name,
                'students' => $students,
            ];
        })->filter();

        return response()->json($data->values());
    }

    /**
     * Recibe registros marcados offline y los sincroniza.
     *
     * @param  array<int, array{career_id: int, user_id: int, date: string, status: string, note: ?string}>  $records
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'records' => ['required', 'array'],
            'records.*.career_id' => ['required', 'integer', 'exists:careers,id'],
            'records.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'records.*.date' => ['required', 'date'],
            'records.*.status' => ['required', 'in:present,late,early_leave,absent,half_absent'],
            'records.*.note' => ['nullable', 'string', 'max:100'],
        ]);

        $shiftType = Configs::find('shift_type')?->value ?? 'simple';
        $recordedBy = $request->user()->id;

        foreach ($request->records as $record) {
            DailyAttendance::updateOrCreate(
                [
                    'career_id' => $record['career_id'],
                    'user_id' => $record['user_id'],
                    'date' => $record['date'],
                ],
                [
                    'recorded_by' => $recordedBy,
                    'status' => $record['status'],
                    'absence_value' => DailyAttendance::calculateAbsenceValue($record['status'], $shiftType),
                    'note' => $record['note'] ?? null,
                ]
            );
        }

        return response()->json(['synced' => count($request->records)]);
    }
}
