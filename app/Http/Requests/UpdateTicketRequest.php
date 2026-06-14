<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = auth('api')->user();

        if ($user?->isEmployee()) {
            return [
                'description' => ['sometimes', 'required', 'string'],
            ];
        }

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'status' => ['sometimes', Rule::in(Ticket::STATUSES)],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'priority' => ['sometimes', Rule::in(Ticket::PRIORITIES)],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['integer', 'distinct', 'exists:tags,id'],
        ];
    }
}
