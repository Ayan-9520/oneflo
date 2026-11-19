<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Http\Requests\ApiFormRequest;

class AccountActivationRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'active' => ['required', 'boolean'],
        ];
    }
}
