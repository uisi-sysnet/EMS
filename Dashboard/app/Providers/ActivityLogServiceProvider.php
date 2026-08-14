<?php
// app/Providers/ActivityLogServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Events\ModelCreated;
use Illuminate\Database\Events\ModelUpdated;
use Illuminate\Database\Events\ModelDeleted;
use Illuminate\Database\Events\ModelRestored;
use Illuminate\Database\Events\ModelsPruned;

class ActivityLogServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Event::listen(ModelCreated::class, function ($event) {
            $this->logActivity($event->model, 'created');
        });

        Event::listen(ModelUpdated::class, function ($event) {
            $this->logActivity($event->model, 'updated');
        });

        Event::listen(ModelDeleted::class, function ($event) {
            $this->logActivity($event->model, 'deleted');
        });

        Event::listen(ModelRestored::class, function ($event) {
            $this->logActivity($event->model, 'restored');
        });
    }

    protected function logActivity($model, $event)
    {
        // Skip logging for ActivityLog model itself to prevent infinite loop
        if ($model instanceof \App\Models\ActivityLog) {
            return;
        }

        // Skip logging for certain models if needed
        $excludeModels = [
            // \App\Models\SomeModel::class,
        ];

        if (in_array(get_class($model), $excludeModels)) {
            return;
        }

        $user = auth()->user();
        
        $properties = [];
        
        if ($event === 'created') {
            $properties['attributes'] = $model->getAttributes();
        } elseif ($event === 'updated') {
            $properties['old'] = $model->getOriginal();
            $properties['new'] = $model->getAttributes();
            $properties['changed'] = $model->getDirty();
        } elseif ($event === 'deleted' || $event === 'restored') {
            $properties['old'] = $model->getAttributes();
        }

        \App\Models\ActivityLog::create([
            'log_name' => 'database',
            'description' => $this->getDescription($model, $event),
            'subject_type' => get_class($model),
            'subject_id' => $model->getKey(),
            'causer_type' => $user ? get_class($user) : null,
            'causer_id' => $user ? $user->id : null,
            'properties' => $properties,
            'event' => $event,
        ]);
    }

    protected function getDescription($model, $event): string
    {
        $modelName = class_basename($model);
        $subject = $model->getKey();
        $user = auth()->user();
        $userName = $user ? $user->name : 'System';

        return match ($event) {
            'created' => "{$userName} created a new {$modelName} (ID: {$subject})",
            'updated' => "{$userName} updated {$modelName} (ID: {$subject})",
            'deleted' => "{$userName} deleted {$modelName} (ID: {$subject})",
            'restored' => "{$userName} restored {$modelName} (ID: {$subject})",
            default => "{$userName} performed {$event} on {$modelName} (ID: {$subject})",
        };
    }
}