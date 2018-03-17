<?php

namespace vaterlangen\CalDavBundle\Processor;

use vaterlangen\CalDavBundle\Model\CalDavInterface;
use vaterlangen\CalDavBundle\Model\EventInterface;
use vaterlangen\CalDavBundle\Model\EventLocationInterface;
use vaterlangen\CalDavBundle\Model\EventReminderInterface;
use vaterlangen\CalDavBundle\Model\EventOrganizerInterface;
use vaterlangen\CalDavBundle\Model\EventAttendeesInterface;
use vaterlangen\CalDavBundle\Model\EventOpaqueInterface;
use vaterlangen\CalDavBundle\Model\EventWholeDayInterface;
use vaterlangen\CalDavBundle\Model\AttendeeInterface;

use Symfony\Component\Locale\Locale;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Intl\Intl;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CalDavProcessor
{
	/**
	 *
	 * @var ContainerInterface
	 */
	protected $container;
	
	/**
	 *
	 * @var CalDavClient
	 */
	protected $client;
	
	/**
	 *
	 * @var string
	 */
	protected $currentConnection;
	
	/**
	 *
	 * @var array
	 */
	protected $connectionsAvail;
	
	
	/**
	 * 
	 * @param string $connectionName
	 * @param ContainerInterface $container
	 */
	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
		
		/* get new client and allow following max. 5 redirects */
		$this->client = new CalDavClient();
		$this->client->set_follow_redirects(true,5);
		
		/* get configured parameters from container */
		$this->connectionsAvail = $this->container->getParameter('vaterlangen_cal_dav.calendars');
		$this->currentConnection = NULL;
		
		return $this;
	}
	
	/**
	 * Setup a connection by config name
	 * 
	 * @param string $connectionName
	 * @return \vaterlangen\CalDavBundle\Processor\CalDavProcessor
	 */
	public function setConnetion($connectionName)
	{
		return $this->loadConnectionData($connectionName);
	}
	
	
	/**
	 * Send vevent to server
	 *
	 * @param object $entity
	 * @param array $categories
	 * @return string UID
	 *
	 * @throws Exception
	 */
	public function set($entity, $categories = NULL)
	{
		/* check if ID interface is implemented */
		if (!$entity instanceof CalDavInterface)
		{
			throw new \InvalidArgumentException("The given entity must implement CalDavInterface!");
		}
	
		/* create vEvent */
		$vEvent = $this->generateFromEntity($entity, $categories);
	
		/* save vcard to server */
		$uid = $this->setByUID($vEvent, $entity->getCalDavID());
	
		/* write uid to entity */
		$entity->setCalDavID($uid);
	
		return $uid;
	}
	
	/**
	 * Receive vEvent from server
	 *
	 * @param object $entity
	 * @param  bool $returnRaw
	 * @return vEvent|string
	 *
	 * @throws Exception
	 */
	public function get($entity, $returnRaw = false)
	{
		/* check if ID interface is implemented */
		if (!$entity instanceof CalDavInterface)
		{
			throw new \InvalidArgumentException("The given entity must implement CalDavInterface!");
		}

		/* get uid */
		$uid = $entity->getCalDavID();
		if (!$uid)
		{
			throw new \InvalidArgumentException("There is no vEvent assigned to the given entity!");
		}

		return $this->getByUID($uid, $returnRaw);
	}
	
	
	/**
	 * Delete vevent from server
	 *
	 * @param object $entity
	 * @return boolean
	 *
	 * @throws Exception
	 */
	public function delete($entity)
	{
		/* check if ID interface is implemented */
		if (!$entity instanceof CalDavInterface)
		{
			throw new \InvalidArgumentException("The given entity must implement CalDavInterface!");
		}
	
		/* get uid */
		$uid = $entity->getCalDavID();
		if (!$uid)
		{
			return true;
		}
	
		/* execute delete and store to entity */
		if ($this->deleteByUID($uid))
		{
			$entity->setCalDavID(NULL);
			return true;
		}
		
		return false;
	}
	
	
	/**
	 * Delete vevent from server
	 *
	 * @param string $vEventId
	 * @return boolean
	 *
	 * @throws Exception
	 */
	public function deleteByUID($uid)
	{
		/* check for valid uid */
		if (!$uid)
		{
			return true;
		}
		
		$this->checkConnection();
		
		try 
        {
			$result = $this->client->delete($uid);
		}
        catch(\Exception $e)
		{
			if (!preg_match('/http status code 404/i', $e->getMessage()))
			{
				throw $e;
			}
		}
		
		return true;
	}
	
	
	
	/**
	 * Get vevent from server
	 * 
	 * @param string $uid
	 * @param bool $returnRaw
	 * @return vEvent|string
	 * 
	 * @throws Exception
	 */
	public function getByUID($uid, $returnRaw = false)
	{
		$this->checkConnection();
		
		try 
        {
			$result = $this->client->get($uid);
		}
        catch(\Exception $e)
		{
			throw $e;
		}

        // only raw supported ATM
        return $result;
		
		/* check wether to return raw data or vevent */
		if ($returnRaw)
		{
			return $result;
		}
		else
		{
			$vevent = new vEvent();
			$vevent->parse($result);
			return $vevent;
		}		
	}
	
	/**
	 * Send vevent to server
	 *
	 * @param vEvent|string $vEvent
	 * @param string $uid
	 * @return string UID
	 *
	 * @throws Exception
	 */
	public function setByUID($vEvent, $uid = NULL)
	{
		if (gettype($vEvent) ==! 'string' && !$vEvent instanceof vEvent)
		{
			throw new \InvalidArgumentException("The given object has to be either string or vEvent! (".gettype($vEvent)." given)");
		}
		
		$this->checkConnection();
	
		try 
        {   
            if (NULL == $uid)
            {
			    $result = $this->client->add($vEvent);
            }
            else
            {
                $result = $this->client->update($vEvent, $uid);
            }
		}
        catch(\Exception $e)
		{
			throw $e;
		}
	
	
		return $result;
	}
	
	/**
	 * @param object $entity
	 * @param array $categories
	 * @return vEvent
	 * 
	 * @throws InvalidArgumentException
	 */
	public function generateFromEntity($entity, $categories = NULL)
	{
        if (!$entity instanceof EventInterface)
        {
            throw new \InvalidArgumentException("The entity needs to implement at least BaseEventInterface!");
        }

		#$vevent = new vEvent();
		
		/* add basic header */
		#$vevent->setProperty('VERSION', '2.0',0,0);	
        $vevent = array();
        $vevent[] = "BEGIN:VCALENDAR";
        $vevent[] = "VERSION:2.0";
        $vevent[] = "CALSCALE:GREGORIAN";
        $vevent[] = "BEGIN:VTIMEZONE";
        $vevent[] =     "TZID:" . $entity->getEventBegin()->getTimezone()->getName();
        $vevent[] =     "BEGIN:DAYLIGHT";
        $vevent[] =         "TZOFFSETFROM:+0100";
        $vevent[] =         "TZOFFSETTO:+0200";
        $vevent[] =         "TZNAME:CEST";
        $vevent[] =         "DTSTART:19700329T030000Z";
        $vevent[] =         "RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3";
        $vevent[] =     "END:DAYLIGHT";
        $vevent[] =     "BEGIN:STANDARD";
        $vevent[] =         "TZOFFSETFROM:+0200";
        $vevent[] =         "TZOFFSETTO:+0100";
        $vevent[] =         "TZNAME:CET";
        $vevent[] =         "DTSTART:19701025T040000Z";
        $vevent[] =         "RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10";
        $vevent[] =     "END:STANDARD";
        $vevent[] = "END:VTIMEZONE";
        $vevent[] = "BEGIN:VEVENT";
        $vevent[] = "DTSTAMP:" . $this->dateToCal($entity->getCreatedAt()->format("U"));
        $vevent[] = "LAST-MODIFIED:" . $this->dateToCal();
        if ($entity instanceof EventWholeDayInterface && $entity->isEventWholeDay())
        {
            $dayafter = new \DateTime($entity->getEventEnd()->format('Y-m-d'));
            $dayafter->modify('+1 day');
            $vevent[] = "DTSTART;VALUE=DATE:" . $entity->getEventBegin()->format('Y-m-d');
            $vevent[] = "DTEND;VALUE=DATE:" . $dayafter->format('Ymd');
            #$vevent[] = "TRANSP:TRANSPARENT";
        }
        else
        {
            $vevent[] = "DTSTART;TZID=" . $entity->getEventBegin()->getTimezone()->getName() . ":" . $this->dateToCal($entity->getEventBegin());
            $vevent[] = "DTEND;TZID=" . $entity->getEventEnd()->getTimezone()->getName() . ":" . $this->dateToCal($entity->getEventEnd());
            #$vevent[] = "TRANSP:OPAQUE";
        }
        $vevent[] = "TRANSP:OPAQUE";
        $vevent[] = "CLASS:PUBLIC";
        if ($entity->getEventDescription())
        {
            $vevent[] = "DESCRIPTION:" . $entity->getEventDescription();
        }
        if ($entity->getEventSummary())
        {
            $vevent[] = "SUMMARY:" . $entity->getEventSummary();
        }
        if ($entity instanceof EventLocationInterface)
        {
            $vevent[] = "LOCATION:" . $entity->getEventLocation();
        }
		
		/* check categories parameter */
		if (gettype($categories) === 'string')
		{
			$categories = array($categories);
		} 

		if (!is_array($categories))
		{
			$categories = array();
		}
		
		/* check categories from entity */
		$entityCategories = $entity->getEventCategories();
		if (gettype($entityCategories) === 'string')
		{
			$entityCategories = array($entityCategories);
		}
		
		if (!is_array($entityCategories))
		{
			$entityCategories = array();
		}

		/* merge categories */
		$categories = array_merge($categories, $this->getDefaultCategories());
		$categories = array_merge($categories, $entityCategories);
		
		/* write non-empty categoriesd to vcard */
		if (count($categories))
		{
			#$vevent->setProperty('CATEGORIES', join(',',$categories));
            $vevent[] = 'CATEGORIES:' . join(',', $categories);
		}

        /* add organizer to event */
        $orga = $this->getDefaultOrganizer();
        if ($entity instanceof EventOrganizerInterface && $entity->getEventOrganizer() != NULL)
        {
            $orga = array('name' => $entity->getEventOrganizer()->getFullName(), 'mail' => $entity->getOrganizer()->getFullMail());
        }
        if ($orga['name'] && $orga['mail'])
        {
            $vevent[] = "ORGANIZER;CN=" . $orga['name'] . ":mailto:" . $orga['mail'];
        }

        /* add attendees to event */
        if ($entity instanceof EventAttendeesInterface)
        {
            $attendees = $entity->getEventAttendees();
            if (is_array($attendees))
            {
                $vevent[] = 'METHOD:REQUEST';
                $vevent[] = "STATUS:CONFIRMED";

                foreach($attendees as $att)
                {
                    if (!($att instanceof AttendeeInterface))
                    {
                        throw new \InvalidArgumentException("getAttendees() must return classes that implement AttendeeInterface!");
                    }
                    $b = array();
                    $b[] = 'ATTENDEE';
                    $b[] = 'CUTYPE=INDIVIDUAL';
                    $b[] = 'ROLE=' . ($att->isRequired() ? 'REQ-PARTICIPANT' : 'OPT-PARTICIPANT');
                    $b[] = 'PARTSTAT=ACCEPTED';
                    $b[] = ' RSVP=TRUE';
                    $b[] = 'CN=' . $att->getFullName();
                    #$b[] = 'MAILTO:'. $att->getMail();
                    
                    $attendee = implode(";",$b);

                    $vevent[] = "$attendee:mailto:" . $att->getMail();
                }
            }
        }

        /* add reminder to event */
        $reminder = $this->getDefaultReminder();
        if ($entity instanceof EventReminderInterface)
        {
            $reminder['enabled'] = true;
            $reminder['trigger'] = $entity->getReminderTrigger();
            $reminder['repeatcount'] = $entity->getReminderRepeatCount();
            $reminder['repeatdelay'] = $entity->getReminderRepeatDelay();
            $reminder['message'] = $entity->getReminderMessage();
        }
        if ($reminder['enabled'])
        {     
            $vevent[] = "BEGIN:VALARM";
            $vevent[] =     "TRIGGER;VALUE=DURATION;RELATED=START:" . $reminder['trigger'];
            $vevent[] =     "REPEAT:" . $reminder['repeatcount'];
            $vevent[] =     "DURATION:" . $reminder['repeatdelay'];
            $vevent[] =     "ACTION:DISPLAY";
            $vevent[] =     "DESCRIPTION:" . $reminder['message'];
            $vevent[] = "END:VALARM";
		}

        $vevent[] = "END:VEVENT";
        $vevent[] = "END:VCALENDAR";

        $vevent = join("\n", $vevent);
		
		return $vevent;
	}
	
	/**
	 * Ceck if connection data is loaded
	 * 
	 * @param string $connectionName
	 * @return boolean
	 */
	public function isConnected($connectionName = NULL)
	{
		return $connectionName === NULL ? ($this->currentConnection !== NULL) : ($this->currentConnection === $connectionName);
	}

	/**
	 * 
	 * @throws \BadFunctionCallException
	 */
	private function checkConnection()
	{
		/* check if connection is loaded */
		if (!$this->isConnected())
		{
			throw new \BadFunctionCallException("Must be connected at this time!");
		}
		
		/* check if server is available */
		if (!$this->client->check_connection())
		{
			throw new \Exception("The connection to the server failed!");
		}
	}
	
	
	/**
	 * Load requested connection data from configuration file 
	 * 
	 * @param string $connectionName
	 * @throws \InvalidArgumentException
	 */
	private function loadConnectionData($connectionName)
	{
		/* check if connection already loaded */
		if ($this->currentConnection === $connectionName)
		{
			return $this;
		}
		 
		
		/* check if requested addressbook config does exist */
		if (!key_exists($connectionName, $this->connectionsAvail))
		{
			throw new \InvalidArgumentException("The requested calendar config for '$connectionName' does not exist!\r\n".
					"Existing configurations: ".join(', ', array_keys($this->connectionsAvail)));
		}
		 
		/* select requested connection */
		$adb = $this->connectionsAvail[$connectionName];
		
		/* build url from config */
		$url = 'http'.($adb['ssl'] ? 's' : '').'://'.$adb['server'].'/'.$adb['resource'].'/';
		
		/* set basic auth and url */
		$this->client->set_auth($adb['user'], $adb['password']);
		$this->client->set_url($url);
		
		/* store connection name */
		$this->currentConnection = $connectionName;

		return $this;
	}
	
	private function getDefaultCategories($connectionName = NULL)
	{
		$t = $this->connectionsAvail[($connectionName === NULL ? $this->currentConnection : $connectionName) ];
		return $t['categories'];
	}

    private function getDefaultOrganizer($connectionName = NULL)
	{
		$t = $this->connectionsAvail[($connectionName === NULL ? $this->currentConnection : $connectionName) ];
		return $t['organizer'];
	}

    private function getDefaultReminder($connectionName = NULL)
	{
		$t = $this->connectionsAvail[($connectionName === NULL ? $this->currentConnection : $connectionName) ];
		return $t['reminder'];
	}

    /**
	 * Convert file to base64 encoded string
	 * 
	 * @param File $file
	 * @return string
     * @throws Exception
	 */
    private function encodeBase64(File $file) 
    {
        $buffer = "";
        $path = $file->getRealPath();

		$fp = @fopen($path, "r");
		if (!$fp) 
        {
			throw new \Exception("Unable to read '$path'!");
		} 
        else 
        {
			while (!feof($fp)) {
				$buffer .= fgets($fp, 4096);
			}
		}
		@fclose($fp);

		return base64_encode($buffer);
	}

    private function url_exists($url) 
    {
        return ($fp = curl_init($url));
    }

    private function dateToCal($date = NULL) 
    {
        if ($date == NULL)
        {
            $date = new \DateTime("now", new \DateTimeZone("UTC"));
        }
        elseif ($date instanceof \DateTime)
        {
            return $date->format('Ymd\THis');
        }
        elseif (gettype($date) == 'string')
        {
            $date = new \DateTime("@$date", new \DateTimeZone("UTC"));
        }
        else
        {
            throw new \InvalidArgumentException("Parameter must be either NULL, string or DateTime but ".gettype($date)." given!");
        }        

        return $date->format('Ymd\THis\Z');
    }

}
