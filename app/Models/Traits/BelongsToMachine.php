<?php

namespace App\Models\Traits;

use App\Models\Scopes\MachineScope;
use Illuminate\Support\Facades\Session;

trait BelongsToMachine
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToMachine(): void
    {
        static::addGlobalScope(new MachineScope);

        static::creating(function ($model) {
            if (empty($model->machine_id) && !app()->runningInConsole() && !request()->is('api/*')) {
                $activeMachineId = Session::get('active_machine_id');
                
                $machineQuery = \App\Models\Machine::query();
                if (auth()->check() && auth()->user()->role === 'mitra') {
                    $machineQuery->where('user_id', auth()->id());
                }

                if ($activeMachineId) {
                    if (!$machineQuery->clone()->where('id', $activeMachineId)->exists()) {
                        $activeMachineId = null;
                    }
                }

                if (!$activeMachineId) {
                    $activeMachineId = $machineQuery->orderBy('name')->first()?->id;
                }

                if ($activeMachineId) {
                    $model->machine_id = $activeMachineId;
                }
            }
        });
    }
}
