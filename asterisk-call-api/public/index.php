<?php

// A very simple and insecure way to handle sessions/tokens.
// For a real-world application, consider using JWT or a more robust session management system.
session_start();

require __DIR__ . '/../vendor/autoload.php';

use Bramus\Router\Router;
use App\AmiClient;
use PAMI\Message\Action\CoreShowChannelsAction;
use PAMI\Message\Action\OriginateAction;
use PAMI\Message\Action\HangupAction;
use PAMI\Message\Action\RedirectAction;

// Simple Bearer Token Authentication
// In a real application, this should be a securely generated and stored token.
define('API_TOKEN', 'SECRET_API_TOKEN_CHANGE_ME');

header('Content-Type: application/json');

function json_response($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

$router = new Router();

// Before middleware to check for Authorization header
$router->before('GET|POST', '/calls/.*', function() {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        json_response(['error' => 'Authorization header missing or invalid'], 401);
    }

    $token = substr($authHeader, 7);
    if ($token !== API_TOKEN) {
        json_response(['error' => 'Invalid token'], 401);
    }

    // Also check if we are "logged in" to AMI
    if (!isset($_SESSION['ami_logged_in']) || !$_SESSION['ami_logged_in']) {
         json_response(['error' => 'Not logged into AMI. Please use /login first.'], 403);
    }
});


$router->post('/login', function() {
    $credentials = json_decode(file_get_contents('php://input'), true);

    // In a real app, you would validate the user credentials against a database.
    // For this example, we assume any user can attempt to log into AMI.
    // The actual AMI login is what matters.

    if (AmiClient::login()) {
        json_response(['message' => 'Successfully logged into Asterisk AMI']);
    } else {
        json_response(['error' => 'Failed to log into Asterisk AMI. Check credentials in config/ami.php'], 500);
    }
});

$router->post('/logout', function() {
    AmiClient::logout();
    session_destroy();
    json_response(['message' => 'Logged out successfully']);
});

$router->get('/calls/live', function() {
    try {
        $client = AmiClient::getInstance();
        $response = $client->send(new CoreShowChannelsAction());
        
        $events = $response->getEvents();
        $channels = [];
        foreach ($events as $event) {
            if ($event instanceof \PAMI\Message\Event\CoreShowChannelEvent) {
                $channels[] = $event->getKeys();
            }
        }
        json_response($channels);
    } catch (Exception $e) {
        json_response(['error' => 'Error fetching live calls: ' . $e->getMessage()], 500);
    }
});

$router->post('/calls/originate', function() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['channel']) || !isset($data['context']) || !isset($data['exten']) || !isset($data['priority'])) {
        json_response(['error' => 'Missing required fields: channel, context, exten, priority'], 400);
    }

    try {
        $client = AmiClient::getInstance();
        $action = new OriginateAction($data['channel']);
        $action->setContext($data['context']);
        $action->setExtension($data['exten']);
        $action->setPriority($data['priority']);
        $action->setCallerId($data['callerId'] ?? 'API');
        
        $response = $client->send($action);

        if ($response->isSuccess()) {
            json_response(['message' => 'Call originated successfully', 'response' => $response->getKeys()]);
        } else {
            json_response(['error' => 'Failed to originate call', 'response' => $response->getKeys()], 500);
        }
    } catch (Exception $e) {
        json_response(['error' => 'Error originating call: ' . $e->getMessage()], 500);
    }
});

$router->post('/calls/hangup', function() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['channel'])) {
        json_response(['error' => 'Missing required field: channel'], 400);
    }

    try {
        $client = AmiClient::getInstance();
        $action = new HangupAction($data['channel']);
        $response = $client->send($action);

        if ($response->isSuccess()) {
            json_response(['message' => 'Hangup initiated successfully', 'response' => $response->getKeys()]);
        } else {
            json_response(['error' => 'Failed to hangup call', 'response' => $response->getKeys()], 500);
        }
    } catch (Exception $e) {
        json_response(['error' => 'Error during hangup: ' . $e->getMessage()], 500);
    }
});

$router->post('/calls/answer', function() {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['channel']) || !isset($data['context']) || !isset($data['exten']) || !isset($data['priority'])) {
        json_response(['error' => 'Missing required fields: channel, context, exten, priority'], 400);
    }

    try {
        $client = AmiClient::getInstance();
        // Redirect the channel to a context that answers the call
        $action = new RedirectAction($data['channel'], $data['context'], $data['exten'], $data['priority']);
        $response = $client->send($action);

        if ($response->isSuccess()) {
            json_response(['message' => 'Answer (Redirect) initiated successfully', 'response' => $response->getKeys()]);
        } else {
            json_response(['error' => 'Failed to answer (redirect) call', 'response' => $response->getKeys()], 500);
        }
    } catch (Exception $e) {
        json_response(['error' => 'Error during answer (redirect): ' . $e->getMessage()], 500);
    }
});

$router->get('/calls/history', function() {
    // This is a simplified implementation. A real-world scenario would involve
    // a database (e.g., using Asterisk's CDR mysql backend) or a more robust log parser.
    $cdrPath = '/var/log/asterisk/cdr-csv/Master.csv'; 

    if (!file_exists($cdrPath) || !is_readable($cdrPath)) {
        json_response(['error' => "CDR file not found or not readable at $cdrPath"], 500);
    }

    $history = [];
    if (($handle = fopen($cdrPath, 'r')) !== FALSE) {
        // These are the default columns in Master.csv
        $headers = ['accountcode', 'src', 'dst', 'dcontext', 'clid', 'channel', 'dstchannel', 'lastapp', 'lastdata', 'start', 'answer', 'end', 'duration', 'billsec', 'disposition', 'amaflags', 'userfield', 'uniqueid'];
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $history[] = array_combine($headers, $data);
        }
        fclose($handle);
    }

    json_response(array_reverse($history)); // Show most recent first
});

$router->get('/calls/count', function() {
    // This is a simplified example. You could add filters by date, disposition, etc.
    $cdrPath = '/var/log/asterisk/cdr-csv/Master.csv'; 

     if (!file_exists($cdrPath) || !is_readable($cdrPath)) {
        json_response(['error' => "CDR file not found or not readable at $cdrPath"], 500);
    }

    $count = 0;
    if (($handle = fopen($cdrPath, 'r')) !== FALSE) {
        while (fgetcsv($handle, 1000, ',') !== FALSE) {
            $count++;
        }
        fclose($handle);
    }

    json_response(['total_calls' => $count]);
});

// Add a simple root endpoint for health check
$router->get('/', function() {
    json_response(['message' => 'Asterisk Call API is running']);
});


$router->run(); 