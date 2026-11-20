<?php

declare(strict_types=1);

namespace App\Modules\Recruitment\Services;

use App\Models\Candidate;
use App\Models\CandidateImport;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\OrganizationContext;
use App\Support\Spreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Bulk candidate import into the active business. Creates candidate records only:
 * never applications, employees, payroll, joining, or onboarding records.
 */
final class CandidateImportService
{
    public const SOURCE = 'Excel Import';

    public const MAX_ROWS = 2000;

    private const BATCH = 100;

    /**
     * System field => [label, required, header aliases].
     *
     * @var array<string, array{0: string, 1: bool, 2: list<string>}>
     */
    public const FIELDS = [
        'full_name' => ['Full name', false, ['full name', 'name', 'candidate name', 'candidate']],
        'given_name' => ['First name', false, ['first name', 'given name', 'firstname']],
        'family_name' => ['Last name', false, ['last name', 'family name', 'surname', 'lastname']],
        'email' => ['Email', false, ['email', 'email address', 'email id', 'e-mail', 'mail']],
        'phone' => ['Mobile number', false, ['phone', 'mobile', 'mobile number', 'phone number', 'contact', 'contact number', 'mobile no']],
        'location' => ['Location', false, ['location', 'city', 'current location', 'current city']],
        'experience_years' => ['Experience (years)', false, ['experience years', 'experience', 'total experience', 'exp', 'years of experience', 'experience in years']],
        'current_company' => ['Current company', false, ['current company', 'company', 'employer', 'current employer']],
        'current_designation' => ['Current designation', false, ['current designation', 'designation', 'current role', 'job title', 'role']],
        'notice_period' => ['Notice period', false, ['notice period', 'notice', 'notice period days']],
        'qualification' => ['Qualification', false, ['qualification', 'education', 'highest qualification', 'degree']],
        'skills' => ['Skills', false, ['skills', 'key skills', 'skill set']],
        'business' => ['Business', false, ['business', 'business name', 'organization', 'organisation', 'hiring business']],
        'candidate_type' => ['Candidate type', false, ['candidate type', 'type']],
        'is_test_data' => ['Is test data', false, ['is test data', 'test data', 'dummy', 'is dummy']],
        'notes' => ['Notes', false, ['notes', 'note', 'remarks', 'comments']],
    ];

    private const LIMITS = [
        'given_name' => 80, 'family_name' => 80, 'email' => 190, 'location' => 120, 'current_company' => 160,
        'current_designation' => 160, 'notice_period' => 80, 'qualification' => 160, 'skills' => 2000, 'notes' => 2000,
    ];

    public function __construct(
        private readonly OrganizationContext $context,
        private readonly AuditLogger $audit,
    ) {}

    public function template(): string
    {
        return Spreadsheet::xlsx([[
            'full_name', 'email', 'phone', 'location', 'experience_years', 'current_company', 'current_designation',
            'notice_period', 'qualification', 'skills', 'business', 'candidate_type', 'is_test_data', 'notes',
        ]]);
    }

