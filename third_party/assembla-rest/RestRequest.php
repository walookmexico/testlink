<?php
/**
 * Assembla Rest Request
 *
 * @author  Alejandro Ariztegui
 * @company Walook
 */

namespace AssemblaApi;

class RestRequest
{
  public $space_id; // name of the project in Assembla

  public $api_key; // key of the user that will interact with Assembla
  public $api_key_secret; // key secret of the user that will interact with Assembla

  protected $url; // url where the tickets are stored
  protected $ticket; // JSON containing the last ticket used
  protected $verb; // used to specify the request (GET, POST, PUT)

  protected $responseBody; // body of Assembla's answer, usually contains a ticket
  protected $responseInfo; // status of Assembla's answer

  /**
   * Initializes the required data to communicate with Assembla
   *
   * @param null $space_id
   * @param null $ticket_data
   * @param string $verb
   */
  public function init($space_id = null, $ticket_data = null, $verb = 'GET')
  {
    $this->space_id = $space_id;
    if (!is_null($this->space_id)) {
      $this->url = 'http://api.assembla.com/v1/spaces/' . $this->space_id . '/tickets/';
    } else {
      $this->url = null;
    }
    if (!is_null($ticket_data)) {
      $this->ticket = '{"ticket":' . json_encode($ticket_data) . '}';
    } else {
      $this->ticket = null;
    }
    $this->verb = $verb;
  }

  /**
   * Resets the communication data
   */
  public function end()
  {
    //$this->space_id = null;
    $this->ticket = null;
    $this->verb = 'GET';
    $this->responseBody = null;
    $this->responseInfo = null;
  }

  /**
   * Sets some data to execute each verb
   *
   * @param null $ticket_number
   */
  public function execute($ticket_number = null)
  {
    $ch = curl_init();
    $base_url = $this->url;
    try {
      switch (strtoupper($this->verb)) {
        case 'GET':
          if (!is_null($ticket_number)) {
            $this->url .= $ticket_number . '.json';
            $this->executeGet($ch);
          } else {
            throw new \InvalidArgumentException('You need a ticket number to execute ' . $this->verb);
          }
          break;
        case 'POST':
          $this->executePost($ch);
          break;
        case 'PUT':
          if (!is_null($ticket_number)) {
            $this->url .= $ticket_number . '.json';
            $this->executePut($ch);
          } else {
            throw new \InvalidArgumentException('You need a ticket number to execute ' . $this->verb);
          }
          break;
        default:
          throw new \InvalidArgumentException('Current verb (' . $this->verb . ') is not supported.');
      }
      $this->url = $base_url;
    } catch (\InvalidArgumentException $e) {
      curl_close($ch);
      throw $e;
    }
  }

  protected function executeGet($ch)
  {
    $this->doExecute($ch);
  }

  protected function executePost($ch)
  {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->ticket);

    $this->doExecute($ch);
  }

  protected function executePut($ch)
  {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->ticket);

    $this->doExecute($ch);
  }

  /**
   * Sends a request with all the options previously specified
   *
   * @param $ch
   */
  protected function doExecute(&$ch)
  {
    $this->setCurlOpts($ch);
    $this->responseBody = curl_exec($ch);
    $this->responseInfo = curl_getinfo($ch);
    curl_close($ch);
  }

  /**
   * Sets the curl options to send ticket requests to Assembla
   *
   * @param $ch
   */
  protected function setCurlOpts(&$ch)
  {
    curl_setopt($ch, CURLOPT_URL, $this->url);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    if ($this->api_key !== null && $this->api_key_secret !== null) {
      curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
          'Content-type: application/json',
          'X-Api-Key: ' . $this->api_key,
          'X-Api-Secret: ' . $this->api_key_secret
        )
      );
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
  }

  public function getResponseInfo()
  {
    if (isset($this->responseInfo['http_code']) &&
      ($this->responseInfo['http_code'] >= 200 && $this->responseInfo['http_code'] < 300)
    ) {
      return true;
    }

    return false;
  }

  public function getResponseBody()
  {
    $response = explode("\r\n\r\n", $this->responseBody, 2)[1];

    return json_decode($response);
  }

  /**
   * Sets the curl options for diverse requests to Assembla
   *
   * @param $ch
   * @param $url
   */
  protected function setCurl(&$ch, $url)
  {
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    if ($this->api_key !== null && $this->api_key_secret !== null) {
      curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-type: application/json',
        'X-Api-Key: ' . $this->api_key,
        'X-Api-Secret: ' . $this->api_key_secret));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
  }

  /**
   * Fetches the users in the project from Assembla
   *
   * @param $space_id
   * @return array|null
   */
  public function getUsers($space_id)
  {
    if (!is_null($space_id)) {
      $url = 'http://api.assembla.com/v1/spaces/' . $this->space_id . '/users/';

      $curl = curl_init();
      $this->setCurl($curl, $url);

      $response = curl_exec($curl);
      $response = explode("\r\n\r\n", $response, 2)[1];
      $usersAux = json_decode($response);

      $users = array();
      for ($i = 0; $i < sizeof($usersAux); $i++) {
        $users[$usersAux[$i]->id] = $usersAux[$i]->name;
      }

      $_SESSION['assembla_users'] = $users;
      return $users;
    } else {
      return NULL;
    }
  }

  /**
   * Fetches the users in the project from Assembla
   *
   * @param $space_id
   * @return array|null
   */
  public function getMilestones($space_id)
  {
    if (!is_null($space_id)) {
      $url = 'http://api.assembla.com/v1/spaces/' . $this->space_id . '/milestones/all/';

      $curl = curl_init();
      $this->setCurl($curl, $url);

      $response = curl_exec($curl);
      $response = explode("\r\n\r\n", $response, 2)[1];
      $milestonesAux = json_decode($response);

      $milestones = array();
      for ($i = 0; $i < sizeof($milestonesAux); $i++) {
        $milestones[$milestonesAux[$i]->id] = $milestonesAux[$i]->title;
      }

      $_SESSION['assembla_milestones'] = $milestones;
      return $milestones;
    } else {
      return NULL;
    }
  }

  public function addComment($comment, $ticket_number, $space_id)
  {
    $url = 'http://api.assembla.com/v1/spaces/' . $space_id . '/tickets/' . $ticket_number . '/ticket_comments';
    $ticket_comment = '{"ticket_comment":{"comment":"' . $comment . '"}}';

    $curl = curl_init();
    $this->setCurl($curl, $url);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $ticket_comment);

    $this->responseBody = curl_exec($curl);
    $this->responseInfo = curl_getinfo($curl);
    curl_close($curl);
  }

  public function addAttachment($ticket_id, $space_id, $file = null)
  {
    $url = 'http://api.assembla.com/v1/spaces/' . $space_id . '/documents.json';
    $file = curl_file_create($file['tmp_name'], $file['type']);
    $document = array(
      'document[file]' => $file,
      'document[attachable_type]' => 'Ticket',
      'document[attachable_id]' => $ticket_id
    );
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, TRUE);
    curl_setopt($curl, CURLOPT_SAFE_UPLOAD, TRUE);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $document);
    if ($this->api_key !== null && $this->api_key_secret !== null) {
      curl_setopt($curl, CURLOPT_HTTPHEADER, array(
        'X-Api-Key: ' . $this->api_key,
        'X-Api-Secret: ' . $this->api_key_secret));
    }
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_TIMEOUT, 50);
    curl_setopt($curl, CURLOPT_FAILONERROR, TRUE);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 3);

    $this->responseBody = curl_exec($curl);
    $this->responseInfo = curl_getinfo($curl);
    curl_close($curl);
  }
}