<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { margin: 0; color: #172B35; font-family: "IBM Plex Sans", sans-serif; background: #fff; }
        .page { max-width: 760px; margin: 0 auto; padding: 32px; }
        header { text-align: {{ $alignment }}; border-bottom: {{ $official ? '1px solid #172B35' : '3px solid '.$primaryColor }}; padding-bottom: 16px; }
        header img.logo { max-height: 64px; max-width: 220px; }
        h1 { font-size: 18px; margin: 12px 0 0; }
        .muted { color: #718096; font-size: 13px; white-space: pre-wrap; }
        main { padding: 24px 0; font-size: 14px; line-height: 1.6; white-space: pre-wrap; }
        .ids { font-size: 12px; color: #718096; }
        footer { border-top: 1px solid #d7dee4; padding-top: 12px; font-size: 12px; color: #718096; }
        .signatory { margin-top: 28px; }
        .signatory img { max-height: 48px; }
        .oneflo { margin-top: 16px; display: flex; gap: 8px; align-items: center; }
        .oneflo img { max-height: 18px; }
    </style>
</head>
<body>
<div class="page">
    <header>
        @if($logo)
            <img class="logo" src="{{ $logo }}" alt="{{ $brandName }}">
        @endif
        <h1>{{ $legalName }}</h1>
        @if($brandName !== '')
            <div>{{ $brandName }}</div>
        @endif
        @if($headerText)
            <div>{{ $headerText }}</div>
        @endif
        @if($address)
            <div class="muted">{{ $address }}</div>
        @endif
        @if($email || $phone || $website)
            <div class="muted">{{ trim($email.'  '.$phone.'  '.$website) }}</div>
        @endif
        <div class="ids">
            @if($cin) CIN {{ $cin }} @endif
            @if($pan) PAN {{ $pan }} @endif
            @if($tan) TAN {{ $tan }} @endif
            @if($gstin) GSTIN {{ $gstin }} @endif
        </div>
    </header>
    <main>{{ $body }}</main>
    @if($signatoryName)
        <div class="signatory">
            @if($signature)
                <img src="{{ $signature }}" alt="Signature of {{ $signatoryName }}">
            @endif
            @if($stamp)
                <img src="{{ $stamp }}" alt="Company stamp">
            @endif
            <div>{{ $signatoryName }}</div>
            <div>{{ $signatoryDesignation }}</div>
        </div>
    @endif
    <footer>
        @if($footerText)<div>{{ $footerText }}</div>@endif
        @if($footerNote)<div>{{ $footerNote }}</div>@endif
        @if($showOneFlo)
            <div class="oneflo">
                @if($platformLogo)<img src="{{ $platformLogo }}" alt="OneFlo">@endif
                <span>{{ $poweredBy }}@if($platformWebsite) · {{ $platformWebsite }}@endif</span>
            </div>
        @endif
    </footer>
</div>
</body>
</html>
