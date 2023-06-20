<?php
// phpcs:ignoreFile PSR1.Files.SideEffects.FoundWithSymbols

use App\Domain\WsServer\WsServerConnection;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;

require dirname(__DIR__) . '/vendor/autoload.php';

// Or using the WsServer component to be able to connect with a web browser by using the following html/js:
/**
<html>
<body>
<script>
    var conn = new WebSocket('ws://localhost:45001');
    conn.onopen = function(e) {
        console.log("Connection established!");
    };

    conn.onmessage = function(e) {
        document.getElementById('received').value += "<---" + e.data + "\n";
    };

    addEventListener("keypress", (e) => {
        if (e.code !== 'Enter') return;
        document.getElementById("send").click();
    });

    function send(msg) {
        conn.send(msg);
        document.getElementById('received').value += "--->" + msg + "\n";
    }
</script>
<input id="msg" type="text" style="width: 300px">
<input id="send" type="button" value="send" onclick="send(document.getElementById('msg').value)">
<br />
<textarea id="received" style="height:90%; width:90%"></textarea>
</body>
</html>
 */
$server = IoServer::factory(
    new \Ratchet\Http\HttpServer(
        new \Ratchet\WebSocket\WsServer(
            createChat()
        )
    ),
    (int)($argv[1] ?? 45001),
    ($argv[2] ?? '0.0.0.0'),
);

$server->run();

function createChat(): MessageComponentInterface
{
    return new class implements MessageComponentInterface
    {
        protected SplObjectStorage $clients;

        public function __construct()
        {
            $this->clients = new SplObjectStorage;
        }

        public function onOpen(ConnectionInterface $conn)
        {
            $conn = new WsServerConnection($conn);

            // Store the new connection to send messages to later
            $this->clients->attach($conn);

            echo "New connection! ({$conn->resourceId})\n";
        }

        public function onMessage(ConnectionInterface $from, $msg)
        {
            $from = new WsServerConnection($from);

            $numRecv = count($this->clients) - 1;
            echo sprintf(
                'Connection %d sending message "%s" to %d other connection%s' . "\n",
                $from->resourceId,
                $msg,
                $numRecv,
                $numRecv == 1 ? '' : 's'
            );

            foreach ($this->clients as $client) {
                if ($from !== $client) {
                    // The sender is not the receiver, send to each client connected
                    $client->send($msg);
                }
            }
        }

        public function onClose(ConnectionInterface $conn)
        {
            $conn = new WsServerConnection($conn);

            // The connection is closed, remove it, as we can no longer send it messages
            $this->clients->detach($conn);

            echo "Connection {$conn->resourceId} has disconnected\n";
        }

        public function onError(ConnectionInterface $conn, \Exception $e)
        {
            echo "An error has occurred: {$e->getMessage()}\n";

            $conn->close();
        }
    };
}
