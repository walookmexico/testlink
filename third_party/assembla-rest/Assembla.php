<?php
/**
 * Assembla Rest Client
 * 
 * @author  Alejandro Ariztegui
 * @company Walook
 */

namespace AssemblaApi;

class Assembla
{
  protected $space_id; // name of the project in Assembla
  protected $users; // users working on the project
  protected $milestones; // milestones contained in the project

  function __construct(array $config = array())
  {
    $this->request = new RestRequest();
    $this->request->api_key = (isset($config['api_key'])) ? trim($config['api_key']) : null;
    $this->request->api_key_secret = (isset($config['api_key_secret'])) ? trim($config['api_key_secret']) : null;
    $this->space_id = (isset($config['space_id'])) ? trim($config['space_id']) : null;
    $this->request->space_id = $this->space_id;
    $this->setUsers();
    $this->setMilestones();

    $this->configCheck();
  }

  /**
   * Verifies that the parameters have been set
   * 
   * @throws \Exception when at least one parameter has not been set
   */
  private function configCheck()
  {
    if (is_null($this->space_id) || $this->space_id == '') {
      throw new \Exception('Missing or Empty space_id - unable to continue');
    }
    if (is_null($this->request->api_key) || $this->request->api_key == '') {
      throw new \Exception('Missing or Empty api_key - unable to continue');
    }
    if (is_null($this->request->api_key_secret) || $this->request->api_key_secret == '') {
      throw new \Exception('Missing or Empty api_key_secret - unable to continue');
    }
  }

  public function setUsers()
  {
    if (!is_null($this->space_id)) {
      $this->users = $this->request->getUsers($this->space_id);
    }
  }

  public function getUsers()
  {
    return $this->users;
  }

  public function setMilestones()
  {
    if (!is_null($this->space_id)) {
      $this->milestones = $this->request->getMilestones($this->space_id);
    }
  }

  public function getMilestones()
  {
    return $this->milestones;
  }

  public function getSpaceId()
  {
    return $this->space_id;
  }

  /**
   * @param array $ticket_data containing the data the ticket will be created with
   * the only one required to create a ticket is the summary
   *
   * Example:
   *
   * $ticket_data = array(
   *                    "priority" => 3, // normal
   *                    "summary" => "Test ticket",
   *                    "assigned_to_id" => "user_id",
   *                    "description" => "This is a test ticket created for Assembla using TestLink",
   *                    "estimate" => 0.0,
   *                    "hierarchy_type" => 2 // story
   *                );
   *
   * For more details on the fields:
   * http://api-doc.assembla.com/content/ref/ticket_fields.html
   *
   * @return object response body, this is the ticket with all its attributes
   */
  public function createTicket($ticket_data)
  {
    $this->request->init($this->space_id,$ticket_data, 'POST');
    $this->request->execute();
    
    return $this->request->getResponseBody();
  }

  /**
   * @param array $ticket_data containing the data the ticket will be created with
   * It must contain the ticket number
   *
   * @return bool, the status of the last request sent to the server.
   */
  public function updateTicket($ticket_data)
  {
    $this->request->init($this->space_id, $ticket_data, 'PUT');
    $this->request->execute($ticket_data->number);

    return $this->request->getResponseInfo();
  }

  /**
   * Fetches a ticket from Assembla and returns it as an array
   * 
   * @param $ticket_number
   * @return array containing the ticket
   */
  public function getTicket($ticket_number)
  {
    $this->request->init($this->space_id);
    $this->request->execute($ticket_number);

    if ($this->request->getResponseInfo()['http_code'] == 404) {
      return false;
    }
    return $this->request->getResponseBody();
  }

  /**
   * @param string $comment will be appended to the ticket description
   * @param int $ticket_number number to id the ticket in Assembla
   * @return bool false if the ticket is not found
   */
  public function addComment($comment, $ticket_number)
  {
    if ($this->getTicket($ticket_number) == false) {
      return false;
    } else {
      $this->request->addComment($comment, $ticket_number, $this->space_id);

      return $this->request->getResponseBody();
    }
  }

  public function addAttachment($ticket_id, $file = null)
  {
    $this->request->addAttachment($ticket_id, $this->space_id, $file);

    return $this->request->getResponseInfo();
  }
}