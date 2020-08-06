<?php
/**
 * Created by IntelliJ IDEA.
 * User: vbhendigiri
 * Date: 4/02/2019
 * Time: 8:48 AM
 *
 * @property Doctrine doctrine
 */

use Restserver\Libraries\REST_Controller;

class Nowapi extends API_Controller
{
	/**
	 * @var \Doctrine\ORM\EntityManager
	 */
	private $em;

	private $CI;

	function __construct()
	{
		parent::__construct();
		$this->em = $this->doctrine->em;
		$this->CI =& get_instance();
		//Check if the request coming in has Valid API key.

		$registeredApiKeys = $this->CI->config->item('wcf_api_keys');
		$headerApiKey      = null;
		if (isset(getallheaders()['X-API-KEY'])) {
			$headerApiKey = getallheaders()['X-API-KEY'];
		}
		if (!\in_array($headerApiKey, $registeredApiKeys, true)) {
			$this->set_response([
									'status'  => true,
									'message' => 'Access Denied',
								], REST_Controller::HTTP_FORBIDDEN);
		}
	}

	public function index_post()
	{
		return false;
	}

	function index_get()
	{
		return false;
	}

	function gettestuserdetail_post()
	{
		$sessionId = md5(date("d:m:Y") . session_id() . date("H:i:s"));

		$fname  = $this->post('firstName');
		$lname  = $this->post('lastName');
		$rawDob = $this->post('dob');

		if ($rawDob !== '') {
			$dob = date('Y-m-d', strtotime(str_replace('/', '-', $rawDob)));
		} else {
			$dob = date('Y-m-d', now());
		}

		$testUserRepo = $this->em->getRepository('Entity\TestUsers');
		$testUser     = $testUserRepo->findOneBy(['fname' => $fname, 'lname' => $lname, 'dob' => new \DateTime($dob, new DateTimeZone('Australia/Melbourne'))]);
		$data         = [];


		if ($testUser instanceof \Entity\TestUsers) {

			//Get Interest rate
			$this->load->model('accessseeker_model');
			$interestData = $this->accessseeker_model->getInterestRate(['score'          => $testUser->getCreditScore(),
																		'couponcode'     => '',
																		'api_run_status' => '']);

			$data = ['fname'                => $testUser->getFname(),
					 'mname'                => $testUser->getMName(),
					 'lname'                => $testUser->getLname(),
					 'dob'                  => $testUser->getDob()->format('d/m/Y'),
					 'phone'                => $testUser->getPhone(),
					 'email'                => $testUser->getEmail(),
					 'gender'               => $testUser->getGender(),
					 'streetAddr1'          => $testUser->getStreetAddr1(),
					 'streetAddr2'          => $testUser->getStreetAddr2(),
					 'state'                => $testUser->getState(),
					 'postCode'             => $testUser->getPostCode(),
					 'creditScore'          => $testUser->getCreditScore(),
					 'loanAmount'           => $testUser->getLoanAmount(),
					 'loanPurpose'          => $testUser->getLoanPurpose(),
					 'interest_rate'        => $interestData['interest_rate'],
					 'comparison_rate'      => $interestData['comparison_rate'],
					 'ref_url'              => $interestData['ref_url'],
					 'campaign_name'        => $interestData['campaign_name'],
					 'check_point_discount' => $interestData['check_point_discount'],
					 'score_new'            => $interestData['score_new'],
					 'coupon_code'          => $interestData['coupon_code'],
					 'insert_id'            => $testUser->getId(),
					 'app_status'           => $interestData['app_status'],
					 'istest'               => true,
			];
		}

		//Log the data into the DB
		$dbLogger = new \Entity\Logs();

		$headerApiKey = null;
		if (isset(getallheaders()['X-API-KEY'])) {
			$headerApiKey = getallheaders()['X-API-KEY'];
		}
		$dbLogger->setEvent('Get User Detail Request')
				 ->setTitle('NF')
				 ->setSite($headerApiKey)
				 ->setDescription('Get User Detail')
				 ->setRequest(GuzzleHttp\json_encode($this->input->post()))
				 ->setCreatedon(new \DateTime('now'))
				 ->setResponse(GuzzleHttp\json_encode($data));
		$this->em->persist($dbLogger);
		$this->em->flush($dbLogger);

		$this->set_response([
								'status'  => true,
								'message' => GuzzleHttp\json_encode($data),
							], REST_Controller::HTTP_OK);

	}

