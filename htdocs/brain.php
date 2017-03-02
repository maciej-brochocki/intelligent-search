<?php
require_once 'LdConnect.php';
require_once 'settings.php'; 

class Brain
{
	protected $externalIp; //external IP of this PC

	public function __construct()
	{
		// Taken from: http://stackoverflow.com/questions/7909362/how-do-i-get-the-external-ip-of-my-server-using-php
		$this->externalIp = file_get_contents('http://phihag.de/ip/');
		if ($this->externalIp === FALSE)
		{
			die('Error: can\'t determine external IP\n');
		}
		echo "Current IP: $this->externalIp\n";
	}
	
	public function attentionProcess()
	{
		$socket = stream_socket_server('tcp://localhost:8000', $errno, $errstr);
		if ($socket === FALSE)
		{
			die("Error: can't open socket $errstr ($errno)\n");
		}
		else
		{
			while ($conn = stream_socket_accept($socket, -1))
			{
				$worker = new inputThread($conn, $this->externalIp);
				$worker->run(); //Globals and static class members don't work in threads, what a crap!
/*				if (!$worker->start())
				{
					echo 'Error: can\'t start new thread\n';
				}*/
			}
			fclose($socket);
		}
	}
}


abstract class Status
{
	const FidoAsked = 0;
	const QuestionParsed = 1;
	const QuestionAnalysed = 2;
	const GoogleAsked = 3;
	const Error = 99;
}
abstract class MessageField
{
	const Type = 1;
	const Id = 2;
	const Data = 3;
}
abstract class MessageType
{
	const Accept = 'a';
	const ReturnedCallback = 'r';
	const Polling = 'p';
	const Done = 'd';
}

class inputThread extends Thread
{
	protected $conn; //TCP connection to handle
	protected $externalIp; //external IP of this PC
	//variables used by inputThread class to store incoming requests' state
	protected static $lastRequestId = 1;
	protected static $requests = array();
		//Query - asked question
		//Id - id
		//Status - status
		//Error - error message
		//ParsedQuery - asked question parsed by Fido
		//Question - analysed question in usable format
		//Google - array with google results
	
	public function __construct($conn, $externalIp)
	{
		$this->conn = $conn;
		$this->externalIp = $externalIp;
	}
	
