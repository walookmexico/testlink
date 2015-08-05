<?php
/**
 * TestLink Open Source Project - http://testlink.sourceforge.net/
 *
 * @filesource  assemblarestInterface.class.php
 * @author      Alejandro Ariztegui
 * @company     Walook
 */
require_once(TL_ABS_PATH . "/third_party/assembla-rest/RestRequest.php");
require_once(TL_ABS_PATH . "/third_party/assembla-rest/Assembla.php");

class assemblarestInterface extends issueTrackerInterface
{
  private $APIClient;

  /**
   * Construct and connect to BTS.
   *
   * @param string $type (see tlIssueTracker.class.php $systems property)
   * @param xml $config
   * @param string $name
   **/
  function __construct($type, $config, $name)
  {
    $this->name = $name;
    $this->interfaceViaDB = false;
    $this->methodOpt['buildViewBugLink'] = array('addSummary' => true, 'colorByStatus' => false);

    if ($this->setCfg($config)) {
      $this->connect();
    }
  }

  /**
   * Returns URL to the bugtracking page for viewing ticket
   * 
   * @param int $issueID the number of the ticket
   * @param null $opt optional set
   * @return string returns a complete HTML HREF to view the bug (if found in db)
   */
  function buildViewBugLink($issueID, $opt = null)
  {
    $link = "<a href='" . $this->buildViewBugURL($issueID) . "' target='_blank'>";
    $issue = $this->getIssue($issueID);

    $ret = new stdClass();
    $ret->link = '';
    $ret->isResolved = false;
    $ret->op = false;

    if (is_null($issue) || !is_object($issue)) {
      $ret->link = "TestLink Internal Message: getIssue($issueID) FAILURE on " . __METHOD__;
      return $ret;
    }
    
    $link .= $issue->number;

    if ($this->methodOpt['buildViewBugLink']['addSummary']) {
      if (!is_null($issue->summary)) {
        $link .= " : ";
        $link .= (string)$issue->summary;
      }
    }
    $link .= "</a>";

    $ret = new stdClass();
    $ret->link = $link;
    $ret->isResolved = $issue->status;
    $ret->op = true;
    
    return $ret;
  }

  /**
   * Return the URL to the bugtracking page for viewing
   * the bug with the given id.
   *
   * @param int $id the bug id
   *
   * @return string returns a complete URL to view the bug
   **/
  function buildViewBugURL($id)
  {
    return 'https://www.assembla.com/spaces/' . (string)trim($this->cfg->space_id) . '/tickets/' . urlencode($id);;
  }

  /**
   * establishes connection to the bugtracking system
   *
   * @return bool
   */
  function connect()
  {
    try {
      // CRITIC NOTICE for developers
      // $this->cfg is a simpleXML Object, then seems very conservative and safe
      // to cast properties BEFORE using it.
      $par = array('api_key' => (string)trim($this->cfg->api_key),
        'api_key_secret' => (string)trim($this->cfg->api_key_secret),
        'space_id' => (string)trim($this->cfg->space_id));
      $this->APIClient = new AssemblaApi\Assembla($par);
      $this->connected = true;
    } catch (Exception $e) {
      $this->connected = false;
      tLog(__METHOD__ . "  " . $e->getMessage(), 'ERROR');
    }
  }

  /**
   * useful for testing
   */
  function getAPIClient()
  {
    return $this->APIClient;
  }

  /**
   * @return bool
   */
  function isConnected()
  {
    return $this->connected;
  }

  /**
   * Fetches a ticket specified by the number given
   * 
   * @param $ticket_number
   * @return array containing the ticket data, if not found returns null
   */
  function getIssue($ticket_number)
  {
    if (!$this->isConnected()) {
      tLog(__METHOD__ . '/Not Connected ', 'ERROR');

      return false;
    }

    $ticket = null;
    try {
      $ticket = $this->APIClient->getTicket($ticket_number);
    } catch (Exception $e) {
      tLog("Assembla Ticket number $ticket_number - " . $e->getMessage(), 'WARNING');
    }

    return $ticket;
  }

  /**
   * Gets a ticket and returns the status
   * 
   * @param $ticket_number
   * @return string containing the status of the ticket
   */
  function getIssueStatus($ticket_number)
  {
    $ticket = $this->getIssue($ticket_number);

    return (!is_null($ticket)) ? $ticket->status : false;
  }

