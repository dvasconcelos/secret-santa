<?php

const DRYRUN = true;
$people = json_decode(file_get_contents("list.json"));

$result = null;
$i = 0;
while($result === null && $i < 2) {
  try {
    $result = select($people);
  } catch (Exception $e) {
    echo "NOTICE: Round " . ($i + 1) .  " missed !" . PHP_EOL;
    $i++;
  }
}

if ($result === null) {
  echo "ERROR: No match found !" . PHP_EOL;
} else {
  send($people, $result);
}

/**
 * @param stdClass $people
 *
 * @return string[]
 **/
function getReceivers(stdClass $people)
{
  $receivers = [];
  foreach ($people as $name => $properties) {
    $receivers[] = $name;
  }
  return $receivers;
}

/**
 * @param stdClass $people
 *
 * @return string[]
 **/
function getExceptions(stdClass $people)
{
  $exceptions = [];
  foreach ($people as $name => $properties) {
    $exceptions[$name] = $properties->exceptions;
    $exceptions[$name][] = $name;
  }
  return $exceptions;
}

/**
 * @param stdClass $people
 *
 * @return array
 *
 * @throws Exception
 **/
function select(stdClass $people)
{
  $result = [];
  $receivers = getReceivers($people);
  $exceptions = getExceptions($people);

  foreach ($people as $name => $properties) {
    $receiver = null;
    $j = 0;

    while($receiver === null && $j < 10) {
      $key = mt_rand(0, count($receivers) - 1);
      if (!in_array($receivers[$key], $exceptions[$name])) {
        $receiver = $receivers[$key];
      }
      $j++;
    }

    if ($receiver === null) {
      throw new Exception("Missed round !");
    }

    $result[$name] = $receiver;
    $exceptions[$receiver][] = $name;
    array_splice($receivers, $key, 1);
  }

  return $result;
}

/**
 * @param stdClass $people
 * @param array $result
 *
 * @return void
 **/
function send(stdClass $people, array $result)
{
  $subject = "Secret Santa";
  $from = "Père Noël<pere@noel.com>";
  $messageModel = "Bonjour %s," . PHP_EOL . PHP_EOL .
    "Cette année, tu devras faire un cadeau à %s." . PHP_EOL .
    "Le budget maximum pour ce cadeau est de 30€" . PHP_EOL . PHP_EOL .
    "Joyeux Noël !" . PHP_EOL .
    "Le Père Noël";
  $headers = "From: $from\r\n" .
    "Reply-To: $from\r\n" .
    "X-Mailer: PHP/" .phpversion();

  foreach ($people as $name => $properties) {
    $to = $properties->email;

    if ($properties->email == null) {
      $sendToCount = count($properties->sendTo);
      $key = mt_rand(0, $sendToCount - 1);
      if ($properties->sendTo[$key] === $result[$name]) {
        $key = ($key + 1) % $sendToCount;
      }
      $senTo = $properties->sendTo[$key];
      $to = $people->$senTo->email;
    }

    $message = sprintf($messageModel, $name, $result[$name]);
    if (DRYRUN) {
      $data = $from . PHP_EOL .
        $to . PHP_EOL .
        $subject . PHP_EOL .
        $message;
      file_put_contents("test/$name.txt", $data);
    } else {
      mail($to , $subject , $message, $headers);
      echo "INFO: Email sent to $to." . PHP_EOL;
    }
  }

}
