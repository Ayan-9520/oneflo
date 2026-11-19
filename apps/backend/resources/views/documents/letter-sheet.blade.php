<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { margin: 0; color: #1c1433; font-family: DejaVu Serif, serif; font-size: 12px; }
        .sheet { padding: 28px 36px 32px; }
        h1 { margin: 0; text-align: center; font-family: DejaVu Sans, sans-serif; font-size: 22px; color: #000000; }
        .logo { height: 128px; width: auto; }
        .mark { display: inline-block; border: 1px solid #1c1433; color: #1c1433; font-size: 18px; font-weight: 700; letter-spacing: 2px; padding: 10px 14px; }
        table.meta { width: 100%; border-collapse: collapse; margin: 18px 0; }
        table.meta td { vertical-align: top; width: 50%; font-size: 12px; line-height: 1.7; padding: 0; }
        h2 { margin: 16px 0 6px; font-size: 14px; color: #1c1433; }
        p { margin: 0 0 8px; line-height: 1.7; }
        .subject { font-weight: 700; margin: 16px 0 8px; }
        .dear { margin-top: 10px; }
        .sign { width: 180px; border-top: 2px solid {{ $primary }}; margin-top: 16px; padding-top: 8px; }
        .footer { margin-top: 22px; color: #6b728a; font-size: 10px; }
    </style>
</head>
<body>
<div class="sheet" data-letter-sheet="true">
    <table style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="width: 34%; vertical-align: top;">
                @if (!empty($logo))
                    <img class="logo" src="{{ $logo }}" alt="">
                @else
                    <div class="mark">{{ $mark }}</div>
                @endif
            </td>
            <td style="vertical-align: top; text-align: center;"><h1>{{ $title }}</h1></td>
            <td style="width: 34%;"></td>
        </tr>
    </table>
    <table class="meta">
        <tr>
            <td><strong>{{ $company }}</strong><br><strong>Address:</strong> {{ $address }}<br><strong>Contact:</strong> {{ $phone }}</td>
            <td><strong>Website:</strong> {{ $website }}<br><strong>Email:</strong> {{ $email }}<br><strong>Date:</strong> {{ $date }}</td>
        </tr>
    </table>
    @if ($person !== '')<p><strong>{{ $person }}</strong>@if ($personAddress !== '')<br>{{ $personAddress }}@endif @if (!empty($role))<br>Designation: {{ $role }}@endif @if (($department ?? '') !== '')<br>Department: {{ $department }}@endif @if (($personEmail ?? '') !== '' || ($personPhone ?? '') !== '')<br>{{ trim(($personEmail ?? '').' '.($personPhone ?? '')) }}@endif</p>@endif
    <p class="subject">Re: {{ $subjectLine ?? $subject }}</p>
    <p class="dear">Dear {{ $person !== '' ? $person : 'Sir or Madam' }},</p>
    @foreach ($paragraphs as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach
    @if (!empty($compensation))
        <h2>{{ $compensationTitle ?? 'Compensation' }}</h2>
        <p>{{ $compensation }}</p>
    @endif
    @if (!empty($joining))
        <h2>{{ $datesTitle ?? 'Dates' }}</h2>
        <p>{{ $joining }}</p>
    @endif
    @if (!empty($requirements))
        <h2>Pre-employment requirements</h2>
        <p>{{ $requirements }}</p>
    @endif
    @if (!empty($documents))
        <h2>Documents required</h2>
        <p>{{ $documents }}</p>
    @endif
    <p style="margin-top: 18px;">Yours truly,</p>
    @if ($signatory !== '' || $signatoryTitle !== '' || $signatoryContact !== '')
        <p>@if ($signatory !== '')<strong>{{ $signatory }}</strong><br>@endif @if ($signatoryTitle !== ''){{ $signatoryTitle }}<br>@endif @if ($signatoryContact !== ''){{ $signatoryContact }}@endif</p>
    @endif
</div>
</body>
</html>
