docker compose exec app php artisan test
if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE }

Push-Location apps\frontend
npx tsc -b --pretty false
if ($LASTEXITCODE -ne 0) { Pop-Location; exit $LASTEXITCODE }
npx oxlint src
$code = $LASTEXITCODE
Pop-Location
exit $code
