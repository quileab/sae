<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\Config;
use App\Models\DailyAttendance;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceSyncController extends Controller
{
    /**
     * Devuelve la lista de alumnos por división para cachear en IndexedDB.
     */
    public function students(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(! $user->isStaff(), 403, 'No tienes permiso para acceder a esta información.');

        $totalLimit = 500; // Increased limit for better usability

        $careerIds = $user->hasAnyRole(['admin', 'director', 'administrative'])
            ? Career::where('allow_enrollments', true)->where('allow_evaluations', true)->pluck('id')
            : $user->careers()->where('allow_enrollments', true)->where('allow_evaluations', true)->pluck('careers.id');

        $students = User::query()
            ->where('role', 'student')
            ->where('enabled', true)
            ->whereHas('careers', fn ($q) => $q->whereIn('careers.id', $careerIds))
            ->with(['careers' => fn ($q) => $q->whereIn('careers.id', $careerIds)])
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->take($totalLimit)
            ->get(['id', 'firstname', 'lastname']);

        // Group students by career for the expected JSON structure
        $data = [];
        $careers = Career::whereIn('id', $careerIds)->get(['id', 'name']);

        foreach ($careers as $career) {
            $careerStudents = $students->filter(fn ($s) => $s->careers->contains('id', $career->id))
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'firstname' => $s->firstname,
                    'lastname' => $s->lastname,
                    'name' => $s->lastname.', '.$s->firstname,
                ]);

            if ($careerStudents->isNotEmpty()) {
                $data[] = [
                    'career_id' => $career->id,
                    'career_name' => $career->name,
                    'students' => $careerStudents->values(),
                ];
            }
        }

        return response()->json($data);
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

        $user = $request->user();
        abort_if(! $user->isStaff(), 403, 'No tienes permiso para registrar asistencia.');

        $shiftType = Config::find('shift_type')?->value ?? 'simple';
        $recordedBy = $user->id;

        // Security check for each record
        $accessibleCareerIds = $user->hasAnyRole(['admin', 'director', 'administrative'])
            ? Career::pluck('id')
            : $user->careers()->pluck('careers.id');

        $syncedCount = 0;
        foreach ($request->records as $record) {
            // IDOR Prevention: Check career access
            if (! $accessibleCareerIds->contains($record['career_id'])) {
                continue;
            }

            // Security check: Ensure student (user_id) belongs to the career
            $isStudentInCareer = DB::table('career_user')
                ->where('career_id', $record['career_id'])
                ->where('user_id', $record['user_id'])
                ->exists();

            if (! $isStudentInCareer) {
                Log::warning('AttendanceSync: Student not in career', [
                    'career_id' => $record['career_id'],
                    'user_id' => $record['user_id'],
                ]);

                continue;
            }

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

            $syncedCount++;
        }

        return response()->json(['synced' => $syncedCount]);
    }
}
