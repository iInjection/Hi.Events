<?php

namespace HiEvents\Http\Request\ContentTranslation;

use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;
use Illuminate\Validation\Rule;

class UpdateEventTranslationSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'source_locale' => ['required', Rule::in(Locale::valuesArray())],
            'fallback_locale' => ['nullable', Rule::in(Locale::valuesArray())],
            'locales' => ['present', 'array'],
            'locales.*' => [Rule::in(Locale::valuesArray())],
        ];
    }
}
