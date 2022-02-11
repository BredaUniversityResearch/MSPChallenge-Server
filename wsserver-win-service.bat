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
if "%~1"=="logging_off" goto logging_off
if "%~1"=="logging_on" goto logging_on
if "%~1"=="" goto get
goto default

:install
%exe% status %service% 1>NUL 2>NUL
IF %ERRORLEVEL% NEQ 0 (
    %exe% install %service% C:\xampp\php\php.exe bin/console app:ws-server --env=prod %2 %3 %4 %5 %6 %7 %8 %9
)
%exe% set %service% AppDirectory %~dp0
%exe% restart %service%
%exe% status %service%
goto get

:blank
echo Argument 1 must be either: remove/install/stop/start/restart/status/get/logging_on/logging_off
echo Followed by any additional parameter supported by the command
goto eof

:default
%exe% %1 %service% %2 %3 %4 %5 %6 %7 %8 %9
goto eof

:remove
%exe% stop %service%
%exe% remove %service% confirm
goto eof

:logging_on
%exe% set %service% AppStdout %~dp0var\log\%service%.log
%exe% set %service% AppStderr %~dp0var\log\%service%.log
%exe% set %service% AppRotateFiles 1
%exe% restart %service%
%exe% status %service%
goto get

:logging_off
%exe% set %service% AppStdout ""
%exe% set %service% AppStderr ""
%exe% restart %service%
%exe% status %service%
goto get

:get
echo AppDirectory:
%exe% get %service% AppDirectory
echo Application:
%exe% get %service% Application
echo AppParameters:
%exe% get %service% AppParameters
echo Log path is:
%exe% get %service% AppStdout
goto eof

:eof
endlocal
IF %ERRORLEVEL% NEQ 0 (
    exit /b %ERRORLEVEL%
)
exit /b 0
