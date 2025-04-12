<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\Group;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\AIHomogeneousGroupService;
use Exception;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HomogeneousGroupController extends Controller
{
    /**
     * @var AIHomogeneousGroupService
     */
    protected $aiService;

    /**
     * Constructor
     */
    public function __construct(AIHomogeneousGroupService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Affiche le formulaire de création de groupes homogènes
     *
     * @return Factory|View|Application
     */
    public function index()
    {
        // Récupération des promotions (cohorts) disponibles
        $cohorts = Cohort::all();
        
        return view('pages.groups.homogeneous', [
            'cohorts' => $cohorts
        ]);
    }

    /**
     * Crée des groupes homogènes basés sur les bilans de compétences
     *
     * @param Request $request
     * @return RedirectResponse
     * @throws ValidationException
     */
    public function createHomogeneousGroups(Request $request): RedirectResponse
    {
        // Validation des entrées
        $validated = $this->validateRequest($request);
        
        // Récupération des paramètres
        $cohortId = $validated['cohort_id'];
        $maxStudentsPerGroup = $validated['max_students_per_group'];
        $useAI = $validated['use_ai'] ?? true;
        $useHistory = $validated['use_history'] ?? false;
        
        try {
            // Démarrer une transaction pour assurer l'intégrité des données
            DB::beginTransaction();
            
            // Récupérer la promotion
            $cohort = Cohort::findOrFail($cohortId);
            
            // Création des groupes homogènes
            $groups = [];
            
            if ($useAI) {
                // Tenter d'utiliser l'API IA pour générer les groupes
                $aiGroups = $this->aiService->generateGroups($cohort, $maxStudentsPerGroup, $useHistory);
                
                if ($aiGroups) {
                    // L'API IA a réussi, utiliser ces groupes
                    $groups = $this->saveAIGeneratedGroups($aiGroups, $cohort);
                } else {
                    // L'API IA a échoué, utiliser le fallback
                    $fallbackGroups = $this->aiService->generateGroupsFallback($cohort, $maxStudentsPerGroup);
                    $groups = $this->saveGeneratedGroups($fallbackGroups, $cohort, $maxStudentsPerGroup);
                }
            } else {
                // Ne pas utiliser l'IA, utiliser directement l'algorithme de fallback
                $fallbackGroups = $this->aiService->generateGroupsFallback($cohort, $maxStudentsPerGroup);
                $groups = $this->saveGeneratedGroups($fallbackGroups, $cohort, $maxStudentsPerGroup);
            }
            
            if (empty($groups)) {
                return redirect()->back()->withErrors(['error' => 'Aucun étudiant trouvé dans cette promotion avec des bilans de compétences.']);
            }
            
            // Validation de la transaction
            DB::commit();
            
            return redirect()->route('groups.index')
                ->with('success', count($groups) . ' groupes homogènes ont été créés avec succès.');
                
        } catch (Exception $e) {
            // Annulation de la transaction en cas d'erreur
            DB::rollBack();
            
            // Log de l'erreur
            logger()->error('Erreur lors de la création des groupes homogènes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->back()
                ->withErrors(['error' => 'Une erreur est survenue lors de la création des groupes. Veuillez réessayer ultérieurement.'])
                ->withInput();
        }
    }
    
    /**
     * Valide les entrées du formulaire
     *
     * @param Request $request
     * @return array
     * @throws ValidationException
     */
    private function validateRequest(Request $request): array
    {
        return Validator::make($request->all(), [
            'cohort_id' => 'required|exists:cohorts,id',
            'max_students_per_group' => 'required|integer|min:2|max:10',
            'use_ai' => 'sometimes|boolean',
            'use_history' => 'sometimes|boolean',
        ], [
            'cohort_id.required' => 'Veuillez sélectionner une promotion.',
            'cohort_id.exists' => 'La promotion sélectionnée n\'existe pas.',
            'max_students_per_group.required' => 'Veuillez spécifier le nombre maximum d\'étudiants par groupe.',
            'max_students_per_group.integer' => 'Le nombre d\'étudiants doit être un nombre entier.',
            'max_students_per_group.min' => 'Le nombre minimum d\'étudiants par groupe est de 2.',
            'max_students_per_group.max' => 'Le nombre maximum d\'étudiants par groupe est de 10.',
        ])->validate();
    }
    
    /**
     * Enregistre les groupes générés par l'IA
     *
     * @param array $aiGroups
     * @param Cohort $cohort
     * @return array
     */
    private function saveAIGeneratedGroups(array $aiGroups, Cohort $cohort): array
    {
        $savedGroups = [];
        
        foreach ($aiGroups as $index => $groupData) {
            // Créer un nouveau groupe dans la base de données
            $group = Group::create([
                'cohort_id' => $cohort->id,
                'name' => 'Groupe homogène IA #' . ($index + 1) . ' - ' . $cohort->name,
                'description' => 'Groupe créé automatiquement par l\'IA pour obtenir une répartition homogène des compétences',
                'is_auto_generated' => true,
                'generation_params' => [
                    'ai_generated' => true,
                    'created_at' => now()->toDateTimeString(),
                ]
            ]);
            
            // Ajouter chaque étudiant au groupe
            $totalSkill = 0;
            $count = 0;
            
            foreach ($groupData['members'] as $member) {
                UserGroup::create([
                    'user_id' => $member['id'],
                    'group_id' => $group->id
                ]);
                
                $totalSkill += $member['skill'] ?? 0;
                $count++;
            }
            
            // Calculer la moyenne des compétences
            $averageSkill = $count > 0 ? round($totalSkill / $count, 2) : 0;
            
            // Mettre à jour la description du groupe avec la moyenne
            $group->description .= " (moyenne des compétences: $averageSkill/20)";
            $group->save();
            
            $savedGroups[] = $group;
        }
        
        return $savedGroups;
    }
    
    /**
     * Enregistre les groupes générés par l'algorithme local
     *
     * @param array $generatedGroups
     * @param Cohort $cohort
     * @param int $maxStudentsPerGroup
     * @return array
     */
    private function saveGeneratedGroups(array $generatedGroups, Cohort $cohort, int $maxStudentsPerGroup): array
    {
        $savedGroups = [];
        
        foreach ($generatedGroups as $index => $students) {
            if (empty($students)) continue;
            
            // Créer un nouveau groupe dans la base de données
            $group = Group::create([
                'cohort_id' => $cohort->id,
                'name' => 'Groupe homogène #' . ($index + 1) . ' - ' . $cohort->name,
                'description' => 'Groupe créé automatiquement pour obtenir une répartition homogène des compétences',
                'is_auto_generated' => true,
                'generation_params' => [
                    'max_students_per_group' => $maxStudentsPerGroup,
                    'created_at' => now()->toDateTimeString(),
                ]
            ]);
            
            // Ajouter chaque étudiant au groupe
            $totalSkill = 0;
            
            foreach ($students as $student) {
                UserGroup::create([
                    'user_id' => $student['id'],
                    'group_id' => $group->id
                ]);
                
                $totalSkill += $student['skill'];
            }
            
            // Calculer la moyenne des compétences
            $averageSkill = count($students) > 0 ? round($totalSkill / count($students), 2) : 0;
            
            // Mettre à jour la description du groupe avec la moyenne
            $group->description .= " (moyenne des compétences: $averageSkill/20)";
            $group->save();
            
            $savedGroups[] = $group;
        }
        
        return $savedGroups;
    }
}
