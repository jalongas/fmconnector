<?php
class SugarForceManager
{
	private static $_instance;
	private $_platformUsername;
	private $_platformPassword;
	private $_username;
	private $_password;
	private $_endpoint = 'http://be-api.forcemanager.net/tsws/fmanager2.asmx?wsdl';
	private $_key;
	private $_userKey;
	private $_localTime;
	private $_strLocalTime;

	public function __construct()
	{
		global $sugar_config;

		if(isset($sugar_config['force_manager']['endpoint']) && !empty($sugar_config['force_manager']['endpoint']))
			$this->_endpoint = $sugar_config['force_manager']['endpoint'];
		if(isset($sugar_config['force_manager']['username']) && !empty($sugar_config['force_manager']['username']))
			$this->_username = $sugar_config['force_manager']['username'];
		if(isset($sugar_config['force_manager']['password']) && !empty($sugar_config['force_manager']['password']))
			$this->_password = $sugar_config['force_manager']['password'];
		if(isset($sugar_config['force_manager']['platform_username']) && !empty($sugar_config['force_manager']['platform_username']))
			$this->_platformUsername = $sugar_config['force_manager']['platform_username'];
		if(isset($sugar_config['force_manager']['platform_password']) && !empty($sugar_config['force_manager']['platform_password']))
			$this->_platformPassword = $sugar_config['force_manager']['platform_password'];
	}

	public static function getInstance()
	{
		if(!isset(self::$_instance)){
			$c = __CLASS__;
			self::$_instance = new $c;
		}
		return self::$_instance;
	}

	public function fetchEmpresas($limit = 0)
	{
		$error = '';
		$response = array();

		try{
			//$this->_forceManagerLogin();
			$this->_generateKeyAndUserKey();

			$client = new SoapClient($this->_endpoint);
			$response = $client->GetEmpresas(array(
				'Key' => $this->_key,
				'UserKey' => $this->_userKey,
				'strLocalTime' => date('d/m/Y H:i', strtotime($this->_strLocalTime)),
				'strCellPhoneNumber' => $this->_username,
				'IdEmpresa' => '-1',
				'relation_idEmpresa' => '-1',
				'relation_type' => '-1',
				'idUser' => '-1',
				'strSearchParamaters' => '',
				'intOrderType' => 1,
				'intGeoProximityLevel' => 0,
				'blnUseCompression' =>  true,
				'intDeviceType' => 105,
				'intFirstRecortPosition' => 0,
				'strClientVersion' => 'Prakton SugarConnector 1.0',
				'blnGetRecordsCount' => false,
				'intMaxResults' => $limit,
			));
			unset($client);

			if(isset($response->GetEmpresasResult)){
				$response = gzdecode(base64_decode($response->GetEmpresasResult));
				if($response == 'No result'){
					$response = '';
				}
			}else{
				$response = '';
			}
		}catch(Exception $e){
			$error = $e->getMessage();
		}

		return array('error' => $error, 'response' => $response);
	}

