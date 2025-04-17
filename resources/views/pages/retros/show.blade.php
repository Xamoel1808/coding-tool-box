<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold">{{ $retro->name }} - {{ $retro->cohort->name }}</h1>
            <a href="{{ route('retro.index') }}" class="btn btn-secondary">{{ __('Retour') }}</a>
        </div>
    </x-slot>

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/jkanban@1.2.0/dist/jkanban.min.css"/>
    @endpush

    <div class="py-6">
        <div id="myKanban" class="kanban-board"></div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/jkanban@1.2.0/dist/jkanban.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var boards = {!! json_encode($retro->columns->map(function($col) {
                    return [
                        'id' => 'board-' . $col->id,
                        'title' => $col->name,
                        'item' => $col->data->map(function($d) {
                            return [
                                'id' => 'item-' . $d->id,
                                'title' => '<strong>' . e($d->name) . '</strong><br>' . e($d->description ?? '')
                            ];
                        })->toArray(),
                    ];
                })) !!};
                
                new jKanban({
                    element: '#myKanban',
                    boards: boards,
                    dragItems: false
                });
            });
        </script>
    @endpush
</x-app-layout>
