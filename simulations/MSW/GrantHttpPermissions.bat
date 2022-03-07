netsh http add urlacl url=http://+:45000/Watchdog/ user="%USERDOMAIN%\%USERNAME%"
netsh advfirewall firewall add rule name="MSP Websocket server" dir=in action=allow protocol=TCP localport=45001
pause