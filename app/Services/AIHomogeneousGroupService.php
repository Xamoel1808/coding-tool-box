<?php

namespace App\Services;

use App\Models\Cohort;
use App\Models\User;
use App\Models\Group;
use Illuminate\Support\Facades\Http;

class AIHomogeneousGroupService
{
    /**
     * Appelle l'API Gemini pour générer des groupes homogènes
     * @param Cohort $cohort
     * @param int $maxStudentsPerGroup
     * @param bool $useHistory
     * @return array|null
     */
    public function generateGroups(Cohort $cohort, int $maxStudentsPerGroup, bool $useHistory = false): ?array
    {
        // Récupérer les étudiants et leurs notes
        $students = $cohort->users()->with('grades')->get();
        if ($students->isEmpty()) return null;
        
        logger()->info('Generating groups for cohort', [
            'cohort_id' => $cohort->id,
            'cohort_name' => $cohort->name,
            'students_count' => $students->count(),
            'max_per_group' => $maxStudentsPerGroup
        ]);

        $studentData = $students->map(function ($student) {
            return [
                'id' => $student->id,
                'name' => $student->first_name . ' ' . $student->last_name,
                'skill' => optional($student->grades->first())->score ?? rand(8, 16), // fallback aléatoire pour test
            ];
        })->toArray();

        // Historique des groupes
        $history = [];
        if ($useHistory) {
            foreach ($students as $student) {
                $history[$student->id] = $student->groups->pluck('id')->toArray();
            }
        }

        // Appel à l'API Gemini v1beta2
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-goog-api-key' => config('services.ai.key')
        ])->post(config('services.ai.url'), [
            'contents' => [
                'role' => 'user',
                'parts' => [
                    [
                        'text' => "OBJECTIF: Créer des groupes d'étudiants équilibrés où chaque groupe aura une moyenne de compétences similaire.

INSTRUCTIONS:
1. Répartis TOUS les étudiants de la liste en groupes de $maxStudentsPerGroup membres maximum.
2. Aucun étudiant ne doit être oublié ou laissé de côté.
3. Les groupes doivent être les plus homogènes possibles (moyenne de compétences similaire entre tous les groupes).
4. Évite les groupes incomplets quand c'est possible (préfère des groupes complets).
5. Si le nombre d'étudiants n'est pas divisible par $maxStudentsPerGroup, répartis les étudiants restants dans les groupes existants.
6. Si possible, utilise l'historique des groupes pour éviter de mettre ensemble des étudiants qui ont déjà travaillé ensemble.

FORMAT DE RÉPONSE:
Retourne un objet JSON au format précis suivant:
{\"groups\": [{\"members\": [{\"id\": 1, \"skill\": 12}]}]}

DONNÉES ÉTUDIANTS (id, nom, note):\n" .
implode("\n", array_map(function($s) { return $s['id']." | ".$s['name']." | note: ".$s['skill']; }, $studentData)) .
"\n\nDONNÉES JSON:\n" .
json_encode([
    'students' => $studentData,
    'max_students_per_group' => $maxStudentsPerGroup,
    'history' => $history,
], JSON_PRETTY_PRINT)
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                'topK' => 40,
                'topP' => 0.8,
                'maxOutputTokens' => 1024,
                'responseMimeType' => 'application/json'
            ]
        ]);
        logger()->info('AI response status '.$response->status(), ['body' => $response->body()]);

        if ($response->successful()) {
            // Extraction de la réponse et parsing...
            if (isset($response['candidates'][0]['content']['parts'][0]['text'])) {
                $jsonText = $response['candidates'][0]['content']['parts'][0]['text'];
                
                // Tenter de parser le JSON retourné
                try {
                    $jsonData = json_decode($jsonText, true);
                    logger()->info('AI parsed response', ['data' => $jsonData]);
                    
                    if (isset($jsonData['groups'])) {
                        return $jsonData['groups'];
                    }
                } catch (\Exception $e) {
                    logger()->error('Failed to parse AI response', ['error' => $e->getMessage()]);
                }
            }
        }
        
        // Aucune réponse exploitable de l'IA
        return null;
    }
}