	function getinterestrate_post()
	{
		$sessionId = md5(date("d:m:Y") . session_id() . date("H:i:s"));

		$this->load->model('accessseeker_model');
		$interestData = $this->accessseeker_model->getInterestRate(['score'          => $this->post('score'),
																	'couponcode'     => $this->post('couponcode'),
																	'api_run_status' => $this->post('api_run_status')]);

		//Log the data into the DB
		$dbLogger = new \Entity\Logs();

		$headerApiKey = null;
		if (isset(getallheaders()['X-API-KEY'])) {
			$headerApiKey = getallheaders()['X-API-KEY'];
		}
		$dbLogger->setEvent('Access Seeker Test')
				 ->setTitle('NF')
				 ->setSite($headerApiKey)
				 ->setDescription('Get User Detail')
				 ->setRequest(GuzzleHttp\json_encode($this->input->post()))
				 ->setCreatedon(new \DateTime('now'))
				 ->setResponse(GuzzleHttp\json_encode($interestData));
		$this->em->persist($dbLogger);
		$this->em->flush($dbLogger);

		$this->set_response([
								'istest'  => true,
								'status'  => true,
								'message' => GuzzleHttp\json_encode($interestData),
							], REST_Controller::HTTP_OK);

	}

	function getcheckidmatrixresult_post()
	{

		$user_data = $this->post();
		//var_dump($user_data);exit;
		if (!isset($user_data['previousAddress'])) {
			$user_data['previousAddress'] = '';
		}

		$response = $this->userverificationidmatrix($user_data);

		$this->set_response([
								'istest'  => true,
								'status'  => true,
								'message' => GuzzleHttp\json_encode($response),
							], REST_Controller::HTTP_OK);

	}

	function getidmatrixscore_post()
	{

		$url      = getenv('VD_API_URL');
		$username = getenv('VD_API_USERNAME');
		$password = getenv('VD_API_PASSWORD');

		$user_data = $this->post();

		if (!isset($user_data['previousAddress'])) {
			$user_data['previousAddress'] = '';
		}

//		$response = $this->userverificationidmatrix($user_data);
		$response = ['status' => 'ACCEPT'];

		if ($response['status'] == 'ACCEPT') {

			$xml_post_string = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:vs="http://vedaxml.com/vxml2/vedascore-apply-v2-0.xsd" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd" xmlns:wsa="http://www.w3.org/2005/08/addressing">
        <soapenv:Header>
        <wsse:Security>
        <wsse:UsernameToken> <wsse:Username>' . $username . '</wsse:Username> <wsse:Password>' . $password . '</wsse:Password>
        </wsse:UsernameToken>
        </wsse:Security>
        <wsa:Action>http://vedaxml.com/vedascore-apply/EnquiryRequest</wsa:Action>
        </soapenv:Header>
        <soapenv:Body>
        <vs:request xsi:schemaLocation="http://vedaxml.com/vxml2/vedascore-apply-v2-0.xsd vedascore-apply-v2-0.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
        <vs:enquiry-header>
            <vs:client-reference>X02</vs:client-reference>
            <vs:operator-id>101</vs:operator-id>
            <vs:operator-name>Craig Wilson</vs:operator-name>
            <vs:permission-type-code>XY</vs:permission-type-code>
            <vs:product-data-level-code>C</vs:product-data-level-code>
            <vs:requested-scores>
              <vs:scorecard-id>VS_1.1_XY_NR</vs:scorecard-id>
              <vs:scorecard-id>VSA_2.0_XY_CR</vs:scorecard-id>        
              <vs:scorecard-id>VSA_2.0_XY_NR</vs:scorecard-id>
            </vs:requested-scores>
          </vs:enquiry-header>
          <vs:enquiry-data>
            <vs:individual>
              <vs:current-name>
            
              <vs:family-name>' . $user_data['lastname'] . '</vs:family-name>
                <vs:first-given-name>' . $user_data['firstname'] . '</vs:first-given-name>
                
              </vs:current-name>
              <vs:addresses>
                <vs:address type="C">
                  <vs:street-number>' . $user_data['street_no'] . '</vs:street-number> 
                  <vs:street-name>' . $user_data['street_name'] . '</vs:street-name>
                  <vs:suburb>' . $user_data['suburb'] . '</vs:suburb>
                  <vs:state>' . $user_data['state'] . '</vs:state>
                  <vs:postcode>' . $user_data['postcode'] . '</vs:postcode>
                </vs:address>
              </vs:addresses>
              
              <vs:gender-code>' . strtoupper($user_data['gender']) . '</vs:gender-code>
              <vs:date-of-birth>' . $user_data['new_date'] . '</vs:date-of-birth>
            </vs:individual>
            <vs:enquiry>
              <vs:account-type-code>PR</vs:account-type-code>
              <vs:enquiry-amount currency-code="AUD">10000</vs:enquiry-amount>
              <vs:is-credit-review>false</vs:is-credit-review>
              <vs:relationship-code>1</vs:relationship-code>
            </vs:enquiry>
          </vs:enquiry-data>
        </vs:request>
        </soapenv:Body>
        </soapenv:Envelope>';

			$options = array();
			if (!empty($xml_post_string)) {
				$options = array_merge($options, ['body' => $xml_post_string]);
			}

			try {

				$client = new GuzzleHttp\Client(['verify' => false]);

				$response = $client->request('POST', $url, $options);

				$body = $response->getBody();
				// Implicitly cast the body to a string and echo it
				echo $body;
				exit;
			} catch (\GuzzleHttp\Exception\RequestException $e) {
				$guzzleResult = $e->getResponse();
			}
		}
	}

