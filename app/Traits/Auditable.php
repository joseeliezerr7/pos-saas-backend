<?php

namespace App\Traits;

use App\Models\Audit\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->auditEvent('created');
        });

        static::updated(function (Model $model) {
            if ($model->isDirty()) {
                $model->auditEvent('updated');
            }
        });

        static::deleted(function (Model $model) {
            $model->auditEvent('deleted');
        });
    }

    protected function auditEvent(string $event): void
    {
        if (!$this->shouldAudit($event)) {
            return;
        }

        $oldValues = null;
        $newValues = null;

        if ($event === 'updated') {
            $oldValues = array_intersect_key($this->getOriginal(), $this->getDirty());
            $newValues = $this->getDirty();
        } elseif ($event === 'created') {
            $newValues = $this->getAttributes();
        } elseif ($event === 'deleted') {
            $oldValues = $this->getOriginal();
        }

        AuditLog::create([
            'tenant_id' => $this->tenant_id ?? null,
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'url' => Request::fullUrl(),
        ]);
    }

    protected function shouldAudit(string $event): bool
    {
        if (property_exists($this, 'auditEvents')) {
            return in_array($event, $this->auditEvents);
        }

        if (property_exists($this, 'auditExclude')) {
            return !in_array($event, $this->auditExclude);
        }

        return true;
    }
}
