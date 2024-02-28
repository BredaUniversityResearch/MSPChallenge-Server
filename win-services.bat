@echo OFF
setlocal
set cwd=%cd%
set scriptpath=%~dp0
cd "%scriptpath%"

if "%PHP_PATH%"=="" (
  set PHP_PATH=C:\xampp\php\php.exe
)
set php="%PHP_PATH%"
set MSPWsServerService=MSPWsServer
set MSPMessengerConsumerService=MSPMessengerConsumer
reg Query "HKLM\Hardware\Description\System\CentralProcessor\0" | find /i "x86" > NUL && set OS=32BIT || set OS=64BIT
set exe=tools\Win\nssm\nssm-win64.exe
if %OS%==32BIT set exe=tools\Win\nssm\nssm-win32.exe

if "%~1"=="" goto blank
if "%~1"=="install" goto install
if "%~1"=="remove" goto remove
if "%~1"=="get" goto get
if "%~1"=="logging_off" goto logging_off
if "%~1"=="logging_on" goto logging_on
if "%~1"=="" goto get
if "%~1"=="firewall_add" goto firewall_add
if "%~1"=="firewall_remove" goto firewall_remove
goto default

:install
call :InstallSymfonyCommandAsService %MSPWsServerService% app:ws-server -v %2 %3 %4 %5 %6 %7 %8 %9
call :InstallSymfonyCommandAsService %MSPMessengerConsumerService% messenger:consume analytics async -vv %2 %3 %4 %5 %6 %7 %8 %9
goto get

:blank
echo Argument 1 must be either: remove/install/stop/start/restart/status/get/logging_on/logging_off/firewall_add/firewall_remove
echo Followed by any additional parameter supported by the command
echo You can also open up the Non-Sucking Service Manager in edit mode for a service using: edit <service_name>
goto eof

:default
call :RunServiceManager %1 %2 %3 %4 %5 %6 %7 %8 %9
goto eof

:remove
call :RemoveService %MSPWsServerService%
call :RemoveService %MSPMessengerConsumerService%
goto eof

:logging_on
call :SetServiceLoggingOn %MSPWsServerService%
call :SetServiceLoggingOn %MSPMessengerConsumerService%
goto get

:logging_off
call :SetServiceLoggingOff %MSPWsServerService%
call :SetServiceLoggingOff %MSPMessengerConsumerService%
goto get

:firewall_add
call :FirewallAddRule
goto eof

:firewall_remove
call :FirewallRemoveRule
goto eof

:get
call :GetServiceParameters %MSPWsServerService% %1 %2
call :GetServiceParameters %MSPMessengerConsumerService% %1 %2
goto eof

:eof
cd "%cwd%"
endlocal
IF %ERRORLEVEL% NEQ 0 (
    exit /b %ERRORLEVEL%
)
exit /b 0

rem ======= all functions below =======
:FirewallAddRule
netsh advfirewall firewall add rule name="MSP Websocket server" dir=in action=allow protocol=TCP localport=45001
exit /b 0

:FirewallRemoveRule
netsh advfirewall firewall delete rule name="MSP Websocket server"
exit /b 0

:InstallSymfonyCommandAsService
set service=%1
set symfonyCommand=%2
if not exist %php% (
    echo Could not find php.exe file location at %php%
    set ERRORLEVEL=1
    goto eof
)
call :FirewallAddRule
%exe% status %service% 1>NUL 2>NUL
IF %ERRORLEVEL% NEQ 0 (
    %exe% install %service% %php% -d memory_limit=-1 bin/console %symfonyCommand% %3 %4 %5 %6 %7 %8 %9
)
%exe% set %service% AppDirectory %~dp0
%exe% stop %service%
%php% bin/console cache:clear
%exe% start %service%
%exe% status %service%
exit /b 0

:RemoveService
set service=%1
%exe% stop %service%
%exe% remove %service% confirm
echo %exe% remove %service% confirm
call :FirewallRemoveRule
exit /b 0

:SetServiceLoggingOn
set service=%1
%exe% set %service% AppStdout %~dp0var\log\%service%.log
%exe% set %service% AppStderr %~dp0var\log\%service%.log
%exe% set %service% AppRotateFiles 1
%exe% restart %service%
%exe% status %service%
exit /b 0

:SetServiceLoggingOff
set service=%1
%exe% set %service% AppStdout ""
%exe% set %service% AppStderr ""
%exe% restart %service%
%exe% status %service%
exit /b 0

:GetServiceParameters
set service=%1
set singleparam=0
if not "%~3"=="" (
  if not "%~2"=="install" (
    set singleparam=1
  )
)
if "%singleparam%"=="1" (
  echo %3:
  %exe% get %service% %3
) else (
  echo AppDirectory:
  %exe% get %service% AppDirectory
  echo Application:
  %exe% get %service% Application
  echo AppParameters:
  %exe% get %service% AppParameters
  echo Log path is:
  %exe% get %service% AppStdout
)
exit /b 0

:RunServiceManager
%exe% %1 %2 %3 %4 %5 %6 %7 %8 %9
exit /b 0