	function userverificationidmatrix($post_data)
	{

		$url      = getenv('IDM_API_URL');
		$username = getenv('IDM_API_USERNAME');
		$password = getenv('IDM_API_PASSWORD');

		$xml_post_string = '<soapenv:Envelope
      xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
      xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"
      xmlns:wsa="http://www.w3.org/2005/08/addressing"
      xmlns:vh="http://vedaxml.com/soap/header/v-header-v1-9.xsd"
      xmlns:idm="http://vedaxml.com/vxml2/idmatrix-v4-0.xsd">
      <soapenv:Header>
      <wsse:Security>
      <wsse:UsernameToken> <wsse:Username>' . $username . '</wsse:Username> <wsse:Password>' . $password . '</wsse:Password>
      </wsse:UsernameToken>
      </wsse:Security>
      <wsa:ReplyTo>
      <wsa:Address>http://www.w3.org/2005/08/addressing/anonymous
      </wsa:Address>
      </wsa:ReplyTo>
      <wsa:To>http://vedaxml.com/sys2/idmatrix-v4</wsa:To>
      <wsa:Action>http://vedaxml.com/idmatrix/VerifyIdentity</wsa:Action>
      <wsa:MessageID>Request_1</wsa:MessageID>
      </soapenv:Header>
      <soapenv:Body>
      <idm:request client-reference="Quick Connect Ref"
      reason-for-enquiry="Quick Connect">
      <idm:consents>
      <idm:consent status="1">VEDA-CBCONS</idm:consent>
      <idm:consent status="1">DL</idm:consent>
      </idm:consents>
      <idm:individual-name>
      <idm:family-name>' . $post_data['lastname'] . '</idm:family-name>
      <idm:first-given-name>' . $post_data['firstname'] . '</idm:first-given-name>
      </idm:individual-name>
      <idm:date-of-birth>' . $post_data['new_date'] . '</idm:date-of-birth>
      <idm:current-address>
      <idm:street-number>' . $post_data['street_no'] . '</idm:street-number>
      <idm:street-name>' . $post_data['street_name'] . '</idm:street-name>
      <idm:suburb>' . $post_data['suburb'] . '</idm:suburb>
      <idm:state>' . $post_data['state'] . '</idm:state>
      <idm:postcode>' . $post_data['postcode'] . '</idm:postcode>  
      <idm:country>AUS</idm:country>
      </idm:current-address>
      ' . $post_data['previousAddress'] . '
      <idm:drivers-licence-details>
      <idm:state-code>' . $post_data['driversLicenceState'] . '</idm:state-code>
      <idm:number>' . $post_data['driversLicenceNumber'] . '</idm:number>
      </idm:drivers-licence-details>
      </idm:request>
      </soapenv:Body>
      </soapenv:Envelope>';

		//echo $xml_post_string;exit;
		$options = array();
		if (!empty($xml_post_string)) {
			//die("I am here");
			$options = array_merge($options, ['body' => $xml_post_string]);
		}
		//var_dump($options);exit;
		try {

			$client = new GuzzleHttp\Client(['verify' => false]);

			$response = $client->request('POST', $url, $options);

			$body = $response->getBody();
			// Implicitly cast the body to a string and echo it
//			echo $body;exit; //. '<br/><br/><br/>';
			// Explicitly cast the body to a string
			$stringBody = (string)$body;

			$lines                  = explode(PHP_EOL, $stringBody);
			$explodeIdmResponse     = array();
			$result                 = array();
			$no_of_lines_per_search = 120;
			//$no_of_searches = round(count($lines)/$no_of_lines_per_search);
			var_dump($lines);
			exit;

			for ($j = 0; $j < $no_of_lines_per_search; $j++) {
				$explodeIdmResponse[] = str_getcsv($lines[$j]);
			}
			foreach ($explodeIdmResponse as $resp) {
				if (is_array($resp)) {
					if (strpos($resp[0], '<ns5:overall-outcome>') !== false) {
						$result['status'] = strip_tags($resp[0]);
					} else if (strpos($resp[0], '<wsa:MessageID>') !== false) {
						$result['unique_id'] = strip_tags($resp[0]);
					}
				}
			}
		} catch (\GuzzleHttp\Exception\RequestException $e) {
			$guzzleResult = $e->getResponse();
		}


		return $result;
	}

	function getcheckdbresult_post()
	{

		$db_api_url       = getenv('DB_API_URL');
		$db_userkey       = getenv('DB_API_UserKey');
		$db_pass          = getenv('DB_API_PASSWORD');
		$db_subscriber_id = getenv('DB_API_SubscriberId');
		$db_api_env       = getenv('DB_API_ENV');
		$db_api_host      = getenv('DB_API_HOST');

		$userData = $this->post();

		var_dump($userData);

	}


}
