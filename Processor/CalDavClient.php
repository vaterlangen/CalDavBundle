<?php

namespace vaterlangen\CalDavBundle\Processor;

use \SimpleXMLElement;
use \XMLWriter;

use Symfony\Component\Config\Definition\Exception\Exception;


/**
 * Base on: https://github.com/graviox/CardDAV-PHP | https://github.com/christian-putzke/CardDAV-PHP
 * 
 * 
 * CalDAV PHP
 *
 * Simple CalDAV query
 * --------------------
 * $caldav = new CalDavClient('https://davical.example.com/user/calendar/');
 * $caldav->set_auth('username', 'password');
 * echo $caldav->get();
 *
 *
 * Simple vEvent query
 * ------------------
 * $caldav = new CalDavClient('https://davical.example.com/user/calendar/');
 * $caldav->set_auth('username', 'password');
 * echo $caldav->get('0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * Check CalDAV server connection
 * -------------------------------
 * $caldav = new CalDavClient('https://davical.example.com/user/calendar/');
 * $caldav->set_auth('username', 'password');
 * var_dump($caldav->check_connection());
 *
 *
 * CalDAV delete query
 * --------------------
 * $caldav = new CalDavClient('https://davical.example.com/user/calendar/');
 * $caldav->set_auth('username', 'password');
 * $caldav->delete('0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * CalDAV add query
 * --------------------
 * $vevent = 'BEGIN:VCALENDAR
 * VERSION:2.0
 * BEGIN:VEVENT
 * DTSTAMP:20150710T132153Z
 * DTSTART:20150712T123000
 * DTEND:20150712T130000
 * SUMMARY:TEST
 * END:VEVENT
 * END:VCALENDAR';
 *
 * $caldav = new CalDavClient('https://davical.example.com/user/calendar/');
 * $caldav->set_auth('username', 'password');
 * $vevent_id = $caldav->add($vevent);
 *
 *
 * CalDAV update query
 * --------------------
 * $vevent = 'BEGIN:VCALENDAR
 * VERSION:2.0
 * BEGIN:VEVENT
 * DTSTAMP:20150710T132153Z
 * DTSTART:20150712T123000
 * DTEND:20150712T130000
 * SUMMARY:TEST-123
 * END:VEVENT
 * END:VCALENDAR';
 *
 * $caldav = new CalDavClient('https://davical.example.com/user/calendar/');
 * $caldav->set_auth('username', 'password');
 * $caldav->update($vevent, '0126FFB4-2EB74D0A-302EA17F');
 *
 *
 * CalDAV debug
 * -------------
 * $caldav = new CalDavClient('https://davical.example.com/user/calendar/');
 * $caldav->enable_debug();
 * $caldav->set_auth('username', 'password');
 * $caldav->get();
 * var_dump($carddav->get_debug());
 *
 *
 * @author Christian Putzke <christian.putzke@graviox.de>, Fabian Hassel <hassel@khsoundlight.de>
 * @copyright Christian Putzke, Fabian Hassel
 * @link http://www.graviox.de/
 * @link https://twitter.com/cputzke/
 * @link http://khsoundlight.de
 * @since 20.07.2011
 * @version 0.6
 * @license http://www.gnu.org/licenses/agpl.html GNU AGPL v3 or later
 *
 */

class CalDavClient
{
	/**
	 * CalDAV PHP Version
	 *
	 * @constant	string
	 */
	const VERSION = '0.6';

	/**
	 * User agent displayed in http requests
	 *
	 * @constant	string
	 */
	const USERAGENT = 'CalDAV PHP/';

	/**
	 * CalDAV server url
	 *
	 * @var	string
	 */
	private $url = null;

	/**
	 * CalDAV server url_parts
	 *
	 * @var	array
	 */
	private $url_parts = null;

	/**
	 * Authentication string
	 *
	 * @var	string
	 */
	private $auth = null;

	/**
	* Authentication: username
	*
	* @var	string
	*/
	private $username = null;

	/**
	* Authentication: password
	*
	* @var	string
	*/
	private $password = null;

