<?

if (!defined("PAUSED"))
	define("PAUSED", "Pausiert");
if (!defined("PLAY"))
	define("PLAY", "Spielt");
if (!defined("STOPPED"))
	define("STOPPED", "");
if (!defined("EPISODE"))
	define("EPISODE", "Serie");
if (!defined("MOVIE"))
	define("MOVIE", "Film");
if (!defined("PICTURE"))
	define("PICTURE", "Foto");
if (!defined("VOLUME"))
	define("VOLUME", "Lautstärke");
if (!defined("STATUS"))
	define("STATUS", "Status");
if (!defined("COVER"))
	define("COVER", "Cover");
if (!defined("TITLE"))
	define("TITLE", "Titel");
if (!defined("SONG"))
	define("SONG", "Song");

class PlexClient extends IPSModule
{

	public function Create()
	{
		parent::Create();

		// Public properties
		$this->RegisterPropertyString("client_uuid", "");
		$this->RegisterPropertyString("ClientIP", "");
		$this->RegisterPropertyInteger("ClientPort", 3005);
		$this->RegisterPropertyString("ClientMAC", "");
		$this->RegisterPropertyString("ServerIP", "");
		$this->RegisterPropertyInteger("ServerPort", 32400);
		$this->RegisterPropertyString("XPlexToken", "");

		// Private properties
		$this->RegisterPropertyInteger("ItemID", 0);
		$this->RegisterPropertyInteger("PageRptCount", 3);

		$this->RequireParent("{87DF895A-35D7-2801-43E8-1533E057F38A}"); // Plex IO
	}

	public function ApplyChanges()
	{
		parent::ApplyChanges();

		// Start create profiles
		$this->RegisterProfileIntegerEx("PLEX.Controls", "Move", "", "", Array(
			Array(-1, "Zurück", "", -1),
			Array(0, "Hoch", "", -1),
			Array(1, "Runter", "", -1),
			Array(2, "Links", "", -1),
			Array(3, "Rechts", "", -1),
			Array(4, "Seite +", "", -1),
			Array(5, "Seite -", "", -1),
			Array(10, "Auswahl", "", -1),
		));

		$this->RegisterProfileIntegerEx("PLEX.PlayerControls", "Script", "", "", Array(
			Array(0, "Prev", "", -1),
			Array(1, "Stop", "", 0xFF0000),
			Array(2, "Pause", "", 0xFFCC00),
			Array(3, "Play", "", 0x99CC00),
			Array(4, "Next", "", -1)
		));

		$this->RegisterProfileIntegerEx("PLEX.RepeatControls", "Repeat", "", "", Array(
			Array(0, "Aus", "", -1),
			Array(1, "Aktuelles", "", 0x99CC00),
			Array(2, "Alle", "", 0x99CC00)
		));

		$this->RegisterProfileBooleanEx("PLEX.ClientStatus", "Information", "", "", Array(
			Array(false, "Inaktiv", "", -1),
			Array(true, "Aktiv", "", -1)
		));

		$this->RegisterProfileBooleanEx("PLEX.ClientPower", "Power", "", "", Array(
			Array(false, "Ausschalten", "", 0xFF0000),
			Array(true, "Einschalten", "", 0x99CC00)
		));

		$this->RegisterProfileInteger("PLEX.Volume", "Speaker", "", " %", 0, 100, 1);


		// Create variables
		$playerID = $this->RegisterVariableInteger("PlayerID", "PlayerID");
		IPS_SetHidden($playerID, true);

		$client_metadata_ID = $this->RegisterVariableString("client_metadata", "Client Info");
		IPS_SetHidden($client_metadata_ID, true);

		$coverID = @$this->GetIDForIdent("Cover");
		if ($coverID != false && IPS_GetObject($coverID)['ObjectType'] != 5) { // migrate from variable to media
			if (IPS_DeleteVariable($coverID))
				$coverID = false;
		}
		if ($coverID == false) {
			$coverID = IPS_CreateMedia(1);
			IPS_SetIdent($coverID, "Cover");
			IPS_SetName($coverID, "Cover");
			IPS_SetIcon($coverID, "Image");
			IPS_SetParent($coverID, $this->InstanceID);
			IPS_SetMediaFile($coverID, "Transparent.png", false);
			IPS_SetMediaContent($coverID, "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HgAGgwJ/lK3Q6wAAAABJRU5ErkJggg==");
			IPS_SendMediaEvent($coverID);
		}

		$coverHTMLID = $this->RegisterVariableString("CoverHTML", "Cover HTML");
		IPS_SetVariableCustomProfile($coverHTMLID, "~HTMLBox");
		IPS_SetIcon($coverHTMLID, "Image");

		$HTMLID = $this->RegisterVariableString("HTML", "HTML");
		IPS_SetVariableCustomProfile($HTMLID, "~HTMLBox");

		$volumeID = $this->RegisterVariableInteger("Volume", "Lautstärke", "PLEX.Volume");
		SetValue($volumeID, 100);
		$this->EnableAction("Volume");

		$clientStatusID = $this->RegisterVariableBoolean("ClientStatus", "Client Status", "PLEX.ClientStatus");
		IPS_SetHidden($clientStatusID, true);

		$statusID = $this->RegisterVariableString("Status", "Status");
		IPS_SetIcon($statusID, "Information");

		$controlsID = $this->RegisterVariableInteger("Controls", "Steuerung", "PLEX.Controls");
		$this->EnableAction("Controls");
		SetValue($controlsID, -1);

		$titleID = $this->RegisterVariableString("Title", "Titel");
		IPS_SetIcon($titleID, "Information");

		$playerControlsID = $this->RegisterVariableInteger("PlayerControls", "Wiedergabe Steuerung", "PLEX.PlayerControls");
		SetValue($playerControlsID, 1);
		$this->EnableAction("PlayerControls");

		$repeatControlsID = $this->RegisterVariableInteger("RepeatControls", "Wiederholung", "PLEX.RepeatControls");
		$this->EnableAction("RepeatControls");

		$clientPowerID = $this->RegisterVariableBoolean("ClientPower", "Power", "PLEX.ClientPower");
		$this->EnableAction("ClientPower");
	}

