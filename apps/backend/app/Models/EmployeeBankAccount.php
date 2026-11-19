<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class EmployeeBankAccount extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['employee_id', 'bank_name', 'ifsc', 'account_number'];

    protected function casts(): array
    {
        return ['account_number' => 'encrypted'];
    }

    public function maskedAccount(): string
    {
        $number = (string) $this->account_number;

        return str_repeat('X', max(0, strlen($number) - 4)).substr($number, -4);
    }
}
