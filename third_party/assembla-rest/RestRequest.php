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
  protected $base_url; // url to interact with the API
  protected $ticket; // JSON containing the last ticket used
  protected $request_body; // body of the request to be sent to Assembla
  protected $content_type; // format of the body to be sent to Assembla
  protected $verb; // used to specify the request (GET, POST, PUT)

  protected $responseBody; // body of Assembla's answer, usually contains a ticket
  protected $responseInfo; // status of Assembla's answer

  /**
   * Initializes the required data to communicate with Assembla
   * 
   * @param null $space_id
   * @param null $content_type
   * @param string $verb
   * @param null $request_body
   */
  public function init($space_id = null, $content_type = null, $verb = 'GET', $request_body = null)
  {
    $this->space_id = $space_id;
    if (!is_null($this->space_id)) {
      $this->base_url = 'https://api.assembla.com/v1/spaces/' . $this->space_id . '/';
    } else {
      $this->base_url = null;
    }

    $this->verb = $verb;
    $this->request_body = $request_body;
    
    switch (strtoupper($content_type)) {
      case 'JSON':
        $this->content_type = 'application/json';
        break;
      case 'XML':
        $this->content_type = 'application/xml';
        break;
      default:
        $this->content_type = null;
        break;
    }
  }

  /**
   * Resets the communication data
   */
  public function end()
  {
    $this->base_url = null;
    $this->verb = 'GET';
    $this->request_body = null;
    $this->responseBody = null;
    $this->responseInfo = null;
  }

  /**
   * Sets some data to execute each verb
   *
   * @param null $ticket_number
   */
  public function execute($data_location)
  {
    $ch = curl_init();
    $this->url = $this->base_url . $data_location;
    try {
      switch (strtoupper($this->verb)) {
        case 'GET':
          $this->executeGet($ch);
          break;
        case 'POST':
          $this->executePost($ch);
          break;
        case 'PUT':
          $this->executePut($ch);
          break;
        default:
          throw new \InvalidArgumentException('Current verb (' . $this->verb . ') is not supported.');
      }
    } catch (\InvalidArgumentException $e) {
      curl_close($ch);
      throw $e;
    } catch (\Exception $e) {
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request_body);

    $this->doExecute($ch);
  }

  protected function executePut($ch)
  {
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->request_body);

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
      $http_header = array('X-Api-Key: ' . $this->api_key, 'X-Api-Secret: ' . $this->api_key_secret);
      if ($this->content_type != null) {
        array_push($http_header, 'Content-type: '. $this->content_type);
      }
      curl_setopt($ch, CURLOPT_HTTPHEADER, $http_header);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
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
    $preresponse = explode("\r\n\r\n", $this->responseBody, 2);
	//	var_dump($preresponse);
	$response = $preresponse[1];
	
    return json_decode($response);
  }

  public function addComment($comment, $ticket_number, $space_id)
  {
    $url = 'https://api.assembla.com/v1/spaces/' . $space_id . '/tickets/' . $ticket_number . '/ticket_comments';
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
    $url = 'https://api.assembla.com/v1/spaces/' . $space_id . '/documents.json';
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