	/**
	 * Characters used for vEvent id generation
	 *
	 * @var	array
	 */
	private $vevent_id_chars = array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 'A', 'B', 'C', 'D', 'E', 'F');

	/**
	 * CalDAV server connection (curl handle)
	 *
	 * @var	resource
	 */
	private $curl;

	/**
	 * Debug on or off
	 *
	 * @var	boolean
	 */
	private $debug = false;

	/**
	 * All available debug information
	 *
	 * @var	array
	 */
	private $debug_information = array();
	
	/**
	 * Follow redirects (Location Header)
	 * 
	 * @var boolean
	 */
	private $follow_redirects = true;
	
	/**
	 * Maximum redirects to follow
	 *
	 * @var integer
	 */
	private $follow_redirects_count = 3;

	/**
	 * Exception codes
	 */
	const EXCEPTION_WRONG_HTTP_STATUS_CODE_GET				= 1000;
	const EXCEPTION_WRONG_HTTP_STATUS_CODE_GET_VEVENT		= 1001;
	const EXCEPTION_WRONG_HTTP_STATUS_CODE_GET_XML_VEVENT	= 1002;
	const EXCEPTION_WRONG_HTTP_STATUS_CODE_DELETE			= 1003;
	const EXCEPTION_WRONG_HTTP_STATUS_CODE_ADD				= 1004;
	const EXCEPTION_WRONG_HTTP_STATUS_CODE_UPDATE			= 1005;
	const EXCEPTION_MALFORMED_XML_RESPONSE					= 1006;
	const EXCEPTION_COULD_NOT_GENERATE_NEW_VEVENT_ID		= 1007;


	/**
	 * Constructor
	 * Sets the CalDAV server url
	 *
	 * @param	string	$url	CalDAV server url
	 */
	public function __construct($url = null)
	{
		if ($url !== null)
		{
			$this->set_url($url);
		}
	}

	/**
	 * Sets debug information
	 *
	 * @param	array	$debug_information		Debug information
	 * @return	void
	 */
	public function set_debug(array $debug_information)
	{
		$this->debug_information[] = $debug_information;
	}

	/**
	* Sets the CalDAV server url
	*
	* @param	string	$url	CalDAV server url
	* @return	void
	*/
	public function set_url($url)
	{
		$this->url = $url;

		if (substr($this->url, -1, 1) !== '/')
		{
			$this->url = $this->url . '/';
		}

		$this->url_parts = parse_url($this->url);
	}

	/**
	 * Sets authentication information
	 *
	 * @param	string	$username	CalDAV server username
	 * @param	string	$password	CalDAV server password
	 * @return	void
	 */
	public function set_auth($username, $password)
	{
		$this->username	= $username;
		$this->password	= $password;
		$this->auth		= $username . ':' . $password;
	}
	
	/**
	 * Sets wether to follow redirects and if yes how often
	 *
	 * @param	boolean	$follow_redirects
	 * @param	integer	$follow_redirects_count
	 * @return	void
	 */
	public function set_follow_redirects($follow_redirects, $follow_redirects_count = 3)
	{
		$this->follow_redirects	= $follow_redirects && $follow_redirects_count > 0;
		$this->follow_redirects_count = $follow_redirects_count > 0 ? $follow_redirects_count : 0;
	}

	/**
	 * Gets all available debug information
	 *
	 * @return	array	$this->debug_information	All available debug information
	 */
	public function get_debug()
	{
		return $this->debug_information;
	}

	/**
	* Gets a clean vEvent from the CalDAV server
	*
	* @param	string	$vevent_id	vEvent id on the CalDAV server
	* @return	string				vEvent (text/calendar)
	*/
	public function get($vevent_id)
	{
		$vevent_id	= str_replace('.ics', null, $vevent_id);
		$result		= $this->query($this->url . $vevent_id . '.ics', 'GET');

		switch ($result['http_code'])
		{
			case 200:
			case 207:
				return $result['response'];
			break;

			default:
				throw new Exception('Woops, something\'s gone wrong! The CalDAV server returned the http status code ' . $result['http_code'] . '.', self::EXCEPTION_WRONG_HTTP_STATUS_CODE_GET_VEVENT);
			break;
		}
	}

	/**
	 * Enables the debug mode
	 *
	 * @return	void
	 */
	public function enable_debug()
	{
		$this->debug = true;
	}

	/**
	* Checks if the CalDAV server is reachable
	*
	* @return	boolean
	*/
	public function check_connection()
	{
		$result = $this->query($this->url, 'OPTIONS');

		if ($result['http_code'] === 200)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Cleans the vEvent
	 *
	 * @param	string	$vevent	vEvent
	 * @return	string			vEvent
	 */
	private function clean_vevent($vevent)
	{
		$vevent = str_replace("\t", null, $vevent);

		return $vevent;
	}
	
	/**
	 * Forces the vEvent to have valid UID and PROPID field
	 *
	 * @param	string	$vevent		vEvent
	 * @param	string	$vevent_id	unique ID
	 * @return	string				vEvent
	 */
	private function force_uid($vevent, $vevent_id)
	{
		$vevent_ary = array();
		
		foreach (explode("\n", str_replace("\r\n", "\n", $vevent)) as $line)
		{
			// add UID at end of vcard
			if (preg_match("/^END:VEVENT/", $line))
			{
				$vevent_ary[] = "UID:$vevent_id";
			}
			
			// remove existing UID fields
			if (!preg_match("/[a-zA-Z]+/", $line) || preg_match("/^UID:/", $line) || preg_match("/^PRODID:/", $line))
			{
				continue;
			}
			
			// add current line to new vEvent
			$vevent_ary[] = $line;
			
			// add PRODID at beginning
			if (preg_match("/^BEGIN:VCALENDAR/", $line))
			{
				$vevent_ary[] = "PRODID:-//" . self::USERAGENT.self::VERSION . "//EN";
			}
		}
		
		return join("\r\n",$vevent_ary);
	}

	/**
	 * Deletes an entry from the CalDAV server
	 *
	 * @param	string	$vevent_id	vEvent id on the CalDAV server
	 * @return	boolean
	 */
	public function delete($vevent_id)
	{
		$result = $this->query($this->url . $vevent_id . '.ics', 'DELETE');

		switch ($result['http_code'])
		{
			case 204:
				return true;
			break;

			default:
				throw new Exception('Woops, something\'s gone wrong! The CalDAV server returned the http status code ' . $result['http_code'] . '.', self::EXCEPTION_WRONG_HTTP_STATUS_CODE_DELETE);
			break;
		}
	}

	/**
	 * Adds an entry to the CalDAV server
	 *
	 * @param	string	$vevent		vEvent
	 * @param	string	$vevent_id	vEvent id on the CalDAV server
	 * @return	string				The new vEvent id
	 */
	public function add($vevent, $uid = null, $isUpdate = false)
	{
		if ($uid === null)
		{
			$uid	= $this->generate_vevent_id();
		}
		$vevent		= $this->clean_vevent($vevent);
		$vevent		= $this->force_uid($vevent, $uid);

        /*
        $ev = explode("\r\n", $vevent);
        echo "<br><hr><br>";
        $i = 1;
        foreach (explode("\n", str_replace("\r\n", "\n", $vevent)) as $line)
        {
            echo sprintf("%02d) %s<br>",$i++, $line);  
        }
        */

		$result		= $this->query($this->url . $uid . '.ics', 'PUT', $vevent, 'text/calendar');

		switch($result['http_code'])
		{
			case 201:
			case 204:
				return $uid;
			    break;
            case 200:
                if ($isUpdate)
                {
                    return $uid;
			        break;
                }
			default:
				throw new Exception('Woops, something\'s gone wrong! The CalDAV server returned the http status code ' . $result['http_code'] . '.', self::EXCEPTION_WRONG_HTTP_STATUS_CODE_ADD);
			break;
		}
	}

	/**
	 * Updates an entry to the CalDAV server
	 *
	 * @param	string	$vevent		vEvent
	 * @param	string	$vevent_id	vEvent id on the CalDAV server
	 * @return	boolean
	 */
	public function update($vevent, $vevent_id)
	{
		try
		{
			return $this->add($vevent, $vevent_id, true);
		}
		catch (Exception $e)
		{
			throw new Exception($e->getMessage(), self::EXCEPTION_WRONG_HTTP_STATUS_CODE_UPDATE);
		}
	}

	/**
	 * Curl initialization
	 *
	 * @return void
	 */
	public function curl_init()
	{
		if (empty($this->curl))
		{
			$this->curl = curl_init();
			curl_setopt($this->curl, CURLOPT_HEADER, true);
			curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->curl, CURLOPT_USERAGENT, self::USERAGENT.self::VERSION);

			if ($this->auth !== null)
			{
				curl_setopt($this->curl, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
				curl_setopt($this->curl, CURLOPT_USERPWD, $this->auth);
			}
			
			/* allow to follow redirects if activated */
			curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, $this->follow_redirects);
			if ($this->follow_redirects)
			{
				curl_setopt($this->curl, CURLOPT_MAXREDIRS, $this->follow_redirects_count);
			}
			
		}
	}

	/**
	 * Query the CalDAV server via curl and returns the response
	 *
	 * @param	string	$url				CalDAV server URL
	 * @param	string	$method				HTTP method like (OPTIONS, GET, HEAD, POST, PUT, DELETE, TRACE, COPY, MOVE)
	 * @param	string	$content			Content for CalDAV queries
	 * @param	string	$content_type		Set content type
	 * @return	array						Raw CalDAV Response and http status code
	 */
	private function query($url, $method, $content = null, $content_type = null)
	{
		$this->curl_init();

		curl_setopt($this->curl, CURLOPT_URL, $url);
		curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);

		if ($content !== null)
		{
			curl_setopt($this->curl, CURLOPT_POST, true);
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $content);
		}
		else
		{
			curl_setopt($this->curl, CURLOPT_POST, false);
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, null);
		}

		if ($content_type !== null)
		{
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-type: '.$content_type));
		}
		else
		{
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, array());
		}
		
		$complete_response	= curl_exec($this->curl);
		$header_size		= curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
		$http_code 			= curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
		$header				= trim(substr($complete_response, 0, $header_size));
		$response			= substr($complete_response, $header_size);

		$return = array(
			'response'		=> $response,
			'http_code'		=> $http_code
		);

		if ($this->debug === true)
		{
			$debug = $return;
			$debug['url']			= $url;
			$debug['method']		= $method;
			$debug['content']		= $content;
			$debug['content_type']	= $content_type;
			$debug['header']		= $header;
			$this->set_debug($debug);
		}

		return $return;
	}

	/**
	 * Returns a valid and unused vEvent id
	 *
	 * @return	string	$vevent_id	Valid vEvent id
	 */
	private function generate_vevent_id()
	{
		$vevent_id = null;

		for ($number = 0; $number <= 25; $number ++)
		{
			if ($number == 8 || $number == 17)
			{
				$vevent_id .= '-';
			}
			else
			{
				$vevent_id .= $this->vevent_id_chars[mt_rand(0, (count($this->vevent_id_chars) - 1))];
			}
		}

		try
		{
			$caldav = new CalDavClient($this->url);
			$caldav->set_auth($this->username, $this->password);

			$result = $caldav->query($this->url . $vevent_id . '.ics', 'GET');

			if ($result['http_code'] !== 404)
			{
				$vcard_id = $this->generate_vevent_id();
			}

			return $vevent_id;
		}
		catch (Exception $e)
		{
			throw new Exception($e->getMessage(), self::EXCEPTION_COULD_NOT_GENERATE_NEW_VEVENT_ID);
		}
	}

	/**
	 * Destructor
	 * Close curl connection if it's open
	 *
	 * @return	void
	 */
	public function __destruct()
	{
		if (!empty($this->curl))
		{
			curl_close($this->curl);
		}
	}
}
