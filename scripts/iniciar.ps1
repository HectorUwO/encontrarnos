# Inicia todo Encontrarnos: Docker Desktop (si hace falta), la app, la cola, MySQL,
# Meilisearch y CompreFace, y abre el sitio cuando responde.
$ErrorActionPreference = 'Stop'
$raiz = Split-Path -Parent $PSScriptRoot
Set-Location $raiz
$url = 'http://127.0.0.1:8080'

function Docker-Listo { docker info *> $null; return $LASTEXITCODE -eq 0 }

try {
    if (-not (Docker-Listo)) {
        Write-Host 'Iniciando Docker Desktop...'
        Start-Process "$env:ProgramFiles\Docker\Docker\Docker Desktop.exe"
        $limite = (Get-Date).AddMinutes(4)
        while (-not (Docker-Listo)) {
            if ((Get-Date) -gt $limite) { throw 'Docker Desktop no arrancó en 4 minutos.' }
            Start-Sleep -Seconds 5
        }
    }
    if (-not (Test-Path '.env.docker')) { throw 'Falta .env.docker (copia .env.docker.example y complétalo).' }

    Write-Host 'Levantando los servicios...'
    docker compose --env-file .env.docker up -d
    if ($LASTEXITCODE -ne 0) { throw 'docker compose falló.' }

    Write-Host 'Esperando a la aplicación...'
    $limite = (Get-Date).AddMinutes(3)
    while ($true) {
        try { if ((Invoke-WebRequest "$url/up" -UseBasicParsing -TimeoutSec 3).StatusCode -eq 200) { break } } catch { }
        if ((Get-Date) -gt $limite) { throw 'La aplicación no respondió en 3 minutos (revisa: docker compose --env-file .env.docker logs app).' }
        Start-Sleep -Seconds 3
    }
    Write-Host "Listo: $url"
    Start-Process $url
    Start-Sleep -Seconds 3
} catch {
    Write-Host "ERROR: $($_.Exception.Message)" -ForegroundColor Red
    Read-Host 'Pulsa Enter para cerrar'
}
