<?

class PlexIO extends IPSModule
{

	public function Create()
	{
		parent::Create();

		$this->RegisterPropertyString("username", "plex");
		$this->RegisterPropertyString("password", "ips");

		// register Webhook
		$this->RegisterWebhook('/hook/plex');
	}

	public function ApplyChanges()
	{
		parent::ApplyChanges();

		$this->ValidateConfiguration();

	}

	private function ValidateConfiguration()
	{
		$username = $this->ReadPropertyString('username');
		$password = $this->ReadPropertyString('password');

		if ($username == "" || $password == "") {
			$this->SetStatus(202);
		} else {
			$this->SetStatus(102);
		}
	}

	/**
	 * Process Webhook Data
	 */
	protected function ProcessHookData()
	{
		$username = $this->ReadPropertyString('username');
		$password = $this->ReadPropertyString('password');
		if (!isset($_SERVER['PHP_AUTH_USER']))
			$_SERVER['PHP_AUTH_USER'] = "";
		if (!isset($_SERVER['PHP_AUTH_PW']))
			$_SERVER['PHP_AUTH_PW'] = "";

		if (($_SERVER['PHP_AUTH_USER'] != $username) || ($_SERVER['PHP_AUTH_PW'] != $password)) {
			header('WWW-Authenticate: Basic Realm="Plex WebHook"');
			header('HTTP/1.0 401 Unauthorized');
			echo "Authorization required";
			return;
		}
		echo "Webhook Plex IP-Symcon";

		//workaround for bug
		if (!isset($_IPS))
			global $_IPS;
		if ($_IPS['SENDER'] == "Execute") {
			echo "This script cannot be used this way.";
			return;
		}

		$plexjson = $_POST["payload"];
		$this->SendDebug("Plex I/O", $plexjson, 0);
		$plex_payload = json_decode($plexjson);
		$this->SendJSON($plex_payload);
	}

	/**
	 * Register Webhook
	 * @param string $webhook
	 * @param bool $delete
	 */
	protected function RegisterWebhook($webhook, $delete = false)
	{
		$ids = IPS_GetInstanceListByModuleID("{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}");

		if (sizeof($ids) > 0) {
			$hooks = json_decode(IPS_GetProperty($ids[0], "Hooks"), true);
			$found = false;
			foreach ($hooks AS $index => $hook) {
				if ($hook['Hook'] == $webhook) {
					if ($hook['TargetID'] == $this->InstanceID && !$delete)
						return;
					elseif ($delete && $hook['TargetID'] == $this->InstanceID) {
						continue;
					}

					$hooks[$index]['TargetID'] = $this->InstanceID;
					$found = true;
				}
			}
			if (!$found) {
				$hooks[] = ["Hook" => $webhook, "TargetID" => $this->InstanceID];
			}

			IPS_SetProperty($ids[0], "Hooks", json_encode($hooks));
			IPS_ApplyChanges($ids[0]);
		}
	}

	protected function SendJSON($data)
	{
		// Weiterleitung zu allen Gerät-/Device-Instanzen
		$this->SendDataToChildren(json_encode(Array("DataID" => "{AD417AC1-7853-A43C-7011-CD5D60984FB8}", "Buffer" => $data))); //  I/O RX GUI
	}
}

