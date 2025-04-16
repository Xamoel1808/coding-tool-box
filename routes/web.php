<?php

use App\Http\Controllers\CohortController;
use App\Http\Controllers\CommonLifeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RetroController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\HomogeneousGroupController;
use App\Http\Controllers\TeacherController;
use App\Http\Middleware\EnsureUserIsTeacherOrAdmin;
use Illuminate\Support\Facades\Route;

// Redirect the root path to /dashboard
Route::redirect('/', 'dashboard');

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('verified')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Cohorts
        Route::get('/cohorts', [CohortController::class, 'index'])->name('cohort.index');
        Route::post('/cohorts', [CohortController::class, 'store'])->name('cohort.store');
        Route::get('/cohort/{cohort}', [CohortController::class, 'show'])->name('cohort.show');
        Route::post('/cohort/{cohort}/add-student', [CohortController::class, 'addStudent'])->name('cohort.addStudent');
        Route::delete('/cohort/{cohort}/remove-student/{user}', [CohortController::class, 'removeStudent'])->name('cohort.removeStudent');

        // Teachers
        Route::get('/teachers', [TeacherController::class, 'index'])->name('teacher.index');
        Route::post('/teachers', [TeacherController::class, 'store'])->name('teacher.store');

        // Students
        Route::get('students', [StudentController::class, 'index'])->name('student.index');
        Route::post('students', [StudentController::class, 'store'])->name('student.store');

        // Knowledge
        Route::get('knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');

        // Groups - Route accessible à tous les utilisateurs authentifiés
        Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
        
        // Groups - Routes accessibles uniquement aux enseignants et administrateurs
        Route::middleware('teacher-admin')->group(function () {
            Route::post('groups/generate', [GroupController::class, 'generate'])->name('groups.generate');
        });

        // Homogeneous Groups (Admin only)
        Route::middleware('admin')->group(function () {
            Route::get('/groups/homogeneous', [HomogeneousGroupController::class, 'index'])->name('groups.homogeneous');
            Route::post('/groups/homogeneous', [HomogeneousGroupController::class, 'createHomogeneousGroups'])->name('groups.homogeneous.create');
        });

        // Retro
        route::get('retros', [RetroController::class, 'index'])->name('retro.index');

        // Common life
        Route::get('common-life', [CommonLifeController::class, 'index'])->name('common-life.index');

        // Grades - Routes accessibles à tous les utilisateurs authentifiés
        Route::get('/grades/student', [GradeController::class, 'studentGrades'])->name('grades.student');

        // Grades - Routes accessibles uniquement aux enseignants et administrateurs
        Route::middleware(EnsureUserIsTeacherOrAdmin::class)->group(function () {
            Route::get('/grades', [GradeController::class, 'index'])->name('grades.index');
            Route::get('/grades/create', [GradeController::class, 'create'])->name('grades.create');
            Route::post('/grades', [GradeController::class, 'store'])->name('grades.store');
            Route::get('/grades/{grade}', [GradeController::class, 'show'])->name('grades.show');
            Route::get('/grades/{grade}/edit', [GradeController::class, 'edit'])->name('grades.edit');
            Route::put('/grades/{grade}', [GradeController::class, 'update'])->name('grades.update');
            Route::delete('/grades/{grade}', [GradeController::class, 'destroy'])->name('grades.destroy');
            Route::get('/grades/get-students', [GradeController::class, 'getStudents'])->name('grades.getStudents');
        });
    });

});

require __DIR__.'/auth.php';
