<x-app-layout>
    <x-slot name="header">
        <h1 class="flex items-center gap-1 text-sm font-normal">
            <span class="text-gray-700">
                {{ __('Groupes') }}
            </span>
        </h1>
    </x-slot>

    <div class="py-6">
        <form method="POST" action="{{ route('groups.generate') }}" class="space-y-4 max-w-lg mx-auto">
            @csrf
            <div>
                <label for="cohort_id" class="block text-sm font-medium text-gray-700">Promotion</label>
                <select id="cohort_id" name="cohort_id" class="select w-full mt-1" required>
                    <option value="">Sélectionner une promotion</option>
                    @foreach($cohorts as $cohort)
                        <option value="{{ $cohort->id }}">{{ $cohort->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="group_size" class="block text-sm font-medium text-gray-700">Nombre d'étudiants par groupe</label>
                <input type="number" min="2" max="50" id="group_size" name="group_size" class="input w-full mt-1" required>
            </div>
            <div>
                <button type="submit" class="btn btn-primary w-full">Générer les groupes</button>
            </div>
        </form>
    </div>

    @if(session('info'))
        <div class="mt-4 max-w-lg mx-auto">
            <div class="alert alert-info">
                {{ session('info') }}
            </div>
        </div>
    @endif

    @isset($groups)
        <div class="mt-8 max-w-2xl mx-auto">
            <h2 class="text-lg font-semibold mb-4">Groupes générés</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($groups as $group)
                    <div class="p-4 border rounded shadow">
                        <h3 class="font-bold mb-2">{{ $group->name }}</h3>
                        <ul class="list-disc pl-5">
                            @foreach($group->users as $user)
                                <li>{{ $user->fullName }} (note: {{ $user->competence_score ?? 'N/A' }})</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </div>
    @endisset

    <!-- Affichage des groupes existants par promotion -->
    @isset($groupsByCohort)
        <div class="mt-12">
            <h2 class="text-xl font-bold mb-6 text-center">Groupes existants par promotion</h2>
            
            @forelse($groupsByCohort as $cohortId => $cohortGroups)
                @php
                    $cohortName = $cohorts->where('id', $cohortId)->first()->name ?? 'Promotion inconnue';
                @endphp
                
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 border-b pb-2">{{ $cohortName }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        @foreach($cohortGroups as $group)
                            <div class="card p-4">
                                <div class="card-header pb-2">
                                    <h4 class="card-title text-primary">{{ $group->name }}</h4>
                                    <p class="text-xs text-gray-500">
                                        @if(isset($group->generation_params['date_generated']))
                                            Créé le: {{ \Carbon\Carbon::parse($group->generation_params['date_generated'])->format('d/m/Y H:i') }}
                                        @endif
                                    </p>
                                </div>
                                <div class="card-body">
                                    @if($group->users->count() > 0)
                                        <ul class="list-disc pl-5">
                                            @foreach($group->users as $user)
                                                <li>{{ $user->last_name }} {{ $user->first_name }} 
                                                    @if(isset($user->competence_score))
                                                        <span class="text-xs text-gray-500">(niveau: {{ $user->competence_score }})</span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="text-gray-500 italic">Aucun étudiant dans ce groupe</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-center text-gray-500">Aucun groupe n'a encore été créé</p>
            @endforelse
        </div>
    @endisset
</x-app-layout>
