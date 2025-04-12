<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function index()
    {
        // Récupérer tous les utilisateurs avec le rôle "student"
        $students = User::join('users_schools', 'users.id', '=', 'users_schools.user_id')
            ->where('users_schools.role', 'student')
            ->select('users.*')
            ->get();

        return view('pages.students.index', [
            'students' => $students
        ]);
    }

    public function store(Request $request)
    {
        // Validation des données
        $validated = $request->validate([
            'last_name' => 'required|string|max:255',
            'first_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'birth_date' => 'nullable|date',
            'password' => 'required|min:6',
        ]);

        // Création de l'étudiant
        $student = new User();
        $student->last_name = $validated['last_name'];
        $student->first_name = $validated['first_name'];
        $student->email = $validated['email'];
        $student->birth_date = $validated['birth_date'] ?? null;
        $student->password = Hash::make($validated['password']);
        $student->save();

        // Associer l'étudiant à l'école avec le rôle "student"
        $school = auth()->user()->school();
        if ($school) {
            $student->belongsToMany(\App\Models\School::class, 'users_schools')
                ->withPivot('role')
                ->attach($school->id, ['role' => 'student']);
        }

        return redirect()->route('student.index')
            ->with('success', 'L\'étudiant a été ajouté avec succès');
    }
}