	public function RequestAction($Ident, $Value)
	{
		switch ($Ident) {
			case "Volume": // volume
				if (IPS_GetProperty($this->GetParent(), "Open"))
					$this->SetVolume($Value);
				break;
			case "PlayerControls": // player control
				switch ($Value) {
					case 0: // prev
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Prev();
						break;
					case 1: // stop
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Stop();
						break;
					case 2: // pause
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Pause();
						break;
					case 3: // play
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Play();
						break;
					case 4: // next
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Next();
						break;
				}
				break;
			case "Controls": // movement player control
				switch ($Value) {
					case -1: // Back
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Back();
						break;
					case 0: // up
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Up();
						break;
					case 1: // down
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Down();
						break;
					case 2: // left
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Left();
						break;
					case 3: // right
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Right();
						break;
					case 4: // page up
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->PgUp();
						break;
					case 5: // page down
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->PgDown();
						break;
					case 10: // select
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->Select();
						break;
				}
				break;
			case "RepeatControls": // repeat
				switch ($Value) {
					case 0: // off
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->RepeatOff();
						break;
					case 1: // actual element
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->RepeatActualElement();
						break;
					case 2: // all
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->RepeatAll();
						break;
				}
				break;
			case "ClientPower": // power
				switch ($Value) {
					case false: // Shutdown the whole system
						if (IPS_GetProperty($this->GetParent(), "Open"))
							$this->PowerOff();
						break;
					case true: // Power on the system
						$this->PowerOn();
						break;
				}
				break;
		}
	}

