@echo off
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0despliegue\desplegar.ps1" %*
pause
