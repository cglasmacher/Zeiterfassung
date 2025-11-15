@echo off
echo Installing Barauszahlung Feature...
echo.

echo Step 1: Installing DomPDF package...
composer require barryvdh/laravel-dompdf
if %errorlevel% neq 0 (
    echo Error installing DomPDF package
    pause
    exit /b 1
)

echo.
echo Step 2: Running database migrations...
php artisan migrate
if %errorlevel% neq 0 (
    echo Error running migrations
    pause
    exit /b 1
)

echo.
echo Installation complete!
echo.
echo You can now use the Barauszahlung feature in:
echo - Settings ^> Mitarbeiter (to mark employees for cash payment)
echo - Tagesübersicht (to manage daily cash payments and generate reports)
echo.
pause