	public function ReceiveData($JSONString)
	{
		$client_uuid = $this->ReadPropertyString("client_uuid");
		$payload = json_decode($JSONString);
		// $this->SendDebug("Plex IO Data", $JSONString, 0);
		$data = $payload->Buffer;
		$plex_json = json_encode($data);
		$this->SendDebug("Plex Webhook", $plex_json, 0);

		if (isset($data->event)) {
			$event = $data->event;
			$this->SendDebug("Plex Event", $event, 0);
			$user = $data->user;
			$this->SendDebug("Plex User", $user, 0);
			$owner = $data->owner;
			$this->SendDebug("Plex Owner", $owner, 0);
			$account = $data->Account;
			$this->SendDebug("Plex Account", $account, 0);
			$accountname = $account->title;
			$this->SendDebug("Plex Accountname", $accountname, 0);
			$accountpic = $account->thumb;
			$this->SendDebug("Plex Account Picture", $accountpic, 0);
			$server = $data->Server;
			$this->SendDebug("Plex Server", $server, 0);
			$servername = $server->title;
			$this->SendDebug("Plex Servername", $servername, 0);
			$serveruuid = $server->uuid;
			$this->SendDebug("Plex Server UUID", $serveruuid, 0);
			$player = $data->Player;
			$this->SendDebug("Plex Player", $player, 0);
			$playerlocal = $player->local;
			$this->SendDebug("Plex Player Local", $playerlocal, 0);
			$playerpublicip = $player->publicAddress;
			$this->SendDebug("Plex Player Public IP", $playerpublicip, 0);
			$plexclientname = $player->title;
			$this->SendDebug("Plex Client Name", $plexclientname, 0);
			$plexclientuuid = $player->uuid;
			$this->SendDebug("Plex Client UUID", $plexclientuuid, 0);
			if($client_uuid == $plexclientuuid)
			{
				switch ($event) {
					case 'media.pause':
						$this->SetValue("Status", PAUSED);
						$this->SetValue("PlayerControls", 2);
						break;
					case 'media.resume':
						$this->SetValue("Status", PLAY);
						$this->SetValue("PlayerControls", 3);
						break;
					case 'media.stop':
						$this->SetValue("Title", "");
						$this->SetValue("Status", STOPPED);
						$this->SetValue("PlayerControls", 1);
						IPS_SetMediaFile($this->GetIDForIdent("Cover"), "Transparent.png", false);
						IPS_SetMediaContent($this->GetIDForIdent("Cover"), "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HgAGgwJ/lK3Q6wAAAABJRU5ErkJggg==");
						IPS_SendMediaEvent($this->GetIDForIdent("Cover"));
						$this->SetValue("CoverHTML", "");

						// IPS_SetProperty($this->InstanceID, "ItemID", -1);
						//SetValue($this->GetIDForIdent("PlayerID"), 1);
						break;
					default:
						break;
				}
				// Get metadata
				$metadata = $data->Metadata;
				$metadata_json = json_encode($metadata);
				$this->SetValue("client_metadata", $metadata_json);
				$librarysectiontype = $metadata->librarySectionType;
				$this->SendDebug("Plex library section type", utf8_decode($librarysectiontype), 0);
				$ratingkey = $metadata->ratingKey;
				$this->SendDebug("Plex rating key", utf8_decode($ratingkey), 0);
				$ratingkeydata = $metadata->key;
				$this->SendDebug("Plex rating key data", utf8_decode($ratingkeydata), 0);
				$guid = $metadata->guid;
				$this->SendDebug("Plex guid", utf8_decode($guid), 0);
				$sectionid = $metadata->librarySectionID;
				$this->SendDebug("Plex section id", utf8_decode($sectionid), 0);
				$studio = $metadata->studio;
				$this->SendDebug("Plex studio", utf8_decode($studio), 0);
				$plextype = $metadata->type;
				$this->SendDebug("Plex type", utf8_decode($plextype), 0);
				$plextitle = $metadata->title;
				$this->SendDebug("Plex title", utf8_decode($plextitle), 0);
				$this->SetValue("Title", utf8_decode($plextitle));
				$plexoriginaltitle = $metadata->originalTitle;
				$this->SendDebug("Plex original title", utf8_decode($plexoriginaltitle), 0);
				$contentrating = $metadata->contentRating;
				$this->SendDebug("Plex content rating", utf8_decode($contentrating), 0);
				$summary = $metadata->summary;
				$this->SendDebug("Plex summary", utf8_decode($summary), 0);
				$rating = $metadata->rating;
				$this->SendDebug("Plex rating", utf8_decode($rating), 0);
				if(isset($metadata->viewOffset))
				{
					$viewoffset = $metadata->viewOffset;
					$this->SendDebug("Plex view offset", utf8_decode($viewoffset), 0);
				}
				if(isset($metadata->viewCount))
				{
					$viewcount = $metadata->viewCount;
					$this->SendDebug("Plex view count", utf8_decode($viewcount), 0);
				}
				if(isset($metadata->lastViewedAt))
				{
					$lastviewedat = $metadata->lastViewedAt;
					$this->SendDebug("Plex last view", utf8_decode($lastviewedat), 0);
				}
				if(isset($metadata->year))
				{
					$year = $metadata->year;
					$this->SendDebug("Plex year", utf8_decode($year), 0);
				}
				if(isset($metadata->tagline))
				{
					$tagline = $metadata->tagline;
					$this->SendDebug("Plex tagline", utf8_decode($tagline), 0);
				}
				if(isset($metadata->thumb))
				{
					$thumb = $metadata->thumb;
					$this->SendDebug("Plex thumb", utf8_decode($thumb), 0);
				}
				$art = $metadata->art;
				$this->SendDebug("Plex art", utf8_decode($art), 0);
				$duration = $metadata->duration;
				$this->SendDebug("Plex duration", utf8_decode($duration), 0);
				$originallyavailableat = $metadata->originallyAvailableAt;
				$this->SendDebug("Plex original available", utf8_decode($originallyavailableat), 0);
				$addedat = $metadata->addedAt;
				$this->SendDebug("Plex add date", utf8_decode($addedat), 0);
				$updatedat = $metadata->updatedAt;
				$this->SendDebug("Plex update date", utf8_decode($updatedat), 0);
				$chaptersource = $metadata->chapterSource;
				$this->SendDebug("Plex chapter source", utf8_decode($chaptersource), 0);
				$primaryextrakey = $metadata->primaryExtraKey;
				$this->SendDebug("Plex primary extra key", utf8_decode($primaryextrakey), 0);
				$genreinfo = $metadata->Genre;  // Array
				foreach ($genreinfo as $key => $genre) {
					$genre = $genre->tag;
					$this->SendDebug("Plex genre", utf8_decode($genre), 0);
				}
				$directorinfo = $metadata->Director;
				foreach ($directorinfo as $key => $director) {
					$director = $director->tag;
					$this->SendDebug("Plex director", utf8_decode($director), 0);
				}
				$writerinfo = $metadata->Writer;
				foreach ($writerinfo as $key => $writer) {
					$writer = $writer->tag;
					$this->SendDebug("Plex writer", utf8_decode($writer), 0);
				}
				$producerinfo = $metadata->Producer;
				foreach ($producerinfo as $key => $producer) {
					$producer = $producer->tag;
					$this->SendDebug("Plex producer", utf8_decode($producer), 0);
				}
				$countryinfo = $metadata->Country;
				foreach ($countryinfo as $key => $country) {
					$country = $country->tag;
					$this->SendDebug("Plex country", utf8_decode($country), 0);
				}
				$roleinfo = $metadata->Role;
				foreach ($roleinfo as $key => $cast) {
					$actor = $cast->tag;
					$this->SendDebug("Plex actor", utf8_decode($actor), 0);
					$role = $cast->role;
					$this->SendDebug("Plex actor role", utf8_decode($role), 0);
					if(isset($cast->thumb))
					{
						$actorpic = $cast->thumb;
						$this->SendDebug("Plex actor picture", utf8_decode($actorpic), 0);
					}
				}
			}
		}





		/*
		// react on incoming JSON
		if(isset($JSON->method)) {
			switch ($JSON->method) {
							case 'Player.OnPlay':
					SetValue($this->GetIDForIdent("Status"), PLAY);
					SetValue($this->GetIDForIdent("PlayerControls"), 3);

					if(isset($JSON->params->data->item)) {
						$item = $JSON->params->data->item;
						$player_id = 0;
						$item_id = $item->id;
						switch ($item->type) {
							case 'episode':
								$properties = '["title", "rating", "year", "genre", "duration", "thumbnail", "season", "episode", "plot", "cast", "showtitle", "streamdetails"]';
								$player_id = 1;
								break;
							case 'movie':
								$properties = '["title", "rating", "year", "genre", "duration", "thumbnail", "plot", "cast", "streamdetails"]';
								$player_id = 1;
								break;
							case 'picture':
								$properties = '["title", "year", "thumbnail"]';
								$player_id = 1;
								break;
							case 'song':
								$properties = '["title", "artist", "albumartist", "year", "genre", "album", "track", "duration", "thumbnail", "disc"]';
								$player_id = 0;
								break;
							default:
								$player_id = 0;
								break;
						}

						IPS_SetProperty($this->InstanceID, "ItemID", $item_id);
						SetValue($this->GetIDForIdent("PlayerID"), $player_id);
						if($player_id >= 0)
							$this->Send('{"jsonrpc":"2.0","method":"Player.GetItem","params":{"playerid":'.$player_id.', "properties": '.$properties.'},"id":1}');
					}
					break;

				case 'Application.OnVolumeChanged':
					SetValue($this->GetIDForIdent("Volume"), $JSON->params->data->volume);
					break;
				case 'Player.OnPropertyChanged':
					if(isset($JSON->params->data->property)) {
						if(isset($JSON->params->data->property->repeat)) {
							$value = $JSON->params->data->property->repeat;
							if($value == "off")
								$value = 0;
							else if($value == "one")
								$value = 1;
							else if($value == "all")
								$value = 2;
							SetValue($this->GetIDForIdent("RepeatControls"), $value);
						}
						// else if(isset($JSON->params->data->property->shuffled)) {
						//     $value = $JSON->params->data->property->shuffled;
						//     if($value == 0)
						//         $value = 0;
						//     else if($value == 1)
						//         $value = 1;
						//     SetValue($this->GetIDForIdent("ShuffleControls"), $value);
						// }
					}
					break;
				case 'System.OnQuit':
					SetValue($this->GetIDForIdent("Title"), "");
					SetValue($this->GetIDForIdent("Status"), STOPPED);
					SetValue($this->GetIDForIdent("ClientStatus"), false);
					SetValue($this->GetIDForIdent("ClientPower"), false);
					SetValue($this->GetIDForIdent("PlayerControls"), 1);

					IPS_SetMediaFile($this->GetIDForIdent("Cover"), "Transparent.png", false);
					IPS_SetMediaContent($this->GetIDForIdent("Cover"), "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HgAGgwJ/lK3Q6wAAAABJRU5ErkJggg==");
					IPS_SendMediaEvent($this->GetIDForIdent("Cover"));

					SetValue($this->GetIDForIdent("CoverHTML"), "");

					IPS_SetProperty($this->InstanceID, "ItemID", -1);
					SetValue($this->GetIDForIdent("PlayerID"), -1);
				default:
					break;
			}
		}
		if(isset($JSON->result)) {
			$result = $JSON->result;

			if(isset($result->item)) {
				$item = $result->item;
				$title = "";
				$player_id = $this->GetPlayerID();
				$item_id = @IPS_GetProperty($this->InstanceID, "ItemID");

				// titel zusammenbauen
				if(isset($item->artist) && count($item->artist) > 0) {
					$title = $item->artist[0]." - ";
				}
				if(isset($item->showtitle) && strlen($item->showtitle) > 0) {
					$title = utf8_decode($item->showtitle." - ");
				}
				if(isset($item->label)) {
					$title .= $item->label;
				}
				if(isset($item->album)) {
					$title .= " [".$item->album."]";
				}
				if(isset($item->season) && isset($item->episode)) {
					if($item->season > -1 && $item->episode > -1)
						$title .= " [S".$item->season."E".$item->episode."]";
				}

				SetValue($this->GetIDForIdent("Title"), $title);

				// cover
				if(isset($item->thumbnail) && strlen($item->thumbnail) > 0 && strlen($this->ReadPropertyString("ServerIP")) > 0) {
					$tmp = explode("url=", urldecode(urldecode($item->thumbnail)));
					$url = $tmp[1];
					$url = str_replace("127.0.0.1", $this->ReadPropertyString("ServerIP"), $url);
					if(strlen(IPS_GetProperty($this->InstanceID, "XPlexToken")) > 0) {
						$url .= "?X-Plex-Token=".IPS_GetProperty($this->InstanceID, "XPlexToken");
					}

					$coverHTML = "<img style='height:100%; width:100%' src='".$url."'>";
					SetValue($this->GetIDForIdent("CoverHTML"), $coverHTML);

					$imageBindata =  base64_encode(file_get_contents($url));
					IPS_SetMediaFile($this->GetIDForIdent("Cover"), md5($title).".jpg", false);
					IPS_SetMediaContent($this->GetIDForIdent("Cover"), $imageBindata);
					IPS_SendMediaEvent($this->GetIDForIdent("Cover"));
				} else {
					IPS_SetMediaFile($this->GetIDForIdent("Cover"), "Transparent.png", false);
					IPS_SetMediaContent($this->GetIDForIdent("Cover"), "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z/C/HgAGgwJ/lK3Q6wAAAABJRU5ErkJggg==");
					IPS_SendMediaEvent($this->GetIDForIdent("Cover"));

					SetValue($this->GetIDForIdent("CoverHTML"), "");
				}
			}
		}
		*/
	}

