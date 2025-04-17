<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\Retro;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RetroController extends Controller
{
    /**
     * Display list of retrospectives with filtering by role
     */
    public function index()
    {
        $user = Auth::user();
        $userRole = $user->school()->pivot->role ?? null;

        $query = Retro::with(['cohort', 'user']);

        if ($userRole === 'teacher') {
            $query->where('user_id', $user->id);
        }

        $retros = $query->get()->groupBy('cohort_id');

        $cohorts = Cohort::all();

        return view('pages.retros.index', compact('retros', 'cohorts'));
    }

    /**
     * Show form to create a new retro
     */
    public function create()
    {
        $cohorts = Cohort::all();
        return view('pages.retros.create', compact('cohorts'));  
    }

    /**
     * Store a new retro with its columns
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'cohort_id' => 'required|exists:cohorts,id',
            'columns' => 'required|array|min:1',
            'columns.*' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($request) {
            $retro = Retro::create([
                'name' => $request->input('name'),
                'cohort_id' => $request->input('cohort_id'),
                'user_id' => Auth::id(),
            ]);

            foreach ($request->input('columns') as $index => $colName) {
                $retro->columns()->create([
                    'name' => $colName,
                    'position' => $index,
                ]);
            }
        });

        return redirect()->route('retro.index')
            ->with('success', __('Rétrospective créée avec succès.'));
    }

    /**
     * Display a single retrospective Kanban board
     */
    public function show(Retro $retro)
    {
        $retro->load(['columns.data']);
        return view('pages.retros.show', compact('retro'));
    }

    /**
     * Remove the specified retrospective
     */
    public function destroy(Retro $retro)
    {
        $retro->delete();

        return redirect()->route('retro.index')
            ->with('success', __('Rétrospective supprimée avec succès.'));
    }
}