  /**
   * Creates a ticket with the data specified
   * 
   * @param $summary
   * @param $description
   * @param null $optional
   * @return array
   */
  function addIssue($summary, $description, $optional = null)
  {
    $ret = array('status_ok' => false, 'id' => -1,'msg' => '');
    $ticket = array(
      "summary" => $summary,
      "description" => $description
    );
    if (property_exists($optional, "assembla_assigned_to")) {
      $ticket["assigned_to_id"] = $optional->assembla_assigned_to;
    }
    if (property_exists($optional, "assembla_estimate")) {
      $ticket["estimate"] = $optional->assembla_estimate;
    }
    if (property_exists($optional, "assembla_milestone")) {
      $ticket["milestone_id"] = $optional->assembla_milestone;
    }
    if (property_exists($optional, "assembla_reported_by")) {
      $ticket["reporter_id"] = $optional->assembla_reported_by;
    }
    try {
      $response = $this->APIClient->createTicket($ticket);
      $ret['status_ok'] = true;
      $ret['id'] = (string)$response->number;
      $ret['msg'] = lang_get('assembla_ticket_created');
      // attachment
      if (property_exists($optional, "attachment")) {
        if (!is_null($optional->attachment)) {
          $ticket_id = $response->id;
          $attachment = $optional->attachment;
          $this->APIClient->addAttachment($ticket_id, $attachment);
        }
      }
    } catch (Exception $e) {
      $msg = "Create ASSEMBLA Ticket FAILURE => " . $e->getMessage();
      tLog($msg, 'WARNING');
      $ret['msg'] = $msg . ' - serialized ticket:' . serialize($ticket);
    }

    return $ret;
  }

  /**
   * @return string template used for setting up Assembla
   */
  public static function getCfgTemplate()
  {
    $template = "<!-- Template " . __CLASS__ . " -->\n" .
      "<issuetracker>\n" .
      "<api_key>ASSEMBLA API KEY FOR THE USER CERATING THE ISSUES</api_key>\n" .
      "<api_key_secret>ASSEMBLA API KEY SECRET FOR THE USER CERATING THE ISSUES</api_key_secret>\n" .
      "<space_id>NAME OF THE SPACE HOSTING THE PROJECT</space_id>\n" .
      "</issuetracker>\n";

    return $template;
  }

  /**
   * @return bool
   */
  function canCreateViaAPI()
  {
    return (property_exists($this->cfg, 'api_key') &&
            property_exists($this->cfg, 'api_key_secret') &&
            property_exists($this->cfg, 'space_id'));
  }

  /**
   * This is used to determine when to show the fields to create a Ticket in Assembla
   * 
   * @return string
   */
  function getIssueTrackerType()
  {
    return 'assembla';
  }

  /**
   * checks id for validity
   *
   * @param string $issueID
   *
   * @return bool returns true if the bugid has the right format
   **/
  function checkBugIDSyntax($issueID)
  {
    return $this->checkBugIDSyntaxNumeric($issueID);
  }

  /**
   * checks if bug id is present on BTS
   *
   * @param string $issueID
   * @return bool true if issue exists on BTS
   **/
  function checkBugIDExistence($issueID)
  {
    $issue = $this->getIssue($issueID);
    if (is_null($issue) || $issue == false) {
      return false;
    } else {
      return true;
    }
  }

  /**
   * Assembla uses comments inside the tickets
   */
  public function addNote($issueID,$noteText,$opt=null)
  {
    try
    {
      $response = $this->APIClient->addComment($noteText,$issueID);
      $ret = array('status_ok' => false, 'id' => null, 'msg' => 'ko');
      if(!is_null($response))
      {
        if($response == false)
        {
          $ret['msg'] = "Ticket not found";
        }
        else
        {
          $ret = array('status_ok' => true, 'id' => $response->ticket_id,
                    'msg' => sprintf(lang_get('assembla_comment_added'),$issueID));
        }
      }
    }
    catch (Exception $e)
    {
      $msg = "Add Assembla Ticket Comment (REST) FAILURE => " . $e->getMessage();
      tLog($msg, 'WARNING');
      $ret = array('status_ok' => false, 'id' => -1, 'msg' => $msg);
    }
    return $ret;
  }
}