	// PUBLIC ACCESSIBLE FUNCTIONS
	public function Send($JSONString)
	{
		$this->SendDataToParent(json_encode(Array("DataID" => "{0A4192DF-57B7-DBED-87AC-D8568BF6CC6D}", "Buffer" => $JSONString)));
	}

	public function SendMessage($title, $message)
	{
		$command = urlencode('{"jsonrpc":"2.0","method":"GUI.ShowNotification","params":{"title":"' . $title . '","message":"' . $message . '"},"id":1}');
		file_get_contents("http://" . IPS_GetProperty($this->InstanceID, "ClientIP") . ":" . IPS_GetProperty($this->InstanceID, "ClientPort") . "/jsonrpc?request=" . $command);
	}

	public function GetSocketID()
	{
		return $this->GetParent();
	}

	public function GetPlayerID()
	{
		return GetValue($this->GetIDForIdent("PlayerID"));
	}

	// Play Controls
	public function Play()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Player.PlayPause","params":{"playerid":' . $player_id . '},"id":1}';
			$this->Send($command);
		}
	}

	public function Pause()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Player.PlayPause","params":{"playerid":' . $player_id . '},"id":1}';
			$this->Send($command);
		}
	}

	public function Stop()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Player.Stop","params":{"playerid":' . $player_id . '},"id":1}';
			$this->Send($command);
		}
	}

	public function Next()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Player.GoTo","params":{"playerid":' . $player_id . ', "to":"next"},"id":1}';
			$this->Send($command);
		}
	}

	public function Prev()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Player.GoTo","params":{"playerid":' . $player_id . ', "to":"previous"},"id":1}';
			$this->Send($command);
		}
	}

	// Controls
	public function Up()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Input.Up","params":{},"id":1}';
			$this->Send($command);
		}
		SetValue($this->GetIDForIdent("Controls"), -99);
	}

	public function PgUp()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Input.ExecuteAction","params":{"action":"pageup"},"id":1}';
			$this->Send($command);
		}
		SetValue($this->GetIDForIdent("Controls"), -99);
	}

	public function Down()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Input.Down","params":{},"id":1}';
			$this->Send($command);
		}
		SetValue($this->GetIDForIdent("Controls"), -99);
	}

	public function PgDown()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Input.ExecuteAction","params":{"action":"pagedown"},"id":1}';
			$this->Send($command);
		}
		SetValue($this->GetIDForIdent("Controls"), -99);
	}

	public function Left()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Input.Left","params":{},"id":1}';
			$this->Send($command);
		}
		SetValue($this->GetIDForIdent("Controls"), -99);
	}

	public function Right()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Input.Right","params":{},"id":1}';
			$this->Send($command);
		}
		SetValue($this->GetIDForIdent("Controls"), -99);
	}

	public function Select()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Input.Select","params":{},"id":1}';
			$this->Send($command);
		}
		SetValue($this->GetIDForIdent("Controls"), -99);
	}

	public function Back()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Input.Back","params":{},"id":1}';
			$this->Send($command);
		}
		SetValue($this->GetIDForIdent("Controls"), -99);
	}

	// Repeat
	public function RepeatOff()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Player.SetRepeat","params":{"playerid":' . $player_id . ', "repeat":"off"},"id":1}';
			$this->Send($command);
		}
	}

	public function RepeatActualElement()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Player.SetRepeat","params":{"playerid":' . $player_id . ', "repeat":"one"},"id":1}';
			$this->Send($command);
		}
	}

	public function RepeatAll()
	{
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Player.SetRepeat","params":{"playerid":' . $player_id . ', "repeat":"all"},"id":1}';
			$this->Send($command);
		}
	}

	// Volume
	public function SetVolume($level)
	{
		$currentValue = GetValue($this->GetIDForIdent("Volume"));
		$player_id = $this->GetPlayerID();
		if ($player_id >= 0) {
			$command = '{"jsonrpc":"2.0","method":"Application.SetVolume","params":{"volume": ' . $level . '},"id":1}';
			$this->Send($command);
		} else
			SetValue($this->GetIDForIdent("Volume"), $currentValue);
	}

	// Power
	public function PowerOn()
	{
		$ip = "";
		$ip_arr = explode(".", gethostbyname(IPS_GetProperty($this->InstanceID, "ClientIP")));
		for ($i = 0; $i < count($ip_arr) - 1; $i++) {
			$ip .= $ip_arr[$i] . ".";
		}
		$ip .= "255";

		$mac = IPS_GetProperty($this->InstanceID, "ClientMAC");
		if (strlen($mac) > 0) {
			@$this->plex_wake($ip, $mac);
		}
	}

	public function PowerOff()
	{
		$command = '{"jsonrpc":"2.0","method":"System.Shutdown","params":{},"id":1}';
		$this->Send($command);
	}

	// SOCKET FUNCTIONS TBD

	// HELPER FUNCTIONS
	protected function RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
	{
		if (!IPS_VariableProfileExists($Name)) {
			IPS_CreateVariableProfile($Name, 1);
		} else {
			$profile = IPS_GetVariableProfile($Name);
			if ($profile['ProfileType'] != 1)
				throw new Exception("Variable profile type does not match for profile " . $Name);
		}

		IPS_SetVariableProfileIcon($Name, $Icon);
		IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
		IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);

	}

	protected function RegisterProfileIntegerEx($Name, $Icon, $Prefix, $Suffix, $Associations)
	{
		if (sizeof($Associations) === 0) {
			$MinValue = 0;
			$MaxValue = 0;
		} else {
			$MinValue = $Associations[0][0];
			$MaxValue = $Associations[sizeof($Associations) - 1][0];
		}

		$this->RegisterProfileInteger($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

		foreach ($Associations as $Association) {
			IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
		}

	}

	protected function RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, $StepSize)
	{
		if (!IPS_VariableProfileExists($Name)) {
			IPS_CreateVariableProfile($Name, 0);
		} else {
			$profile = IPS_GetVariableProfile($Name);
			if ($profile['ProfileType'] != 0)
				throw new Exception("Variable profile type does not match for profile " . $Name);
		}

		IPS_SetVariableProfileIcon($Name, $Icon);
		IPS_SetVariableProfileText($Name, $Prefix, $Suffix);
		IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
	}

	protected function RegisterProfileBooleanEx($Name, $Icon, $Prefix, $Suffix, $Associations)
	{
		if (sizeof($Associations) === 0) {
			$MinValue = 0;
			$MaxValue = 0;
		} else {
			$MinValue = $Associations[0][0];
			$MaxValue = $Associations[sizeof($Associations) - 1][0];
		}

		$this->RegisterProfileBoolean($Name, $Icon, $Prefix, $Suffix, $MinValue, $MaxValue, 0);

		foreach ($Associations as $Association) {
			IPS_SetVariableProfileAssociation($Name, $Association[0], $Association[1], $Association[2], $Association[3]);
		}

	}

	protected function GetParent()
	{
		$instance = IPS_GetInstance($this->InstanceID);
		return ($instance['ConnectionID'] > 0) ? $instance['ConnectionID'] : false;
	}


	protected function plex_wake($ip, $mac)
	{
		if (strstr($mac, "-") !== false)
			$addr_byte = explode('-', $mac);
		else if (strstr($mac, ":") !== false)
			$addr_byte = explode(':', $mac);

		$hw_addr = '';

		for ($a = 0; $a < 6; $a++) $hw_addr .= chr(hexdec($addr_byte[$a]));

		$msg = chr(255) . chr(255) . chr(255) . chr(255) . chr(255) . chr(255);

		for ($a = 1; $a <= 16; $a++) $msg .= $hw_addr;

		$s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		if ($s != false) {
			$opt_ret = socket_set_option($s, 1, 6, TRUE);
			$e = socket_sendto($s, $msg, strlen($msg), 0, $ip, 2050);
			socket_close($s);
		}
	}

	//Add this Polyfill for IP-Symcon 4.4 and older
	protected function SetValue($Ident, $Value)
	{

		if (IPS_GetKernelVersion() >= 5) {
			parent::SetValue($Ident, $Value);
		} else {
			SetValue($this->GetIDForIdent($Ident), $Value);
		}
	}
}

?>
