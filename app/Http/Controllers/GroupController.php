<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    public function index()
    {
        // Récupérer l'utilisateur actuel et son rôle
        $user = Auth::user();
        $userRole = $user->school()->pivot->role ?? null;
        
        // Différent comportement selon le rôle
        if ($userRole === 'student') {
            // Pour les étudiants, montrer uniquement leurs groupes
            $userGroups = $user->groups()->with(['cohort'])->get();
            return view('pages.groups.student_index', compact('userGroups'));
        } else {
            // Pour les enseignants et les admins, montrer tous les groupes
            $cohorts = Cohort::all();
            $groupsByCohort = Group::with(['users', 'cohort'])->get()->groupBy('cohort_id');
            return view('pages.groups.index', compact('cohorts', 'groupsByCohort'));
        }
    }

    public function generate(Request $request)
    {
        // Le middleware est maintenant appliqué au niveau des routes
        
        $request->validate([
            'cohort_id' => 'required|exists:cohorts,id',
            'group_size' => 'required|integer|min:2',
            'replace_existing' => 'sometimes|boolean',
        ]);

        $cohort = Cohort::findOrFail($request->cohort_id);
        $students = $cohort->users;
        
        // Vérifier si des groupes existent déjà pour cette promotion
        $existingGroups = Group::where('cohort_id', $cohort->id)->exists();
        if ($existingGroups && !$request->has('replace_existing')) {
            return redirect()->route('groups.index')
                ->with('info', 'Des groupes existent déjà pour cette promotion. Aucun nouveau groupe n\'a été créé.');
        }

        // Mélange aléatoire des étudiants
        $students = $students->shuffle()->values();

        $groupSize = $request->group_size;
        $groups = collect();
        
        // Calculate how many groups we need based on number of students and group size
        $groupCount = (int) ceil($students->count() / $groupSize);

        // Create empty group objects
        for ($i = 0; $i < $groupCount; $i++) {
            $group = new Group();
            $group->name = 'Groupe ' . ($i + 1);
            $group->cohort_id = $cohort->id;
            $group->is_auto_generated = true;
            $group->generation_params = [
                'group_size' => $groupSize,
                'date_generated' => now()->toDateTimeString()
            ];
            $groups->push($group);
        }

        // Préparation des user IDs pour chaque groupe
        $groupUserIds = [];
        
        // Instead of distributing one by one using the modulo,
        // create chunks of groupSize students
        $studentChunks = $students->chunk($groupSize);
        
        // Assign each chunk to a group
        foreach ($studentChunks as $index => $chunk) {
            if ($index < $groupCount) {
                $groupUserIds[$index] = $chunk->pluck('id');
            } else {
                // If we have more chunks than groups, add remaining students to the last group
                $groupUserIds[$groupCount-1] = $groupUserIds[$groupCount-1]->merge($chunk->pluck('id'));
            }
        }

        // Enregistrement en base
        DB::transaction(function () use ($groups, $groupUserIds, $cohort, $request) {
            // Suppression des anciens groupes de la cohorte seulement si l'option est activée
            if ($request->has('replace_existing')) {
                Group::where('cohort_id', $cohort->id)->delete();
            }
            
            foreach ($groups as $i => $group) {
                $group->save();
                
                // Make sure groupUserIds[$i] exists before syncing
                if (isset($groupUserIds[$i])) {
                    $group->users()->sync($groupUserIds[$i]);
                }
            }
        });

        // Rafraîchir les groupes avec les utilisateurs
        $groups = Group::where('cohort_id', $cohort->id)->with('users')->get();

        $cohorts = Cohort::all();
        return view('pages.groups.index', compact('cohorts', 'groups'));
    }
}
