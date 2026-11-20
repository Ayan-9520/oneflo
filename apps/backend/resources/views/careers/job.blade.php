<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $url }}">
    @if ($image)
    <meta property="og:image" content="{{ $image }}">
    <meta name="twitter:image" content="{{ $image }}">
    @endif
    <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    <link rel="canonical" href="{{ $url }}">
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>{{ $description }}</p>
    <p><a href="{{ $url }}">View this opening</a></p>
</body>
</html>
