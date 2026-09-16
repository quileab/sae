<?php

use App\Enums\RoleGroups;
use App\Http\Controllers\Api\AttendanceSyncController;
use App\Http\Controllers\print\PrintClassbookController;
use App\Http\Controllers\print\PrintInscriptionsController;
use App\Http\Controllers\print\PrintStudentReportController;
use App\Livewire\Chat;
use App\Livewire\ContentManager;
use App\Livewire\Students\RiskReport;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware(['auth'])->group(function () {
    Route::get('/inscriptionsPDF/{student}/{career}/{inscription}', [PrintInscriptionsController::class, 'index'])->name('inscriptionsPDF');
    Route::get('/inscriptionsSavePDF/{student}/{career}/{inscription}', [PrintInscriptionsController::class, 'savePDF'])->name('inscriptionsSavePDF');
    Route::get('/printClassbooks/{subject?}/{id?}', [PrintClassbookController::class, 'printClassbooks'])->name('printclassbooks')->middleware('roles:admin,teacher,principal,director,administrative,student');

    Route::livewire('/dashboard', 'dashboard');
    Route::livewire('/users', 'users.user-list')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/user/{id?}', 'users.user-form')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/users/import', 'users.import')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/careers', 'careers.career-list')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/career/{id?}', 'careers.career-form')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/subjects', 'subjects.subject-list')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/subjects-table', 'subjects.subject-table')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/subject/{id?}', 'subjects.subject-form')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/enrollments', 'enrollment')->middleware('roles:admin,student,principal,director,administrative');
    Route::livewire('/config-manager', 'config-manager')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/inscriptions', 'inscriptions.inscription-manager')->middleware('roles:admin,student,principal,director,administrative');
    Route::livewire('/inscriptions/list', 'inscriptions.inscription-list')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::livewire('/inscriptions/pdfs', 'inscriptions.inscription-pdf-list')->middleware('roles:admin,principal,director,administrative');
    Route::livewire('/class-sessions', 'class_sessions.class-session-list')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::livewire('/class-session/{id?}', 'class_sessions.class-session-form')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::livewire('/class-sessions/students/{id?}', 'class_sessions.students')->middleware('roles:admin,teacher,principal,director,administrative');
    Volt::route('/students-directory/{subject_id?}', 'class_sessions.student-directory')->name('class-sessions.student-directory')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::get('/chat', Chat::class);
    Route::get('/print/student-report/{subject_id}', [PrintStudentReportController::class, 'generateReport'])->name('print.student-report')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::get('/print/student-attendance-report/{subject_id}', [PrintStudentReportController::class, 'generateAttendanceReport'])->name('print.student-attendance-report')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::get('/print/student-grades-report/{subject_id}', [PrintStudentReportController::class, 'generateGradesReport'])->name('print.student-grades-report')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::get('/print/students-payments', [PrintStudentReportController::class, 'printStudentsPayments'])->name('printStudentsPayments')->middleware('roles:admin,principal,director,administrative');
    Route::get('/subjects-content/{subject?}', ContentManager::class)->name('subjects.content-manager')->middleware('roles:admin,teacher,principal,director,administrative,student');
    Route::livewire('/calendar', 'calendar')->name('calendar')->middleware('roles:admin,teacher,principal,director,administrative,student');
    Route::get('/reports/risk', RiskReport::class)->name('reports.risk')->middleware('roles:admin,teacher,principal,director,administrative');
    Route::livewire('/attendance', 'attendance.attendance-list')->name('attendance')->middleware('roles:preceptor,admin,principal,director,administrative');
    Volt::route('/final-grades', 'final-grades.index')->name('final-grades.index')->middleware('roles:admin,administrative,director,preceptor,teacher');
    Route::livewire('/profile', 'users.profile')->name('profile');

    // Internal Routes for Attendance PWA (Full session support)
    Route::prefix('pwa-attendance')
        ->middleware('roles:'.implode(',', RoleGroups::values(RoleGroups::ATTENDANCE_MANAGERS)))
        ->group(function () {
            Route::get('/students', [AttendanceSyncController::class, 'students']);
            Route::post('/sync', [AttendanceSyncController::class, 'store']);
        });

    // Biblioteca (Libros y Préstamos)
    Route::livewire('/books', 'books.index')->name('books.index')->middleware('roles:admin,administrative,director,preceptor,student,teacher');
    Route::livewire('/books/spines', 'books.spines')->name('books.spines')->middleware('roles:admin,administrative,director,preceptor');
    Route::livewire('/books/spines/print', 'books.spines-print')->name('books.spines.print')->middleware('roles:admin,administrative,director,preceptor');
    Route::livewire('/books/print', 'books.print')->name('books.print')->middleware('roles:admin,administrative,director,preceptor,student,teacher');
    Route::livewire('/books/create', 'books.form')->name('books.create')->middleware('roles:admin,administrative,director,preceptor');
    Route::livewire('/books/{id}/edit', 'books.form')->name('books.edit')->middleware('roles:admin,administrative,director,preceptor');
    Route::livewire('/books/loans', 'books.loans')->name('books.loans')->middleware('roles:admin,administrative,director,preceptor');
});