    public function preview(UploadedFile $file, User $actor): CandidateImport
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension === 'xls') {
            throw ValidationException::withMessages(['file' => ['Older .xls files are not supported. In Excel choose File > Save As > Excel Workbook (.xlsx), or CSV, and upload again.']]);
        }
        try {
            $hints = collect(self::FIELDS)->flatMap(fn (array $field, string $key) => [$key, ...$field[2]])
                ->map(fn (string $name) => $this->key($name))->unique()->values()->all();
            $sheet = Spreadsheet::read((string) $file->getRealPath(), $extension, self::MAX_ROWS, $hints);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['file' => [$exception->getMessage()]]);
        }
        $headerNumber = array_key_first($sheet);
        if ($headerNumber === null) {
            throw ValidationException::withMessages(['file' => ['The file is empty.']]);
        }
        $headers = array_map(fn (string $value) => mb_substr($value, 0, 120), $sheet[$headerNumber]);
        unset($sheet[$headerNumber]);
        $raw = [];
        foreach ($sheet as $number => $cells) {
            if (implode('', $cells) === '') {
                continue;
            }
            $raw[] = ['row' => $number, 'cells' => array_map(fn (string $value) => mb_substr($value, 0, 2000), $cells)];
        }
        if ($raw === []) {
            throw ValidationException::withMessages(['file' => ['The file has a header row but no candidate rows.']]);
        }
        if (count($raw) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => ['Import up to '.self::MAX_ROWS.' candidates per file. Split the file and upload each part.']]);
        }

        $import = new CandidateImport([
            'user_id' => $actor->id,
            'file_name' => mb_substr(basename($file->getClientOriginalName()), 0, 200),
            'status' => 'previewed',
            'headers' => $headers,
            'mapping' => $this->guessMapping($headers),
        ]);
        $this->evaluate($import, $raw);
        $import->save();
        $this->audit->accessChange($actor, $this->organization(), 'candidate_import.previewed', $this->auditCounts($import));

        return $import;
    }

    /**
     * @param  array<string, mixed>  $mapping  Column index => system field.
     */
    public function remap(CandidateImport $import, array $mapping): CandidateImport
    {
        $this->assertOpen($import);
        $clean = [];
        foreach ($mapping as $column => $field) {
            if (is_numeric($column) && is_string($field) && array_key_exists($field, self::FIELDS) && (int) $column < count($import->headers)) {
                $clean[(string) (int) $column] = $field;
            }
        }
        if (count($clean) !== count(array_unique($clean))) {
            throw ValidationException::withMessages(['mapping' => ['Map each system field to one column only.']]);
        }
        $import->mapping = $clean;
        $this->evaluate($import, array_map(fn (array $row) => ['row' => $row['row'], 'cells' => $row['cells']], $import->rows));
        $import->save();

        return $import;
    }

    public function confirm(CandidateImport $import, User $actor): CandidateImport
    {
        $locked = DB::transaction(function () use ($import): CandidateImport {
            $row = CandidateImport::query()->whereKey($import->id)->lockForUpdate()->firstOrFail();
            $this->assertOpen($row);
            $row->status = 'importing';
            $row->save();

            return $row;
        });

        $rows = $locked->rows;
        $imported = 0;
        $failed = 0;
        $duplicates = $locked->duplicate_rows;
        foreach (array_chunk(array_keys($rows), self::BATCH) as $batch) {
            DB::transaction(function () use (&$rows, $batch, $locked, &$imported, &$failed, &$duplicates): void {
                foreach ($batch as $index) {
                    $row = $rows[$index];
                    if ($row['status'] !== 'valid') {
                        continue;
                    }
                    try {
                        DB::transaction(function () use (&$rows, $index, $row, $locked, &$imported, &$duplicates): void {
                            if ($this->existing($row['data']['email'], $row['data']['phone']) !== null) {
                                $rows[$index]['status'] = 'duplicate';
                                $rows[$index]['errors'] = ['A candidate with this email or mobile number already exists in this business.'];
                                $duplicates++;

                                return;
                            }
                            $candidate = Candidate::query()->create([
                                ...$row['data'],
                                'source' => self::SOURCE,
                                'status' => 'active',
                                'candidate_import_id' => $locked->id,
                            ]);
                            $rows[$index]['status'] = 'imported';
                            $rows[$index]['candidate_id'] = $candidate->id;
                            $imported++;
                        });
                    } catch (Throwable $exception) {
                        report($exception);
                        $rows[$index]['status'] = 'failed';
                        $rows[$index]['errors'] = ['This row could not be saved. Check the values and try again.'];
                        $failed++;
                    }
                }
            });
        }

        $locked->rows = $rows;
        $locked->status = 'imported';
        $locked->imported_rows = $imported;
        $locked->failed_rows = $failed;
        $locked->duplicate_rows = $duplicates;
        $locked->valid_rows = $imported;
        $locked->imported_at = now();
        $locked->save();
        $this->audit->accessChange($actor, $this->organization(), 'candidate_import.confirmed', $this->auditCounts($locked));

        return $locked;
    }

    public function cancel(CandidateImport $import, User $actor): CandidateImport
    {
        $this->assertOpen($import);
        $import->status = 'cancelled';
        $import->save();
        $this->audit->accessChange($actor, $this->organization(), 'candidate_import.cancelled', ['import_id' => $import->id]);

        return $import;
    }

    public function errorReport(CandidateImport $import): string
    {
        $handle = fopen('php://temp', 'r+b');
        fputcsv($handle, ['Excel row', 'Status', 'Problems', ...$import->headers], ',', '"', '');
        foreach ($import->rows as $row) {
            if (in_array($row['status'], ['valid', 'imported'], true)) {
                continue;
            }
            $cells = array_map(fn (string $value) => Spreadsheet::safeCell($value), $row['cells']);
            fputcsv($handle, [$row['row'], $row['status'], Spreadsheet::safeCell(implode(' | ', $row['errors'])), ...$cells], ',', '"', '');
        }
        rewind($handle);
        $csv = "\xEF\xBB\xBF".stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(CandidateImport $import): array
    {
        return [
            'id' => $import->id,
            'file_name' => $import->file_name,
            'status' => $import->status,
            'headers' => $import->headers,
            'mapping' => (object) $import->mapping,
            'fields' => collect(self::FIELDS)->map(fn (array $field, string $key) => ['key' => $key, 'label' => $field[0]])->values()->all(),
            'summary' => [
                'total' => $import->total_rows,
                'valid' => $import->valid_rows,
                'invalid' => $import->invalid_rows,
                'duplicate' => $import->duplicate_rows,
                'importable' => $import->status === 'imported' ? 0 : $import->valid_rows,
                'imported' => $import->imported_rows,
                'skipped' => $import->invalid_rows,
                'failed' => $import->failed_rows,
                'test_data' => collect($import->rows)->where('data.is_test_data', true)->count(),
            ],
            'rows' => array_map(fn (array $row) => [
                'row' => $row['row'],
                'status' => $row['status'],
                'errors' => $row['errors'],
                'name' => trim(($row['data']['given_name'] ?? '').' '.($row['data']['family_name'] ?? '')),
                'email' => $row['data']['email'] ?? null,
                'phone' => $row['data']['phone'] ?? null,
                'is_test_data' => (bool) ($row['data']['is_test_data'] ?? false),
            ], $import->rows),
        ];
    }

    /**
     * @param  list<array{row: int, cells: list<string>}>  $raw
     */
    private function evaluate(CandidateImport $import, array $raw): void
    {
        $organization = $this->organization();
        $mapping = $import->mapping;
        $seenEmails = [];
        $seenPhones = [];
        $rows = [];
        foreach ($raw as $item) {
            $values = [];
            foreach ($mapping as $column => $field) {
                $values[$field] = trim((string) ($item['cells'][(int) $column] ?? ''));
            }
            [$data, $errors] = $this->validateRow($values, $organization);
            $status = $errors === [] ? 'valid' : 'invalid';
            if ($status === 'valid') {
                $inFile = ($data['email'] !== null ? ($seenEmails[$data['email']] ?? null) : null)
                    ?? ($data['phone'] !== null ? ($seenPhones[$data['phone']] ?? null) : null);
                if ($inFile !== null) {
                    $status = 'duplicate';
                    $errors[] = 'Same email or mobile number as Excel row '.$inFile.' in this file.';
                } elseif ($this->existing($data['email'], $data['phone']) !== null) {
                    $status = 'duplicate';
                    $errors[] = 'A candidate with this email or mobile number already exists in this business.';
                }
            }
            if ($status === 'valid') {
                if ($data['email'] !== null) {
                    $seenEmails[$data['email']] = $item['row'];
                }
                if ($data['phone'] !== null) {
                    $seenPhones[$data['phone']] = $item['row'];
                }
            }
            $rows[] = ['row' => $item['row'], 'cells' => $item['cells'], 'status' => $status, 'errors' => $errors, 'data' => $data];
        }

        $counts = array_count_values(array_column($rows, 'status'));
        $import->rows = $rows;
        $import->total_rows = count($rows);
        $import->valid_rows = $counts['valid'] ?? 0;
        $import->invalid_rows = $counts['invalid'] ?? 0;
        $import->duplicate_rows = $counts['duplicate'] ?? 0;
    }

    /**
     * @param  array<string, string>  $values
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function validateRow(array $values, Organization $organization): array
    {
        $errors = [];
        $given = $values['given_name'] ?? '';
        $family = $values['family_name'] ?? '';
        if ($given === '' && ($values['full_name'] ?? '') !== '') {
            $parts = preg_split('/\s+/', $values['full_name'], 2) ?: [];
            $given = $parts[0] ?? '';
            $family = $family !== '' ? $family : ($parts[1] ?? '');
        }
        if ($given === '') {
            $errors[] = 'Full name is required.';
        }

        $email = strtolower($values['email'] ?? '');
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Email "'.mb_substr($email, 0, 60).'" is not a valid email address.';
        }
        $phone = $this->phone($values['phone'] ?? '');
        if (($values['phone'] ?? '') !== '' && $phone === null) {
            $errors[] = 'Mobile number must have 10 to 15 digits.';
        }
        if ($email === '' && ($values['phone'] ?? '') === '') {
            $errors[] = 'Add an email or a mobile number.';
        }

        $experience = null;
        if (($values['experience_years'] ?? '') !== '') {
            $number = str_replace([' years', ' year', 'yrs', 'yr'], '', strtolower($values['experience_years']));
            if (! is_numeric(trim($number)) || (float) $number < 0 || (float) $number > 60) {
                $errors[] = 'Experience must be a number of years between 0 and 60.';
            } else {
                $experience = (int) round((float) $number);
            }
        }

        $business = $values['business'] ?? '';
        if ($business !== '' && ! in_array($this->key($business), [$this->key($organization->name), $this->key((string) $organization->slug)], true)) {
            $errors[] = 'Business "'.mb_substr($business, 0, 60).'" is not the business you are importing into ('.$organization->name.'). Switch business or fix the value.';
        }

        $data = [
            'given_name' => $given,
            'family_name' => $family,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone,
            'location' => $values['location'] ?? null,
            'experience_years' => $experience,
            'current_company' => $values['current_company'] ?? null,
            'current_designation' => $values['current_designation'] ?? null,
            'notice_period' => $values['notice_period'] ?? null,
            'qualification' => $values['qualification'] ?? null,
            'skills' => $values['skills'] ?? null,
            'notes' => $values['notes'] ?? null,
            'is_test_data' => $this->isTestData($values),
        ];
        foreach (self::LIMITS as $field => $limit) {
            if ($data[$field] === '') {
                $data[$field] = $field === 'family_name' ? '' : null;
            }
            if (is_string($data[$field]) && mb_strlen($data[$field]) > $limit) {
                $errors[] = self::FIELDS[$field][0].' is longer than '.$limit.' characters.';
            }
        }

        return [$data, $errors];
    }

    /**
     * @param  array<string, string>  $values
     */
    private function isTestData(array $values): bool
    {
        $flag = strtolower($values['is_test_data'] ?? '');
        $type = strtolower($values['candidate_type'] ?? '');

        return in_array($flag, ['yes', 'y', 'true', '1'], true) || in_array($type, ['dummy', 'test', 'test data'], true);
    }

    private function phone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }

    private function existing(?string $email, ?string $phone): ?Candidate
    {
        if ($email === null && $phone === null) {
            return null;
        }

        return Candidate::query()->where(function ($query) use ($email, $phone): void {
            if ($email !== null) {
                $query->orWhereRaw('lower(email) = ?', [$email]);
            }
            if ($phone !== null) {
                $query->orWhere('phone', $phone);
            }
        })->first();
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    private function guessMapping(array $headers): array
    {
        $aliases = [];
        foreach (self::FIELDS as $field => [, , $names]) {
            $aliases[$this->key($field)] = $field;
            foreach ($names as $name) {
                $aliases[$this->key($name)] ??= $field;
            }
        }
        $mapping = [];
        foreach ($headers as $index => $header) {
            $field = $aliases[$this->key($header)] ?? null;
            if ($field !== null && ! in_array($field, $mapping, true)) {
                $mapping[(string) $index] = $field;
            }
        }

        return $mapping;
    }

    private function key(string $value): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';
    }

    private function assertOpen(CandidateImport $import): void
    {
        if ($import->status !== 'previewed') {
            throw ValidationException::withMessages(['import' => ['This import has already been '.($import->status === 'cancelled' ? 'cancelled' : 'processed').'. Upload the file again to start a new import.']]);
        }
    }

    private function organization(): Organization
    {
        return $this->context->get() ?? throw new RuntimeException('An active business is required.');
    }

    /**
     * @return array<string, mixed>
     */
    private function auditCounts(CandidateImport $import): array
    {
        return [
            'import_id' => $import->id,
            'file_name' => $import->file_name,
            'total' => $import->total_rows,
            'valid' => $import->valid_rows,
            'invalid' => $import->invalid_rows,
            'duplicate' => $import->duplicate_rows,
            'imported' => $import->imported_rows,
            'failed' => $import->failed_rows,
        ];
    }
}
