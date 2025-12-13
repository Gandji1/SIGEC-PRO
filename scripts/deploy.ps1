@echo off
REM SIGEC Deployment Script for Windows

setlocal enabledelayedexpansion

echo.
echo ==========================================
echo SIGEC Deployment Script (Windows)
echo ==========================================
echo.

REM Check if Docker is installed
docker --version >nul 2>&1
if errorlevel 1 (
    echo Error: Docker is not installed. Please install Docker Desktop for Windows.
    pause
    exit /b 1
)

REM Change to project root
cd /d "%~dp0\.."

echo Building Docker images...
docker-compose -f infra/docker-compose.yml build
if errorlevel 1 (
    echo Error: Docker build failed
    pause
    exit /b 1
)

echo.
echo Starting services...
docker-compose -f infra/docker-compose.yml up -d
if errorlevel 1 (
    echo Error: Docker services failed to start
    pause
    exit /b 1
)

echo.
echo Waiting for services to be ready...
timeout /t 5 /nobreak

echo.
echo Generating Laravel application key...
docker exec sigec-app php artisan key:generate

echo.
echo Running database migrations...
docker exec sigec-app php artisan migrate --seed

echo.
echo ✅ Deployment complete!
echo.
echo Services available at:
echo    - Backend API: http://localhost:8000
echo    - Frontend: http://localhost:5173
echo    - pgAdmin: http://localhost:5050 (admin@sigec.local / admin)
echo.
echo To stop services, run: docker-compose -f infra/docker-compose.yml down
echo.
pause
