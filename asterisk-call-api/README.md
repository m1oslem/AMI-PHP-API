# Asterisk Call API

This is a simple RESTful API for interacting with the Asterisk Manager Interface (AMI). It allows you to perform actions like initiating calls, hanging up, and monitoring live calls.

## Features

- Login to AMI
- View live calls
- View call history (from CDR file)
- Originate new calls
- Hangup existing calls
- Answer incoming calls
- Simple Bearer Token authentication

## Requirements

- PHP >= 7.4
- Composer
- An Asterisk server with AMI enabled and CDR to CSV enabled.

## Installation & Setup

1.  **Clone the repository:**
    ```bash
    git clone <your-repo-url>
    cd asterisk-call-api
    ```

2.  **Install dependencies:**
    ```bash
    composer install
    ```

3.  **Configure AMI Credentials:**
    Open `config/ami.php` and update it with your Asterisk AMI details:
    ```php
    <?php

    return [
        'host' => '127.0.0.1',       // Your Asterisk server IP
        'port' => 5038,
        'username' => 'your-ami-user', // Your AMI username
        'secret' => 'your-ami-secret',   // Your AMI secret
        'connect_timeout' => 10,
        'read_timeout' => 10,
    ];
    ```

4.  **Configure API Token:**
    Open `public/index.php` and change the `API_TOKEN` constant to a secure, random string:
    ```php
    // ...
    define('API_TOKEN', 'YOUR_SUPER_SECRET_API_TOKEN');
    // ...
    ```

5.  **Configure CDR File Path (Optional):**
    The API reads call history from `/var/log/asterisk/cdr-csv/Master.csv` by default. If your path is different, update it in `public/index.php` in the `/calls/history` and `/calls/count` routes.

6.  **Run the local server:**
    You can use the built-in PHP server for testing. Run this command from the project's root directory.
    ```bash
    php -S localhost:8000 -t public
    ```
    The API will be available at `http://localhost:8000`.

## API Endpoints

**NOTE:** All endpoints under `/calls/` require an `Authorization: Bearer YOUR_SUPER_SECRET_API_TOKEN` header.

---

### Authentication

#### `POST /login`

Establishes the connection with the Asterisk AMI. This must be called before any other `/calls/` endpoint.

**Response:**
```json
{
    "message": "Successfully logged into Asterisk AMI"
}
```

---

### Calls

#### `GET /calls/live`

Get a list of all current active channels (live calls).

**Postman:**
- **Method:** `GET`
- **URL:** `http://localhost:8000/calls/live`
- **Headers:**
  - `Authorization`: `Bearer YOUR_SUPER_SECRET_API_TOKEN`

**Success Response:**
```json
[
    {
        "privilege": "System,All",
        "channel": "PJSIP/101-0000000a",
        "channelstate": "6",
        "channelstatedesc": "Up",
        "calleridnum": "101",
        "calleridname": "Device 101",
        "connectedlinenum": "102",
        "connectedlinename": "Device 102",
        "language": "en",
        "accountcode": "",
        "context": "from-internal",
        "exten": "102",
        "priority": "1",
        "uniqueid": "1678886400.10",
        "linkedid": "1678886400.10"
    }
]
```

---

#### `POST /calls/originate`

Originate a new call.

**Postman:**
- **Method:** `POST`
- **URL:** `http://localhost:8000/calls/originate`
- **Headers:**
  - `Authorization`: `Bearer YOUR_SUPER_SECRET_API_TOKEN`
  - `Content-Type`: `application/json`
- **Body (raw, JSON):**
  ```json
  {
      "channel": "PJSIP/101",
      "context": "from-internal",
      "exten": "102",
      "priority": 1,
      "callerId": "API Call <123>"
  }
  ```

**Success Response:**
```json
{
    "message": "Call originated successfully",
    "response": {
        "response": "Success",
        "actionid": "<uuid>",
        "message": "Originate successfully queued"
    }
}
```

---

#### `POST /calls/hangup`

Hang up a specific channel.

**Postman:**
- **Method:** `POST`
- **URL:** `http://localhost:8000/calls/hangup`
- **Headers:**
  - `Authorization`: `Bearer YOUR_SUPER_SECRET_API_TOKEN`
  - `Content-Type`: `application/json`
- **Body (raw, JSON):**
  ```json
  {
      "channel": "PJSIP/101-0000000a"
  }
  ```

**Success Response:**
```json
{
    "message": "Hangup initiated successfully",
    "response": {
        "response": "Success",
        "actionid": "<uuid>",
        "message": "Channel hangout successfully"
    }
}
```

---

#### `POST /calls/answer`

Answer a ringing channel. This is done by redirecting the channel to a dialplan context that answers.

**Postman:**
- **Method:** `POST`
- **URL:** `http://localhost:8000/calls/answer`
- **Headers:**
  - `Authorization`: `Bearer YOUR_SUPER_SECRET_API_TOKEN`
  - `Content-Type`: `application/json`
- **Body (raw, JSON):**
  ```json
  {
      "channel": "PJSIP/101-0000000b",
      "context": "from-internal-answer",
      "exten": "s",
      "priority": 1
  }
  ```
  *Note: You need a context like `[from-internal-answer]` in your dialplan with an extension `s` that calls the `Answer()` application.*

**Success Response:**
```json
{
    "message": "Answer (Redirect) initiated successfully",
    "response": {
        "response": "Success",
        "actionid": "<uuid>",
        "message": "Redirect successful"
    }
}
```

---

#### `GET /calls/history`

Get call history from the CDR `Master.csv` file.

**Postman:**
- **Method:** `GET`
- **URL:** `http://localhost:8000/calls/history`
- **Headers:**
  - `Authorization`: `Bearer YOUR_SUPER_SECRET_API_TOKEN`

**Success Response:**
```json
[
    {
        "accountcode": "",
        "src": "101",
        "dst": "102",
        "dcontext": "from-internal",
        "clid": "\"Device 101\" <101>",
        "channel": "PJSIP/101-0000000a",
        "dstchannel": "PJSIP/102-0000000b",
        "lastapp": "Dial",
        "lastdata": "PJSIP/102,,T",
        "start": "2023-03-15 12:00:00",
        "answer": "2023-03-15 12:00:05",
        "end": "2023-03-15 12:00:15",
        "duration": "15",
        "billsec": "10",
        "disposition": "ANSWERED",
        "amaflags": "3",
        "userfield": "",
        "uniqueid": "1678886400.10"
    }
]
```

---

#### `GET /calls/count`

Get the total number of calls from the CDR `Master.csv` file.

**Postman:**
- **Method:** `GET`
- **URL:** `http://localhost:8000/calls/count`
- **Headers:**
  - `Authorization`: `Bearer YOUR_SUPER_SECRET_API_TOKEN`

**Success Response:**
```json
{
    "total_calls": 1234
}
``` 