	public function createEntity($type = '', $bean = '')
	{
		$error = '';
		$response = array();

		if(!empty($type) && $this->_isValidEntity($type)){
			$strXmlFields = '';
			switch($type){
				case 'Company':
					$entityName = 'EMPRESA';

					$strXmlFields.= '<fields>';
					$strXmlFields.= $this->_buildXmlField($bean, 'id');
					$strXmlFields.= $this->_buildXmlField($bean, 'name');
					$strXmlFields.= $this->_buildXmlField($bean, 'sic_code');
					$strXmlFields.= $this->_buildXmlField($bean, 'phone_office');
					$strXmlFields.= $this->_buildXmlField($bean, 'phone_fax');
					$strXmlFields.= $this->_buildXmlField($bean, 'phone_alternate');
					$strXmlFields.= $this->_buildXmlField($bean, 'email1');
					$strXmlFields.= $this->_buildXmlField($bean, 'description');
					$strXmlFields.= $this->_buildXmlField($bean, 'billing_address_street');
					$strXmlFields.= $this->_buildXmlField($bean, 'billing_address_state');
					$strXmlFields.= $this->_buildXmlField($bean, 'billing_address_city');
					$strXmlFields.= $this->_buildXmlField($bean, 'billing_address_postalcode');
					$strXmlFields.= '</fields>';
					break;
				case 'Contact':
					$entityName = 'CONTACTO';

					$strXmlFields = '';
					break;
				default:
					break;
			}

			if(!empty($strXmlFields)){
				try{
					//$this->_forceManagerLogin();
					$this->_generateKeyAndUserKey();

					$client = new SoapClient($this->_endpoint);
					$response = $client->CreateEntity(array(
						'Key' => $this->_key,
						'UserKey' => $this->_userKey,
						'strLocalTime' => date('d/m/Y H:i', strtotime($this->_strLocalTime)),
						'strCellPhoneNumber' => $this->_username,
						'entityType' => $entityName,
						'xmlFields' => $strXmlFields,
						'intDeviceType' => 105,
						'strClientVersion' => 'Prakton SugarConnector 1.0',
						'blnUseCompression' =>  true,
					));
					unset($client);

					if(isset($response->CreateEntityResult)){
						$response = gzdecode(base64_decode($response->CreateEntityResult));
					}else{
						$response = '';
					}
				}catch(Exception $e){
					$error = $e->getMessage();
				}
			}
		}

		return array('error' => $error, 'response' => $response);
	}

	private function _isValidEntity($type = '')
	{
		$valid = false;
		switch($type){
			case 'Company':
				$valid = true;
				break;
			case 'Contact':
				$valid = false;
				break;
			default:
				break;
		}

		return $valid;
	}

	private function _buildXmlField($bean = '', $field = '')
	{
		$xmlField = '';
		if(!empty($bean) && !empty($field) && isset($bean->$field))
			$xmlField = '<field name="' . $this->_getFMFieldNameByEntity($bean->module, $field) . '" value="' . $bean->$field . '" />';

		return $xmlField;
	}

	private function _getFMFieldNameByEntity($module = '', $field = '')
	{
		$fieldMap = array(
			'Accounts' => array(
				'id'							=> 'Z_crmId',
				'name' 							=> 'nombre',
				'sic_code' 						=> 'nif',
				'phone_office' 					=> 'tel',
				'phone_fax' 					=> 'fax',
				'phone_alternate' 				=> 'tel_movil',
				'email1' 						=> 'email',
				'description' 					=> 'Observaciones',
				'billing_address_street' 		=> 'direccion',
				'billing_address_state' 		=> 'strProvincia',
				'billing_address_city' 			=> 'strPoblacion',
				'billing_address_postalcode'	=> 'cp',
			),
			'Contacts' => array(),
		);

		if(isset($fieldMap[$module][$field]))
			return $fieldMap[$module][$field];

		return '';
	}

	private function _generateKeyAndUserKey()
	{
		$this->_strLocalTime = date('Y-m-d H:i');
		$this->_localTime = date('Ymd|H', strtotime($this->_strLocalTime));
		//Generate Key: Step 1 - Timestamp
		$keyHash = sha1($this->_localTime);
		//Generate Key: Step 2 - Timestamp Final
		$keyHash = sha1($this->_platformUsername . '|' . $this->_platformPassword . '|' . $keyHash);
		$this->_key = $keyHash . '|' . $this->_platformUsername;

		//Generate UserKey: Step 1 
		$this->_userKey = sha1($this->_username . '|' . $this->_password);
	}

	private function _forceManagerLogin()
	{
		$error = '';
		$response = array();

		try{
			$this->_generateKeyAndUserKey();

			$client = new SoapClient($this->_endpoint);
			$response = $client->Login(array(
				'Key' => $this->_key,
				'UserKey' => $this->_userKey,
				'strCellPhoneNumber' => $this->_username,
				'idUsuario' => '-1',
				'idImplementacion' => '-1',
				'intDeviceType' => '105',
				'strClientVersion' => 'Prakton SugarConnector 1.0',
				'idUsuarioImpersonated' => '-1',
				'idImplementacionImpersonated' => '-1',
			));
			unset($client);
		}catch(Exception $e){
			$error = $e->getMessage();
		}
		return array('error' => $error, 'response' => $response);
	}
}
