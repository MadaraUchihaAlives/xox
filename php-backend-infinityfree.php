<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$roomId = isset($_GET['room']) ? $_GET['room'] : '';
$roomsDir = __DIR__ . '/rooms';

if (!is_dir($roomsDir)) {
  mkdir($roomsDir, 0777, true);
}

function generateBoardState() {
  return array_map(function($i) {
    return array(
      'index' => $i,
      'value' => '',
      'player' => ''
    );
  }, range(0, 8));
}

function getRandomSymbol() {
  return rand(0, 1) === 0 ? 'X' : 'O';
}

if ($action === 'create') {
  $roomId = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'), 0, 5);
  $password = isset($_POST['password']) ? $_POST['password'] : '';

  $creatorSymbol = getRandomSymbol();
  $opponentSymbol = $creatorSymbol === 'X' ? 'O' : 'X';

  $roomData = array(
    'id' => $roomId,
    'password' => $password,
    'creator_connected' => true,
    'opponent_connected' => false,
    'creator_symbol' => $creatorSymbol,
    'opponent_symbol' => $opponentSymbol,
    'current_turn' => 'X',
    'board_state' => generateBoardState(),
    'winner' => null,
    'created_at' => date('Y-m-d H:i:s'),
    'last_activity' => date('Y-m-d H:i:s')
  );

  $filePath = $roomsDir . '/' . $roomId . '.json';
  file_put_contents($filePath, json_encode($roomData, JSON_PRETTY_PRINT));

  echo json_encode(array(
    'success' => true,
    'room_id' => $roomId,
    'creator_symbol' => $creatorSymbol,
    'opponent_symbol' => $opponentSymbol
  ));
}

else if ($action === 'join') {
  $password = isset($_POST['password']) ? $_POST['password'] : '';
  $filePath = $roomsDir . '/' . $roomId . '.json';

  if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(array('error' => 'Room not found'));
    exit();
  }

  $roomData = json_decode(file_get_contents($filePath), true);

  if ($roomData['password'] && $roomData['password'] !== $password) {
    http_response_code(401);
    echo json_encode(array('error' => 'Incorrect password'));
    exit();
  }

  if ($roomData['opponent_connected']) {
    http_response_code(400);
    echo json_encode(array('error' => 'Room is full'));
    exit();
  }

  $roomData['opponent_connected'] = true;
  $roomData['last_activity'] = date('Y-m-d H:i:s');

  file_put_contents($filePath, json_encode($roomData, JSON_PRETTY_PRINT));

  echo json_encode(array(
    'success' => true,
    'room_id' => $roomId,
    'opponent_symbol' => $roomData['opponent_symbol'],
    'room_data' => $roomData
  ));
}

else if ($action === 'get') {
  $filePath = $roomsDir . '/' . $roomId . '.json';

  if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(array('error' => 'Room not found'));
    exit();
  }

  $roomData = json_decode(file_get_contents($filePath), true);
  echo json_encode($roomData);
}

else if ($action === 'move') {
  $index = isset($_POST['index']) ? (int)$_POST['index'] : -1;
  $player = isset($_POST['player']) ? $_POST['player'] : '';
  $symbol = isset($_POST['symbol']) ? $_POST['symbol'] : '';

  $filePath = $roomsDir . '/' . $roomId . '.json';

  if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(array('error' => 'Room not found'));
    exit();
  }

  $roomData = json_decode(file_get_contents($filePath), true);

  if ($roomData['current_turn'] !== $symbol) {
    http_response_code(400);
    echo json_encode(array('error' => 'Not your turn'));
    exit();
  }

  if ($roomData['board_state'][$index]['value'] !== '') {
    http_response_code(400);
    echo json_encode(array('error' => 'Cell already occupied'));
    exit();
  }

  $roomData['board_state'][$index] = array(
    'index' => $index,
    'value' => $symbol,
    'player' => $player
  );

  $winner = checkWin($roomData['board_state'], $symbol);
  $draw = !$winner && countEmpty($roomData['board_state']) === 0;

  $roomData['current_turn'] = $symbol === 'X' ? 'O' : 'X';
  if ($winner) {
    $roomData['winner'] = $symbol;
  } else if ($draw) {
    $roomData['winner'] = 'draw';
  }

  $roomData['last_activity'] = date('Y-m-d H:i:s');
  file_put_contents($filePath, json_encode($roomData, JSON_PRETTY_PRINT));

  echo json_encode(array(
    'success' => true,
    'room_data' => $roomData,
    'winner' => $roomData['winner']
  ));
}

else if ($action === 'reconnect') {
  $role = isset($_POST['role']) ? $_POST['role'] : '';
  $filePath = $roomsDir . '/' . $roomId . '.json';

  if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(array('error' => 'Room not found'));
    exit();
  }

  $roomData = json_decode(file_get_contents($filePath), true);

  if ($role === 'creator') {
    $roomData['creator_connected'] = true;
  } else if ($role === 'opponent') {
    $roomData['opponent_connected'] = true;
  }

  $roomData['last_activity'] = date('Y-m-d H:i:s');
  file_put_contents($filePath, json_encode($roomData, JSON_PRETTY_PRINT));

  echo json_encode(array(
    'success' => true,
    'room_data' => $roomData
  ));
}

else if ($action === 'exit') {
  $role = isset($_POST['role']) ? $_POST['role'] : '';
  $filePath = $roomsDir . '/' . $roomId . '.json';

  if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(array('error' => 'Room not found'));
    exit();
  }

  if ($role === 'creator') {
    unlink($filePath);
    echo json_encode(array('success' => true, 'message' => 'Room deleted'));
  } else if ($role === 'opponent') {
    $roomData = json_decode(file_get_contents($filePath), true);
    $roomData['opponent_connected'] = false;
    file_put_contents($filePath, json_encode($roomData, JSON_PRETTY_PRINT));
    echo json_encode(array('success' => true, 'message' => 'Left room'));
  }
}

else if ($action === 'delete') {
  $filePath = $roomsDir . '/' . $roomId . '.json';
  if (file_exists($filePath)) {
    unlink($filePath);
    echo json_encode(array('success' => true));
  } else {
    http_response_code(404);
    echo json_encode(array('error' => 'Room not found'));
  }
}

else {
  http_response_code(400);
  echo json_encode(array('error' => 'Invalid action'));
}

function checkWin($boardState, $player) {
  $winCombos = array(
    array(0,1,2), array(3,4,5), array(6,7,8),
    array(0,3,6), array(1,4,7), array(2,5,8),
    array(0,4,8), array(2,4,6)
  );

  foreach ($winCombos as $combo) {
    if ($boardState[$combo[0]]['value'] === $player &&
        $boardState[$combo[1]]['value'] === $player &&
        $boardState[$combo[2]]['value'] === $player) {
      return true;
    }
  }
  return false;
}

function countEmpty($boardState) {
  $count = 0;
  foreach ($boardState as $cell) {
    if ($cell['value'] === '') $count++;
  }
  return $count;
}
?>
