# Descarga SweetAlert2 en assets/vendor/sweetalert2 con una versión fija y comprueba su huella SHA-256.
# La app ya no lo carga desde un CDN: así no depende de terceros ni de lo que publiquen en el futuro.
# Uso: powershell -File despliegue\actualizar-sweetalert2.ps1
# Para cambiar de versión: actualiza $Version y las huellas (https://data.jsdelivr.com/v1/packages/npm/sweetalert2@VERSION?structure=flat)
# y SWAL_VERSION en includes/funciones.php.
$ErrorActionPreference = 'Stop'

$Version = '11.26.25'
$Archivos = @(
    @{ Ruta = 'dist/sweetalert2.all.min.js'; Nombre = 'sweetalert2.all.min.js'; Sha256 = 'Tobw4i5HcbW4qsJMYTxmGAameLGvooTQPPatA9PiGgo=' },
    @{ Ruta = 'LICENSE';                     Nombre = 'LICENSE';                Sha256 = 'h5qjP+ujWzQoxkhfwXzd2Mgg16h4AoGqlF5gWCooxLQ=' }
)

$destino = Join-Path (Split-Path -Parent $PSScriptRoot) 'assets\vendor\sweetalert2'
New-Item -ItemType Directory -Force -Path $destino | Out-Null
[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12

foreach ($a in $Archivos) {
    $url = "https://cdn.jsdelivr.net/npm/sweetalert2@$Version/$($a.Ruta)"
    $tmp = Join-Path $env:TEMP ("swal-" + [guid]::NewGuid() + ".tmp")
    Write-Host "==> Descargando $url" -ForegroundColor Magenta
    Invoke-WebRequest -Uri $url -OutFile $tmp -UseBasicParsing
    $sha = [Convert]::ToBase64String([Security.Cryptography.SHA256]::Create().ComputeHash([IO.File]::ReadAllBytes($tmp)))
    if ($sha -ne $a.Sha256) {
        Remove-Item $tmp -Force
        throw "La huella de $($a.Nombre) no coincide (esperada $($a.Sha256), obtenida $sha). No se instala."
    }
    Move-Item -Force $tmp (Join-Path $destino $a.Nombre)
}
Set-Content -Path (Join-Path $destino 'VERSION') -Value $Version -Encoding ASCII
Write-Host "SweetAlert2 $Version listo en assets\vendor\sweetalert2" -ForegroundColor Green
