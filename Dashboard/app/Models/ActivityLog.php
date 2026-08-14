<?php
// app/Models/ActivityLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'batch_uuid',
        'event'
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function getChangesAttribute()
    {
        $properties = $this->properties ?? [];
        
        $old = $properties['old'] ?? [];
        $new = $properties['attributes'] ?? $properties['new'] ?? [];
        
        // If it's an update event, show before/after
        if ($this->event === 'updated' && !empty($old) && !empty($new)) {
            $changes = [];
            foreach ($new as $key => $value) {
                $oldValue = $old[$key] ?? null;
                if ($oldValue != $value) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $value
                    ];
                }
            }
            return $changes;
        }
        
        // For created events, show all attributes
        if ($this->event === 'created' && !empty($new)) {
            return $new;
        }
        
        // For deleted events, show what was deleted
        if ($this->event === 'deleted' && !empty($old)) {
            return $old;
        }
        
        return null;
    }

    public function getFormattedChangesAttribute()
    {
        $changes = $this->changes;
        if (empty($changes)) {
            return null;
        }

        $formatted = [];
        foreach ($changes as $field => $change) {
            if (is_array($change) && isset($change['old']) && isset($change['new'])) {
                $formatted[] = "{$field}: {$change['old']} → {$change['new']}";
            } elseif (is_array($change)) {
                $formatted[] = "{$field}: " . json_encode($change);
            } else {
                $formatted[] = "{$field}: {$change}";
            }
        }
        return implode(', ', $formatted);
    }
}