	public function run()
	{
		$msg = '';
		while (!feof($this->conn))
		{
			$part = fread($this->conn, 1024);
			if ($part === FALSE)
			{
				break;
			}
			$msg = $msg . $part;
			$tokens = explode('|', $msg);

			if (sizeof($tokens)==5)
			{
				switch($tokens[MessageField::Type])
				{
					case MessageType::Accept:
						if (fwrite($this->conn, '|' . MessageType::Accept . '|' . self::$lastRequestId . '||\r\n') === FALSE)
						{
							fclose($this->conn);
							return;
						}
						echo "Accepted request " . self::$lastRequestId . "\r\n";
						
						self::$requests[self::$lastRequestId] = array(
							'Query' => $tokens[MessageField::Data],
							'Id' => askFido($this->externalIp, $tokens[MessageField::Data]),
							'Status' => Status::FidoAsked,
						);
						if (self::$requests[self::$lastRequestId]['Id'] === FALSE)
						{
							self::$requests[self::$lastRequestId]['Status'] = Status::Error;
							self::$requests[self::$lastRequestId]['Error'] = 'Error: Fido server problem';
						}
						self::$lastRequestId++;
						
						break;
					case MessageType::ReturnedCallback:
						$parsedQuery = json_decode($tokens[MessageField::Data], true);
						if (($parsedQuery !== NULL) && isset($parsedQuery['Id']))
						{
							foreach (self::$requests as $key => $val)
							{
								if (self::$requests[$key]['Id'] == $parsedQuery['Id'])
								{

									self::$requests[$key]['ParsedQuery'] = $parsedQuery['Result'];
									self::$requests[$key]['Status'] = Status::QuestionParsed;

									echo "Fido processed request $key\r\n";
									break;
								}
							}
						}
						break;
					case MessageType::Polling:
						$id = $tokens[MessageField::Id];
						if (isset(self::$requests[$id]))
						{
							switch (self::$requests[$id]['Status'])
							{
								case Status::FidoAsked:
									fwrite($this->conn, '|' . MessageType::Polling . '|' . $id . '|' . "Waiting for Fido server..." . '|\r\n');
									break;
								case Status::QuestionParsed:
									self::$requests[$id]['Question'] = parseQuestion(self::$requests[$id]['ParsedQuery']);
									if (self::$requests[$id]['Question'] === FALSE)
									{
										self::$requests[$id]['Status'] = Status::Error;
										self::$requests[$id]['Error'] = 'Error: query not recognized as valid question';
									}
									else
									{
										self::$requests[$id]['Status'] = Status::QuestionAnalysed;
									}
									fwrite($this->conn, '|' . MessageType::Polling . '|' . $id . '|' . "Fido server replied..." . '|\r\n');
									break;
								case Status::QuestionAnalysed:
									self::$requests[$id]['Google'] = askGoogle(buildGoogleQuery(self::$requests[$id]['Question']));
									if (self::$requests[$id]['Google'] === FALSE)
									{
										self::$requests[$id]['Status'] = Status::Error;
										self::$requests[$id]['Error'] = 'Error: Google server problem';
									}
									else if (sizeof(self::$requests[$id]['Google']) == 0)
									{
										self::$requests[$id]['Status'] = Status::Error;
										self::$requests[$id]['Error'] = 'Error: No results found by Google';
									}
									else
									{
										self::$requests[$id]['Status'] = Status::GoogleAsked;
									}
									fwrite($this->conn, '|' . MessageType::Polling . '|' . $id . '|' . "Question analysed..." . '|\r\n');
									break;
								case Status::GoogleAsked:
									fwrite($this->conn, '|' . MessageType::Done . '|' . $id . '|' . json_encode(self::$requests[$id]['Google']) . '|\r\n');
									echo "Successfully polled request $id\r\n";
									unset(self::$requests[$id]);
									break;
								case Status::Error:
									fwrite($this->conn, '|' . MessageType::Done . '|' . $id . '|' . self::$requests[$id]['Error'] . '|\r\n');
									echo "Request $id ended with error\r\n";
									unset(self::$requests[$id]);
									break;
							}
						}
						else
						{
							fwrite($this->conn, '|' . MessageType::Done . '|' . $id . '|Error: no such request|\r\n');
						}
						break;
				}
				break; //from while
			}
		}
		fclose($this->conn);
	}
}

function askFido($externalIp, $text)
{
	try
	{
		//set authorization data:
		$api = new LdConnect(FIDO_USERNAME, md5(FIDO_PASSWORD));

		//using realtime-api:
		$api->hasCallback(true);

		//set & verify callback adress (if not set):
		$api->setCallback('http://' . $externalIp . '/callback.php');

		$api->setText($text);

		//generate token:
		$api->logIn(); 

		$result = $api->getFullObject('array');
		
		if (isset($result['Result']['Id']))
		{
			return $result['Result']['Id'];
		}
		else
		{
			print_r($result);
			return false;
		}
	}
	catch (Exception $error)
	{
		//print error message:
		print $error->getMessage();
		return false;
	}
}

function fixForAttribute($word)
{
	$result = array();

	switch ($word)
	{
		case 'how':
		case 'how come':
			$result['phrase_category'] = 'process';
			$result['phrase_aspect'] = 'assistant/tool';
			break;
		case 'where':
			$result['phrase_category'] = 'place';
			$result['phrase_aspect'] = 'position/status';
			break;
		case 'when':
			$result['phrase_category'] = 'time';
			$result['phrase_aspect'] = 'position/status';
			break;
		case 'why':
			$result['phrase_category'] = 'process';
			$result['phrase_aspect'] = 'purpose/reason';
			break;
		case 'what':
		case 'who':
		case 'whom':
		case 'whose':
		case 'which':
		default:
			echo "Interrogative $word in attribute, wtf!?\r\n";
			break;
	}
	return $result;
}

