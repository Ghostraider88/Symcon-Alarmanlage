<?php
	class Pinpad extends IPSModule
	{

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterVariable();

			$this->SetVisualizationType(1);
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

		}

		public function RequestAction($Ident, $value)
		{
			if ($this->GetIDForIdent($Ident))
			{
				            $this->SendDebug('Error in RequestAction', 'Variable to be updated does not exist'.$value, 0);
							$this->SetValue($Ident, $value);
			}
		}
		private function RegisterVariable()
		{
			if (@!$this->GetIDForIdent('PinID'))
			{ 
				$this->RegisterVariableString('PinID', $this->Translate('Pin'),'',0);
            	IPS_SetHidden($this->GetIDForIdent('PinID'), true);
			}
		}

		public function GetVisualizationTile()
		{
			//$initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ')</script>';

			// Füge statisches HTML aus Datei hinzu
			$module = file_get_contents(__DIR__ . '/module.html');
			//			return $module .$initialHandling;
			return $module;
		}

		// sende Text zum Display
		public function sendText(string $Text, int $Seconds)
		{
			$result = [];
			$result['sendText'] = $Text;
			$result['timeOut'] = $Seconds * 1000;

			$this->UpdateVisualizationValue(json_encode($result));
		}
	}