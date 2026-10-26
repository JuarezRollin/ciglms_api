<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use LogicException;

class BatchGl extends Pivot
{
    protected $table = 'batch_gl';

    public $timestamps = false;

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        if ($this->exists && $this->isDirty(['batch_id', 'gl_id'])) {
            throw new LogicException('Change batch membership using attach, detach, or sync.');
        }

        return $this->getConnection()->transaction(function () use ($options): bool {
            $batch = Batch::query()->whereKey($this->batch_id)->lockForUpdate()->firstOrFail();
            $saved = parent::save($options);
            if ($saved) {
                $batch->recalculateTotal();
            }

            return $saved;
        });
    }

    public function delete(): int|bool|null
    {
        return $this->getConnection()->transaction(function (): int|bool|null {
            $batch = Batch::query()->whereKey($this->batch_id)->lockForUpdate()->firstOrFail();
            $deleted = parent::delete();
            if ($deleted) {
                $batch->recalculateTotal();
            }

            return $deleted;
        });
    }
}
