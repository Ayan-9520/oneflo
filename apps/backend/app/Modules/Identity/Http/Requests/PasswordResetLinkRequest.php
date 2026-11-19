<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Http\Requests\ApiFormRequest;

class PasswordResetLinkRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
