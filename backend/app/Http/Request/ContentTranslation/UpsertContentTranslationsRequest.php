<?php

namespace HiEvents\Http\Request\ContentTranslation;

use HiEvents\DomainObjects\Enums\TranslatableContentType;
use HiEvents\Http\Request\BaseRequest;
use HiEvents\Locale;
use Illuminate\Validation\Rule;

class UpsertContentTranslationsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'locale' => ['required', Rule::in(Locale::valuesArray())],
            'translations' => ['required', 'array'],
            'translations.*.type' => ['required', Rule::in(TranslatableContentType::valuesArray())],
            'translations.*.id' => ['required', 'integer'],
            'translations.*.field' => ['required', 'string', 'max:64'],
            'translations.*.value' => ['present', 'nullable'],
            'translations.*.value.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
