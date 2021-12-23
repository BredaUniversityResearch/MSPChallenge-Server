@echo OFF
setlocal

set service=MSPWsServer
reg Query "HKLM\Hardware\Description\System\CentralProcessor\0" | find /i "x86" > NUL && set OS=32BIT || set OS=64BIT
set exe=tools\Win\nssm-win64.exe
if %OS%==32BIT set exe=tools\Win\nssm-win32.exe

if "%~1"=="" goto blank
if "%~1"=="install" goto install
if "%~1"=="remove" goto remove
if "%~1"=="get" goto get
goto default

:install
%exe% status %service% 1>NUL 2>NUL
IF %ERRORLEVEL% NEQ 0 (
   %exe% install %service% C:\xampp\php\php.exe bin/console app:ws-server --env=prod %2 %3 %4 %5 %6 %7 %8 %9
)
%exe% set %service% AppDirectory %~dp0
%exe% get %service% AppDirectory
%exe% get %service% Application
%exe% get %service% AppParameters
%exe% restart %service%
%exe% status %service%
goto eof

:blank
echo Argument 1 must be either: remove/install/stop/start/restart/status/get
echo Followed by any additional parameter supported by the command
goto eof

:default
%exe% %1 %service% %2 %3 %4 %5 %6 %7 %8 %9
goto eof

:remove
%exe% stop %service%
%exe% remove %service% confirm
goto eof

:get
%exe% get %service% AppDirectory
%exe% get %service% Application
%exe% get %service% AppParameters
goto eof

:eof
endlocal
IF %ERRORLEVEL% NEQ 0 (
   exit /b %ERRORLEVEL%
)
exit /b 0
