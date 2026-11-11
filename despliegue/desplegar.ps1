# Sube el Armario digital a un servidor Linux (Raspberry Pi, Debian, Ubuntu…) por SSH y lo instala.
# Uso: doble clic en Desplegar.bat
#   o: powershell -File despliegue\desplegar.ps1 -Servidor usuario@IP -Destino /var/www/html/armario
# La primera vez pregunta el servidor y lo guarda en despliegue\servidor.txt (no se sube a git).
param(
    [string]$Servidor = "",
    [string]$Destino = "/var/www/html/armario"
)

$origen = Split-Path -Parent $PSScriptRoot
$archivoServidor = Join-Path $PSScriptRoot "servidor.txt"

if (-not $Servidor) {
    if (Test-Path $archivoServidor) {
        $Servidor = (Get-Content $archivoServidor -Raw).Trim()
    } else {
        $Servidor = Read-Host "Servidor SSH (por ejemplo pi@192.168.1.50)"
        if (-not $Servidor) { Write-Host "No se ha indicado servidor." -ForegroundColor Red; exit 1 }
        Set-Content -Path $archivoServidor -Value $Servidor -Encoding ASCII
        Write-Host "Guardado en despliegue\servidor.txt para la próxima vez." -ForegroundColor DarkGray
    }
}

Write-Host "==> Preparando $Servidor" -ForegroundColor Magenta
ssh $Servidor "rm -rf /tmp/armario-deploy"
if ($LASTEXITCODE -ne 0) { Write-Host "No se pudo conectar por SSH con $Servidor" -ForegroundColor Red; exit 1 }

Write-Host "==> Copiando archivos" -ForegroundColor Magenta
scp -r -q "$origen" "${Servidor}:/tmp/armario-deploy"
if ($LASTEXITCODE -ne 0) { Write-Host "Falló la copia de archivos" -ForegroundColor Red; exit 1 }

Write-Host "==> Instalando" -ForegroundColor Magenta
ssh -t $Servidor "bash /tmp/armario-deploy/despliegue/remoto.sh '$Destino'"
if ($LASTEXITCODE -ne 0) { Write-Host "La instalación dio un error (mira los mensajes de arriba)" -ForegroundColor Red; exit 1 }

Start-Process "http://$($Servidor.Split('@')[-1])/$(Split-Path -Leaf $Destino)/"