function determineQuestionType($type, $category, $aspect, $word)
{
	$result = array();

	switch ($type)
	{
		case 'subject':
		case 'object':
		case 'complement':
			$result['question_type'] = $type;
			break;
		case 'attribute':
			$result['question_type'] = $type;
			$result['phrase_category'] = $category;
			$result['phrase_aspect'] = $aspect;
																					
			// Fix for sole WH-words
			if (($result['phrase_category'] == '') && ($result['phrase_aspect'] == ''))
			{
				$result = array_merge($result, fixForAttribute($word));
			}
			break;
		// Phrase
		case 'predicate':
		case 'preposition':
		case 'interrogator':
		case 'connector':
		case 'irrelevant':
		// Clause
		case 'node':
		case 'question tag':
		case 'addition to remarks':
		default:
			echo "Interrogative " . $word . " in type " . $type . ", wtf!?\r\n";
			break;
	}
	return $result;
}

function determineSentenceParts($clause)
{
	$result = array();

	foreach ($clause['phrases'] as $phraseKey => $phrase)
	{
		switch ($phrase['phrase_type'])
		{
			case 'predicate':
			case 'subject':
			case 'object':
				$result[$phrase['phrase_type']] = $phrase['phrase_core_base'];
				break;
		}
	} //phrase
	return $result;
}

//result:
// question_type: yes_no/subject/object/complement/attribute
// phrase_category
// phrase_aspect
// predicate
// subject
// object
// positive
// passive
// clause_tense
function parseQuestion($data)
{
	foreach ($data['sentences'] as $sentenceKey => $sentence)
	{
		$result = array();
		foreach ($sentence['clauses'] as $clauseKey => $clause)
		{
			foreach ($clause['phrases'] as $phraseKey => $phrase)
			{
				foreach ($phrase['words'] as $wordKey => $word)
				{
					// Find interrogative to determine what the question is asking about
					if ($word['word_component'] == 'interrogative')
					{
						if ($clause['clause_type'] == 'main')
						{
							// Simple sentence
							$result = array_merge($result, determineQuestionType($phrase['phrase_type'], $phrase['phrase_category'], $phrase['phrase_aspect'], $word['word_lex']));
						}
						else
						{
							// Complex sentence
							$result = array_merge($result, determineQuestionType($clause['clause_type'], $phrase['phrase_category'], $phrase['phrase_aspect'], $word['word_lex']));
						}
					}
				} //word
			} //phrase
			
			// Check if this is really a question
			if (($clause['clause_type'] == 'main') && ((strpos($clause['clause_construction'], 'question') !== false) || (strpos($clause['clause_construction'], 'information_request') !== false)))
			{
				$result['valid'] = true;
				$result['positive'] = (strpos($clause['clause_construction'], 'positive') !== false);
				$result['passive'] = (strpos($clause['clause_construction'], 'passive') !== false);
				$result['clause_tense'] = $clause['clause_tense'];
				$result = array_merge($result, determineSentenceParts($clause));
			}
		} //clause

		if (isset($result['valid']))
		{
			if (!isset($result['question_type']))
			{
				// Binary question
				$result['question_type'] = 'yes_no';
			}
			else if (isset($result[$result['question_type']]))
			{
				// Unset what is asked for
				unset($result[$result['question_type']]);
			}
			unset($result['valid']);
			return $result;
		}
	} //sentence
	
	return false;
}

function buildGoogleQuery($question)
{
	$result = "";
	if (isset($question['predicate']))
	{
		$result = $result . " " . $question['predicate'];
	}
	if (isset($question['subject']))
	{
		$result = $result . " " . $question['subject'];
	}
	if (isset($question['object']))
	{
		$result = $result . " " . $question['object'];
	}
	return ltrim($result);
}

function askGoogle($query)
{
	$url = "https://ajax.googleapis.com/ajax/services/search/web?v=1.0&q=" . urlencode($query);

	// sendRequest
	$ch = curl_init();
	if ($ch === FALSE)
	{
		return FALSE;
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_SSLVERSION, 3);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	$body = curl_exec($ch);
	if ($body === FALSE)
	{
		return FALSE;
	}
	curl_close($ch);

	// now, process the JSON string
	$json = json_decode($body, true);
	if ($json === NULL)
	{
		return FALSE;
	}
	// now have some fun with the results...
	$result = array();
	foreach ($json['responseData']['results'] as $key => $val)
	{
		$result[$key] = $val['url'];
	}
	return $result;
}

$brain = new Brain();
$brain->attentionProcess();
?>