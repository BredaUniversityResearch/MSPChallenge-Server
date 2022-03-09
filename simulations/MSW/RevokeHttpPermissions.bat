netsh http delete urlacl url=http://+:45000/Watchdog/
netsh advfirewall firewall delete rule name="MSP Websocket server"
pause
