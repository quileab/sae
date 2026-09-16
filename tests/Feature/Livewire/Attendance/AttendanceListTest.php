<?php

use App\Livewire\Attendance\AttendanceList;
use App\Models\Career;
use App\Models\Config;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->career = Career::factory()->create(['allow_enrollments' => true, 'allow_evaluations' => true]);
    $this->student = User::factory()->create(['role' => 'student']);
    $this->student->careers()->attach($this->career);

    Config::updateOrCreate(
        ['id' => 'shift_type'],
        [
            'value' => 'simple',
            'group' => 'system',
            'type' => 'text',
            'description' => 'Shift Type',
        ]
    );
});

test('staff can record daily attendance', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    Livewire::actingAs($admin)
        ->test(AttendanceList::class)
        ->set('careerId', $this->career->id)
        ->set('attendances.'.$this->student->id.'.status', 'absent')
        ->set('attendances.'.$this->student->id.'.note', 'Enfermo')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('daily_attendances', [
        'user_id' => $this->student->id,
        'career_id' => $this->career->id,
        'status' => 'absent',
        'note' => 'Enfermo',
        'recorded_by' => $admin->id,
    ]);
});

test('students cannot record attendance', function () {
    $this->actingAs($this->student);

    Livewire::test(AttendanceList::class)
        ->assertForbidden();
});

test('preceptor can only see assigned careers', function () {
    $preceptor = User::factory()->create(['role' => 'preceptor']);
    $otherCareer = Career::factory()->create(['allow_enrollments' => true, 'allow_evaluations' => true]);

    $preceptor->careers()->attach($this->career);

    Livewire::actingAs($preceptor)
        ->test(AttendanceList::class)
        ->assertSee($this->career->name)
        ->assertDontSee($otherCareer->name);
});
