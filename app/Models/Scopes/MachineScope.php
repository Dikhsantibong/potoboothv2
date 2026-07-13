<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Session;

class MachineScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (app()->runningInConsole() || request()->is('api/*') || request()->is('downloads/*')) {
            return;
        }

        $activeMachineId = Session::get('active_machine_id');
        
        $machineQuery = \App\Models\Machine::query();
        if (auth()->check() && auth()->user()->role === 'mitra') {
            $machineQuery->where('user_id', auth()->id());
        }

        // Verify if session active machine actually belongs to the user, otherwise reset it
        if ($activeMachineId) {
            if (!$machineQuery->clone()->where('id', $activeMachineId)->exists()) {
                $activeMachineId = null;
            }
        }

        if (!$activeMachineId) {
            $activeMachineId = $machineQuery->orderBy('name')->first()?->id;
        }

        if ($activeMachineId) {
            $builder->where($model->getTable() . '.machine_id', $activeMachineId);
        }
    }
}
