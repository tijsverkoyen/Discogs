<?php
/**
 * Discogs class
 *
 * This source file can be used to communicate with Discogs (http://discogs.com)
 *
 * The class is documented in the file itself. If you find any bugs help me out and report them. Reporting can be done by sending an email to php-discogs-bugs[at]verkoyen[dot]eu.
 * If you report a bug, make sure you give me enough information (include your code).
 *
 * License
 * Copyright (c) 2009, Tijs Verkoyen. All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products derived from this software without specific prior written permission.
 *
 * This software is provided by the author "as is" and any express or implied warranties, including, but not limited to, the implied warranties of merchantability and fitness for a particular purpose are disclaimed. In no event shall the author be liable for any direct, indirect, incidental, special, exemplary, or consequential damages (including, but not limited to, procurement of substitute goods or services; loss of use, data, or profits; or business interruption) however caused and on any theory of liability, whether in contract, strict liability, or tort (including negligence or otherwise) arising in any way out of the use of this software, even if advised of the possibility of such damage.
 *
 * @author			Tijs Verkoyen <php-discogs@verkoyen.eu>
 * @version			1.0.0
 *
 * @copyright		Copyright (c) 2008, Tijs Verkoyen. All rights reserved.
 * @license			BSD License
 */
class Discogs
{
	// internal constant to enable/disable debugging
	const DEBUG = false;

	// url for the api
	const API_URL = 'http://discogs.com';

	// port for the API
	const API_PORT = 80;

	// current version
	const VERSION = '1.0.0';


	/**
	 * The API-key that will be used for authenticating
	 *
	 * @var	string
	 */
	private $apiKey;


	/**
	 * The timeout
	 *
	 * @var	int
	 */
	private $timeOut = 60;


	/**
	 * The user agent
	 *
	 * @var	string
	 */
	private $userAgent;


// class methods
	/**
	 * Default constructor
	 *
	 * @return	void
	 * @param	string $apiKey	The API-key that has to be used for authenticating
	 */
	public function __construct($apiKey)
	{
		$this->setApiKey($apiKey);
	}


	/**
	 * Make the call
	 *
	 * @return	string
	 * @param	string $url
	 * @param	array[optional] $parameters
	 */
	private function doCall($url, $parameters = array())
	{
		// redefine
		$url = (string) $url;
		$parameters = (array) $parameters;

		// add required parameters
		$parameters['f'] = 'xml';
		$parameters['api_key'] = $this->getApiKey();

		// init var
		$queryString = '';

		// loop parameters and add them to the queryString
		foreach($parameters as $key => $value) $queryString .= '&'. $key .'='. urlencode(utf8_encode($value));

		// cleanup querystring
		$queryString = trim($queryString, '&');

		// append to url
		$url .= '?'. $queryString;

		// prepend
		$url = self::API_URL .'/'. $url;

		// set options
		$options[CURLOPT_URL] = $url;
		$options[CURLOPT_PORT] = self::API_PORT;
		$options[CURLOPT_USERAGENT] = $this->getUserAgent();
		$options[CURLOPT_FOLLOWLOCATION] = true;
		$options[CURLOPT_RETURNTRANSFER] = true;
		$options[CURLOPT_TIMEOUT] = (int) $this->getTimeOut();
		$options[CURLOPT_ENCODING] = 'gzip';

		// init
		$curl = curl_init();

		// set options
		curl_setopt_array($curl, $options);

		// execute
		$response = curl_exec($curl);
		$headers = curl_getinfo($curl);

		// fetch errors
		$errorNumber = curl_errno($curl);
		$errorMessage = curl_error($curl);

		// close
		curl_close($curl);

		// invalid headers
		if(!in_array($headers['http_code'], array(0, 200)))
		{
			// should we provide debug information
			if(self::DEBUG)
			{
				// make it output proper
				echo '<pre>';

				// dump the header-information
				var_dump($headers);

				// dump the raw response
				var_dump($response);

				// end proper format
				echo '</pre>';

				// stop the script
				exit;
			}

			// load response as XML
			$xml = @simplexml_load_string($response, null, LIBXML_NOCDATA);

			// errormessage?
			if($xml !== false && isset($xml->error)) throw new DiscogsException(utf8_decode((string) $xml->error), (int) $headers['http_code']);

			// fallback
			throw new DiscogsException('Invalid headers ('. $headers['http_code'] .')', (int) $headers['http_code']);
		}

		// error?
		if($errorNumber != '') throw new DiscogsException($errorMessage, $errorNumber);

		// load as XML
		$xml = @simplexml_load_string($response, null, LIBXML_NOCDATA);

		// validate XML
		if($xml === false)
		{
			// should we provide debug information
			if(self::DEBUG)
			{
				// make it output proper
				echo '<pre>';

				// dump the header-information
				var_dump($headers);

				// dump the raw response
				var_dump($response);

				// end proper format
				echo '</pre>';

				// stop the script
				exit;
			}

			throw new DiscogsException('Invalid XML');
		}

		// return
		return $xml;
	}


