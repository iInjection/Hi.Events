<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTranslationSetting extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'locales' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
