@echo off
echo ========================================
echo Hemingway Gastro - System Upgrade
echo ========================================
echo.

echo [1/3] Installing Node dependencies...
call npm install
if %errorlevel% neq 0 (
    echo Error installing dependencies!
    pause
    exit /b %errorlevel%
)

echo.
echo [2/3] Building assets...
call npm run build
if %errorlevel% neq 0 (
    echo Error building assets!
    pause
    exit /b %errorlevel%
)

echo.
echo [3/3] Clearing Laravel cache...
call php artisan config:clear
call php artisan cache:clear
call php artisan view:clear

echo.
echo ========================================
echo Upgrade completed successfully!
echo ========================================
echo.
echo To start the development server, run:
echo   composer run dev
echo.
pause