	/**
	 * Get the API-key
	 *
	 * @return	string
	 */
	private function getApiKey()
	{
		return (string) $this->apiKey;
	}


	/**
	 * Get the timeout that will be used
	 *
	 * @return	int
	 */
	public function getTimeOut()
	{
		return (int) $this->timeOut;
	}


	/**
	 * Get the useragent that will be used. Our version will be prepended to yours.
	 * It will look like: "PHP Discogs/<version> <your-user-agent>"
	 *
	 * @return	string
	 */
	public function getUserAgent()
	{
		return (string) 'PHP Discogs/'. self::VERSION .' '. $this->userAgent;
	}


	/**
	 * Set the login that has to be used
	 *
	 * @return	void
	 * @param	string $apiKey
	 */
	private function setApiKey($apiKey)
	{
		$this->apiKey = (string) $apiKey;
	}


	/**
	 * Set the timeout
	 * After this time the request will stop. You should handle any errors triggered by this.
	 *
	 * @return	void
	 * @param	int $seconds	The timeout in seconds
	 */
	public function setTimeOut($seconds)
	{
		$this->timeOut = (int) $seconds;
	}


	/**
	 * Set the user-agent for you application
	 * It will be appended to ours, the result will look like: "PHP Discogs/<version> <your-user-agent>"
	 *
	 * @return	void
	 * @param	string $userAgent	Your user-agent, it should look like <app-name>/<app-version>
	 */
	public function setUserAgent($userAgent)
	{
		$this->userAgent = (string) $userAgent;
	}


// release methods
	/**
	 * Get more information about a release
	 *
	 * @return	array
	 * @param	string $releaseId
	 */
	public function getRelease($releaseId)
	{
		// redefine
		$releaseId = (string) $releaseId;

		// make the call
		$response = $this->doCall('release/'. urlencode($releaseId));

		// validate response
		if(!isset($response->release)) throw new DiscogsException('Invalid XML.');

		// init var
		$return = array();

		// general properties
		$return['id'] = (string) $response->release['id'];
		$return['status'] = (string) $response->release['status'];
		$return['title'] = utf8_decode((string) $response->release->title);
		$return['genre'] = utf8_decode((string) $response->genres->genre);
		$return['country'] = utf8_decode((string) $response->country);
		$return['released'] = utf8_decode((string) $response->released);
		$return['notes'] = utf8_decode((string) $response->notes);

		// images
		$return['images'] = array();

		// loop images
		foreach($response->release->images->image as $image)
		{
			// init var
			$imageProperties = array();

			// get properties
			$imageProperties['type'] = (string) $image['type'];
			$imageProperties['width'] = (int) $image['width'];
			$imageProperties['height'] = (int) $image['height'];
			$imageProperties['url'] = (string) $image['uri'];
			$imageProperties['thumb_url'] = (string) $image['uri150'];

			// add
			$return['images'][] = $imageProperties;
		}

		// artists
		$return['artists'] = array();

		// loop artists
		foreach($response->release->artists->artist as $artist)
		{
			// init var
			$artistProperties = array();

			// get properties
			$artistProperties['name'] = utf8_decode((string) $artist->name);

			// add
			$return['artists'][] = $artistProperties;
		}

		// extra artists
		$return['extra_artists'] = array();

		// loop artists
		foreach($response->release->extraartists->artist as $artist)
		{
			// init var
			$artistProperties = array();

			// get properties
			$artistProperties['name'] = utf8_decode((string) $artist->name);
			$artistProperties['role'] = utf8_decode((string) $artist->role);

			// add
			$return['extra_artists'][] = $artistProperties;
		}

		// labels
		$return['labels'] = array();

		// loop labels
		foreach($response->release->labels->label as $label)
		{
			// init var
			$labelProperties = array();

			// get properties
			$labelProperties['name'] = utf8_decode((string) $label['name']);
			$labelProperties['catno'] = (string) $label['catno'];

			// add
			$return['labels'][] = $labelProperties;
		}

		// formats
		$return['formats'] = array();

		// loop format
		foreach($response->release->formats->format as $format)
		{
			// init var
			$formatProperties = array();

			// get properties
			$formatProperties['name'] = utf8_decode((string) $format['name']);
			$formatProperties['qty'] = (string) $format['qty'];
			$formatProperties['description'] = utf8_decode((string) $format->descriptions->description);

			// add
			$return['formats'][] = $formatProperties;
		}

		// styles
		$return['styles'] = array();

		// loop styles
		foreach($response->release->styles->style as $style) $return['styles'][] = utf8_decode((string) $style);

		// tracks
		$return['tracklist'] = array();

		// loop tracks
		foreach($response->release->tracklist->track as $track)
		{
			// init var
			$trackProperties = array();

			// get properties
			$trackProperties['position'] = (string) $track->position;
			$trackProperties['title'] = utf8_decode((string) $track->title);
			$trackProperties['duration'] = (string) $track->duration;

			if(isset($track->extraartists->artist))
			{
				$trackProperties['extra_artists'] = array();

				// extra artists
				foreach($track->extraartists->artist as $artist)
				{
					// init var
					$artistProperties = array();

					// get properties
					$artistProperties['name'] = utf8_decode((string) $artist->name);
					$artistProperties['role'] = utf8_decode((string) $artist->role);

					// add
					$trackProperties['extra_artists'][] = $artistProperties;
				}
			}

			// add
			$return['tracks'][] = $trackProperties;
		}

		// return
		return $return;
	}


// artist methods
	/**
	 * Get more information about an artist
	 *
	 * @return	array
	 * @param	string $name
	 */
	public function getArtist($name)
	{
		// redefine
		$name = (string) $name;

		// make the call
		$response = $this->doCall('artist/'. urlencode($name));

		// validate
		if(!isset($response->artist)) throw new DiscogsException('Invalid XML.');

		// init var
		$return = array();

		// general properties
		$return['name'] = utf8_decode((string) $response->artist->name);
		$return['real_name'] = utf8_decode((string) $response->artist->realname);

		// urls
		if(isset($response->artist->urls->url))
		{
			$return['urls'] = array();

			// loop urls
			foreach($response->artist->urls->url as $url)
			{
				$url = trim((string) $url);

				if($url != '') $return['urls'][] = $url;
			}
		}

		// name variations
		if(isset($response->artist->namevariations->name))
		{
			$return['name_variations'] = array();

			// loop urls
			foreach($response->artist->namevariations->name as $name) $return['name_variations'][] = utf8_decode((string) $name);
		}

		// aliases
		if(isset($response->artist->aliases->name))
		{
			$return['aliases'] = array();

			// loop urls
			foreach($response->artist->aliases->name as $name) $return['aliases'][] = utf8_decode((string) $name);
		}

		// images
		if(isset($response->artist->images->image))
		{
			$return['images'] = array();

			// loop images
			foreach($response->artist->images->image as $image)
			{
				// init var
				$imageProperties = array();

				// get properties
				$imageProperties['type'] = (string) $image['type'];
				$imageProperties['width'] = (int) $image['width'];
				$imageProperties['height'] = (int) $image['height'];
				$imageProperties['url'] = (string) $image['uri'];
				$imageProperties['thumb_url'] = (string) $image['uri150'];

				// add
				$return['images'][] = $imageProperties;
			}
		}

		// releases
		if(isset($response->artist->releases->release))
		{
			$return['releases'] = array();

			// loop releases
			foreach($response->artist->releases->release as $release)
			{
				// init var
				$releaseProperties = array();

				// get properties
				$releaseProperties['id'] = (string) $release['id'];
				$releaseProperties['status'] = (string) $release['status'];
				$releaseProperties['type'] = (string) $release['type'];
				$releaseProperties['title'] = utf8_decode((string) $release->title);
				$releaseProperties['format'] = utf8_decode((string) $release->format);
				$releaseProperties['label'] = utf8_decode((string) $release->label);
				$releaseProperties['year'] = utf8_decode((string) $release->year);

				// add
				$return['releases'][] = $releaseProperties;
			}
		}

		// return
		return $return;
	}


// label methods
	/**
	 * Get more information about a label
	 *
	 * @return	array
	 * @param	string $name
	 */
	public function getLabel($name)
	{
		// redefine
		$name = (string) $name;

		// make the call
		$response = $this->doCall('label/'. urlencode($name));

		// validate
		if(!isset($response->label)) throw new DiscogsException('Invalid XML.');

		// init var
		$return = array();

		// general properties
		$return['name'] = utf8_decode((string) $response->label->name);
		$return['profile'] = utf8_decode((string) $response->label->profile);
		$return['contact_info'] = utf8_decode((string) $response->label->contactinfo);

		// parent label
		if(isset($response->label->parentLabel)) $return['parent_label'] = utf8_decode((string) $response->label->parentLabel);

		// sublabels
		if(isset($response->label->sublabels->label))
		{
			$return['sublabels'] = array();

			// loop sublabels
			foreach($response->label->sublabels->label as $label) $return['sublabels'][] = utf8_decode((string) $label);
		}

		// images
		if(isset($response->label->images->image))
		{
			$return['images'] = array();

			// loop images
			foreach($response->label->images->image as $image)
			{
				// init var
				$imageProperties = array();

				// get properties
				$imageProperties['type'] = (string) $image['type'];
				$imageProperties['width'] = (int) $image['width'];
				$imageProperties['height'] = (int) $image['height'];
				$imageProperties['url'] = (string) $image['uri'];
				$imageProperties['thumb_url'] = (string) $image['uri150'];

				// add
				$return['images'][] = $imageProperties;
			}
		}

		// releases
		if(isset($response->label->releases->release))
		{
			$return['releases'] = array();

			// loop releases
			foreach($response->label->releases->release as $release)
			{
				// init var
				$releaseProperties = array();

				// get properties
				$releaseProperties['id'] = (string) $release['id'];
				$releaseProperties['status'] = (string) $release['status'];
				$releaseProperties['catno'] = utf8_decode((string) $release->catno);
				$releaseProperties['artist'] = utf8_decode((string) $release->artist);
				$releaseProperties['title'] = utf8_decode((string) $release->title);
				$releaseProperties['format'] = utf8_decode((string) $release->format);

				// add
				$return['releases'][] = $releaseProperties;
			}
		}

		// return
		return $return;
	}


// search methods
	/**
	 * Search something on Discogs
	 *
	 * @return	array
	 * @param	string $term
	 * @param	string[optional] $type
	 * @param	int[optional] $page
	 */
	public function search($term, $type = 'all', $page = 1)
	{
		// redefine
		$term = (string) $term;
		$type = (string) $type;
		$page = (int) $page;

		// build parameters
		$parameters['type'] = $type;
		$parameters['q'] = $term;
		if($page > 1) $parameters['page'] = $page;

		// make the call
		$response = $this->doCall('search', $parameters);

		// init var
		$return = array();
		$return['exact_results'] = array();
		$return['search_results'] = array();

		if(isset($response->exactresults->result))
		{
			foreach($response->exactresults->result as $result)
			{
				// init var
				$row = array();

				// get data
				$row['title'] = utf8_decode((string) $result->title);
				$row['url'] = (string) $result->uri;
				$row['type'] = (string) $result['type'];

				if($row['type'] == 'artist')
				{
					// split url
					$chunks = (array) explode('artist/', $row['url']);

					if(isset($chunks[1])) $row['id'] = urldecode($chunks[1]);
				}

				elseif($row['type'] == 'label')
				{
					// split url
					$chunks = (array) explode('label/', $row['url']);

					if(isset($chunks[1])) $row['id'] = urldecode($chunks[1]);
				}

				elseif($row['type'] == 'release')
				{
					// split url
					$chunks = (array) explode('release/', $row['url']);

					if(isset($chunks[1])) $row['id'] = urldecode($chunks[1]);
				}

				// add
				$return['exact_results'][] = $row;
			}
		}

		if(isset($response->searchresults->result))
		{
			foreach($response->searchresults->result as $result)
			{
				// init var
				$row = array();

				// get data
				$row['title'] = utf8_decode((string) $result->title);
				$row['url'] = (string) $result->uri;
				$row['summary'] = utf8_decode((string) $result->summary);
				$row['type'] = (string) $result['type'];

				if($row['type'] == 'artist')
				{
					// split url
					$chunks = (array) explode('artist/', $row['url']);

					if(isset($chunks[1])) $row['id'] = urldecode($chunks[1]);
				}

				elseif($row['type'] == 'label')
				{
					// split url
					$chunks = (array) explode('label/', $row['url']);

					if(isset($chunks[1])) $row['id'] = urldecode($chunks[1]);
				}

				elseif($row['type'] == 'release')
				{
					// split url
					$chunks = (array) explode('release/', $row['url']);

					if(isset($chunks[1])) $row['id'] = urldecode($chunks[1]);
				}

				// add
				$return['search_results'][] = $row;
			}
		}

		// return
		return $return;
	}

}


/**
 * Discogs Exception class
 *
 * @author	Tijs Verkoyen <php-discogs@verkoyen.eu>
 */
class DiscogsException extends Exception
{
}

?>