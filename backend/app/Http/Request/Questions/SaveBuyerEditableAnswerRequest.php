<?php

namespace HiEvents\Http\Request\Questions;

use HiEvents\Http\Request\BaseRequest;

class SaveBuyerEditableAnswerRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'integer'],
            'attendee_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'answer' => ['present', 'nullable'],
            'answer.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
