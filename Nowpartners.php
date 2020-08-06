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

class Nowpartners extends API_Controller
{
	/**
	 * @var \Doctrine\ORM\EntityManager
	 */
	private $em;

	private $CI;

	/**
	 * @var \Entity\Partners|null
	 */
	private $partner;

	function __construct()
	{
		parent::__construct();
		$this->em = $this->doctrine->em;
		$this->CI =& get_instance();
		//Check if the request coming in has Valid API key.
		$headerApiKey = null;
		$access       = false;
		$partner      = null;

		if (isset(getallheaders()['Nf-Api-Key'])) {
			$headerApiKey = getallheaders()['Nf-Api-Key'];
		}
		if ($headerApiKey !== null && $headerApiKey !== '') {
			/** @var \Entity\Partners $partner */
			$partner = $this->em->getRepository(\Entity\Partners::class)->findOneBy(['isactive' => 1, 'apiKey' => $headerApiKey]);
			if ($partner instanceof \Entity\Partners === true) {
				$access = true;
			}
		}
		$this->partner = $partner;

		if ($access != true) {
			$this->set_response([
									'status'  => true,
									'message' => 'Access Denied',
								], REST_Controller::HTTP_FORBIDDEN);
			exit;
		}
	}

	function index_post()
	{
		$this->set_response([
								'status'  => true,
								'message' => 'Access Denied to root',
							], REST_Controller::HTTP_BAD_GATEWAY);
	}

	function index_get()
	{
		$this->set_response([
								'status'  => true,
								'message' => 'Access Denied to root',
							], REST_Controller::HTTP_BAD_GATEWAY);
	}

	/**
	 * returns rate, loan_amount, loan_term, repayments, etc
	 */
	function getrate_post()
	{
		$this->load->model('PartnerApi_model');
		$client          = new \GuzzleHttp\Client(['verify' => false]);
		$partnerCustomer = new \Entity\PartnersCustomer();
		$rawDob          = $this->post('date_of_birth');
		$dob             = null;
		$validDob        = null;
		if ($rawDob !== '' && $rawDob !== null) {
			$validDob     = DateTime::createFromFormat('d/m/Y', $rawDob)->format('Y-m-d');
			$formattedDOB = DateTime::createFromFormat('Y-m-d', $validDob);
		} else {
			$formattedDOB = null;
		}
		$loanPurpose = in_array(strtolower($this->post('loan_purpose')), LOAN_PURPOSES) ? strtolower($this->post('loan_purpose')) : 'other';
		$partnerCustomer->setFname($this->post('first_name'))
						->setMname($this->post('middle_name'))
						->setLname($this->post('last_name'))
						->setEmail($this->post('email_address'))
						->setMobile($this->post('mobile_number'))
						->setHomePhone($this->post('home_number'))
						->setGender(strtolower($this->post('gender')))
						->setDob($formattedDOB)
						->setLoanAmount($this->post('loan_amount'))
						->setLoanPurpose($loanPurpose)
						->setLoanTerm($this->post('loan_term'))
						->setPaymentFrequency($this->post('payment_frequency'))
						->setCurrentUnitNumber($this->post('current_unit_number'))
						->setCurrentStreetNumber($this->post('current_street_number'))
						->setCurrentStreetName($this->post('current_street_name'))
						->setCurrentStreetType($this->post('current_street_type'))
						->setCurrentSuburb($this->post('current_suburb'))
						->setCurrentState($this->post('current_state'))
						->setCurrentPostCode($this->post('postcode'))
						->setScore($this->post('score'))
						->setCouponCode($this->post('coupon_code'))
						->setPartner($this->partner);

		$rateResult = null;

//		Log the data into the DB
		$dbLogger = new \Entity\Logs();
		$dbLogger->setEvent('API Rate Request')
				 ->setTitle(LOG_TITLE_PARTNERS_API_RATE)
				 ->setSite($this->partner->getName())
				 ->setDescription('Incoming API Request to get rate.')
				 ->setRequest(GuzzleHttp\json_encode(['request' => $this->post()]))
				 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
				 ->setResponse(GuzzleHttp\json_encode([]));
		$this->em->persist($dbLogger);
		$this->em->flush($dbLogger);


		//Score Cut off Check
		if ($partnerCustomer->getScore() <= 539) {
			$data = ['status' => 'failed', 'message' => 'Not Eligible'];
		} else {
			//Get all posted data and validate
			$result     = $this->validateMandatoryData($partnerCustomer);
			$rateResult = null;
			if ($result) {
				/**
				 * @var \Entity\PartnersCustomer $partnersCustomer
				 */
				$partnersCustomer = $this->PartnerApi_model->getPartnerInterestRate($partnerCustomer);

				//Log the data into the DB
				$dbLogger1 = new \Entity\Logs();
				$dbLogger1->setEvent('API Rate Response')
						  ->setTitle(LOG_TITLE_PARTNERS_API_RATE)
						  ->setSite($this->partner->getName())
						  ->setDescription('Response Prepared to send')
						  ->setRequest(GuzzleHttp\json_encode(['request' => $this->post()]))
						  ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
						  ->setResponse(GuzzleHttp\json_encode(['response' => (array)$partnersCustomer]));


				$this->em->persist($dbLogger1);
				$this->em->flush($dbLogger1);


				$this->em->flush();
				$apiReturnData = [

					'interestRate'       => $partnersCustomer->getInterestRate(),
					'comparisonRate'     => $partnersCustomer->getComparisonRate(),
					'loanAmount'         => $partnersCustomer->getLoanAmount(),
					'loanPurpose'        => $partnersCustomer->getLoanPurpose(),
					'loanTerm'           => $partnersCustomer->getLoanTerm(),
					'paymentFrequency'   => $partnersCustomer->getPaymentFrequency(),
					'principalAmount'    => $partnersCustomer->getPrincipalAmount(),
					'numberOfRepayments' => $partnersCustomer->getNumberOfRepayments(),
					'repaymentAmount'    => $partnersCustomer->getRepaymentAmount(),
					'score'              => $partnersCustomer->getScore(),
					'applicationFee'     => $partnersCustomer->getApplicationFee(),
					'debitFee'           => $partnersCustomer->getDebitFee(),
					'monthlyDebitFee'    => $partnersCustomer->getMonthlyDebitFee(),

				];

				$data = ['status' => 'success', 'message' => GuzzleHttp\json_encode($apiReturnData)];

			} else {
				$data = ['status' => 'failed', 'message' => 'Not Eligible'];
			}
		}

		$this->set_response($data, REST_Controller::HTTP_OK);
	}


	/**
	 * Validates if the partner has posted all the required data into json format & also checks all mandatory fields
	 * are sent over
	 */
	private function validateMandatoryData(\Entity\PartnersCustomer $partnersCustomer)
	{
		$errorMsg = '';
		if ($partnersCustomer->getFname() === '' || $partnersCustomer->getFname() === null) {
			$errorMsg .= 'First Name Missing';
		}

		if ($partnersCustomer->getLname() === '' || $partnersCustomer->getLname() === null) {
			$errorMsg .= 'Last Name Missing';
		}

		if ($partnersCustomer->getEmail() === '' || $partnersCustomer->getEmail() === null) {
			$errorMsg .= 'Email Missing';
		}

		if ($partnersCustomer->getMobile() === '' || $partnersCustomer->getMobile() === null) {
			$errorMsg .= 'Mobile Missing';
		}

		if ($partnersCustomer->getGender() === '' || $partnersCustomer->getGender() === null) {
			$errorMsg .= 'Gender missing or Invalid';
		}

		if ($partnersCustomer->getGender() !== '' && $partnersCustomer->getGender() !== null && $this->CI->commonutil->checkIfGenderIsValid($partnersCustomer->getGender()) === false) {

			$errorMsg .= 'Invalid Gender';
		}

		if ($partnersCustomer->getDob() === '' || $partnersCustomer->getDob() === null) {
			$errorMsg .= 'Date of Birth missing/invalid';
		}

		if ($partnersCustomer->getLoanAmount() === '' || $partnersCustomer->getLoanAmount() === null) {
			$errorMsg .= 'Loan Amount Missing';
		}

		if ($partnersCustomer->getLoanAmount() !== ''
			&& $partnersCustomer->getLoanAmount() !== null
			&& !is_numeric($partnersCustomer->getLoanAmount())
		) {
			$errorMsg .= 'Invalid Loan Amount';
		}

		if ($partnersCustomer->getLoanTerm() === '' || $partnersCustomer->getLoanTerm() === null) {
			$errorMsg .= 'Loan Term Missing';
		}

		if ($partnersCustomer->getLoanAmount() !== ''
			&& $partnersCustomer->getLoanAmount() !== null && ((int)$partnersCustomer->getLoanAmount() < 4000 || (int)$partnersCustomer->getLoanAmount() >= 400000)) {
			$errorMsg .= 'Loan amount should be between 4000 - 400000';
		}

		if ($partnersCustomer->getLoanTerm() !== ''
			&& $partnersCustomer->getLoanTerm() !== null && !is_numeric($partnersCustomer->getLoanTerm())) {
			$errorMsg .= 'Invalid Loan Term';
		} elseif ($partnersCustomer->getLoanTerm() > 84 || $partnersCustomer->getLoanTerm() < 1) {
			$errorMsg .= 'Loan term ranges from 1-84  only';
		}

		if ($partnersCustomer->getLoanPurpose() === '' || $partnersCustomer->getLoanPurpose() === null) {
			$errorMsg .= 'Loan Amount Missing';
		}


		if ($partnersCustomer->getCurrentStreetNumber() === '' || $partnersCustomer->getCurrentStreetNumber() === null) {
			$errorMsg .= 'Street Number Missing';
		}

		if ($partnersCustomer->getCurrentStreetName() === '' || $partnersCustomer->getCurrentStreetName() === null) {
			$errorMsg .= 'Street Name Missing';
		}

		if ($partnersCustomer->getCurrentSuburb() === '' || $partnersCustomer->getCurrentSuburb() === null) {
			$errorMsg .= 'Suburb Missing';
		}

		if ($partnersCustomer->getCurrentState() === '' || $partnersCustomer->getCurrentState() === null) {
			$errorMsg .= 'State Missing';
		}

		if ($partnersCustomer->getCurrentState() !== '' && $partnersCustomer->getCurrentState() !== null && $this->CI->commonutil->checkIfStateIsValid($partnersCustomer->getCurrentState()) === false) {

			$errorMsg .= 'Invalid State. Please refer documentation for valid state codes';
		}


		if ($partnersCustomer->getCurrentPostCode() === '' || $partnersCustomer->getCurrentPostCode() === null) {
			$errorMsg .= 'Postcode Missing';
		}

		if ($partnersCustomer->getCurrentPostCode() !== '' && $partnersCustomer->getCurrentPostCode() !== null && $this->CI->commonutil->validateAUPostcode($partnersCustomer->getCurrentPostCode()) === false) {

			$errorMsg .= 'Invalid Australian Postcode';
		}

		if ($errorMsg !== '') {

			//Log the data into the DB
			$dbLogger = new \Entity\Logs();
			$dbLogger->setEvent('API Rate Request Error')
					 ->setTitle(LOG_TITLE_PARTNERS_API_RATE)
					 ->setSite($this->partner->getName())
					 ->setDescription('Error in Request')
					 ->setRequest(GuzzleHttp\json_encode(['request' => $errorMsg]))
					 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
					 ->setResponse(GuzzleHttp\json_encode($errorMsg));
			$this->em->persist($dbLogger);
			$this->em->flush($dbLogger);

			$this->set_response([
									'status'  => true,
									'message' => GuzzleHttp\json_encode(['error' => $errorMsg]),
								], REST_Controller::HTTP_NOT_ACCEPTABLE);
			exit;
		}

		return true;

	}

	/**
	 * Validates if the partner has posted all the required data into json format & also checks all mandatory fields
	 * are sent over
	 */
	private function validateMandatoryNfRateData(\Entity\PartnersCustomer $partnersCustomer)
	{
		$errorMsg = '';
		if ($partnersCustomer->getFname() === '' || $partnersCustomer->getFname() === null) {
			$errorMsg .= 'First Name Missing';
		}

		if ($partnersCustomer->getLname() === '' || $partnersCustomer->getLname() === null) {
			$errorMsg .= 'Last Name Missing';
		}

		if ($partnersCustomer->getEmail() === '' || $partnersCustomer->getEmail() === null) {
			$errorMsg .= 'Email Missing';
		}

		if ($partnersCustomer->getScore() === '' || $partnersCustomer->getScore() === null) {
			$errorMsg .= 'Score Missing';
		}


		if ($partnersCustomer->getCurrentPostCode() !== '' && $partnersCustomer->getCurrentPostCode() !== null && $this->CI->commonutil->validateAUPostcode($partnersCustomer->getCurrentPostCode()) === false) {

			$errorMsg .= 'Invalid Australian Postcode';
		}

		if ($errorMsg !== '') {

			//Log the data into the DB
			$dbLogger = new \Entity\Logs();
			$dbLogger->setEvent('NF Rate Request Error')
					 ->setTitle(LOG_TITLE_PARTNERS_API_RATE)
					 ->setSite($this->partner->getName())
					 ->setDescription('Error in Request')
					 ->setRequest(GuzzleHttp\json_encode(['request' => $errorMsg]))
					 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
					 ->setResponse(GuzzleHttp\json_encode($errorMsg));
			$this->em->persist($dbLogger);
			$this->em->flush($dbLogger);

			$this->set_response([
									'status'  => true,
									'message' => GuzzleHttp\json_encode(['error' => $errorMsg]),
								], REST_Controller::HTTP_NOT_ACCEPTABLE);
			exit;
		}

		return true;

	}

	/**
	 * returns rate, loan_amount, loan_term, repayments, etc
	 */
	function generatepartnergmrlink_post()
	{
		$this->CI->load->library('PartnerGmrModel');

		$client     = new \GuzzleHttp\Client(['verify' => false]);
		$partnerGmr = new PartnerGmrModel();
		$errorMsg   = '';
		$firstName  = $this->post('firstName');
		$lastName   = $this->post('lastName');
		$middleName = $this->post('middleName');
		$email      = $this->post('email');
		$mobile     = $this->post('phone');

		//Log the data into the DB
		$dbLogger = new \Entity\Logs();

		$dbLogger->setEvent('GMR link Request Received')
				 ->setTitle(LOG_TITLE_PARTNERS_API)
				 ->setSite($this->partner->getName())
				 ->setDescription('Request Received')
				 ->setRequest(GuzzleHttp\json_encode($this->post()))
				 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
				 ->setResponse(GuzzleHttp\json_encode([]));
		$this->em->persist($dbLogger);
		$this->em->flush($dbLogger);


		if ($firstName === '' || $firstName === null) {
			$errorMsg .= 'First Name Missing';
		}

		if ($lastName === '' || $lastName === null) {
			$errorMsg .= 'Last Name Missing';
		}

		if ($email === '' || $email === null) {
			$errorMsg .= 'Email Missing';
		}

		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$errorMsg .= 'Invalid Email';
		}


		if ($mobile === '' || $mobile === null) {
			$errorMsg .= 'Mobile Missing';
		}


		if ($mobile !== '' && $mobile !== null) {
			$regx = '/04[\d]{8}/';
			preg_match_all($regx, $mobile, $matches, PREG_SET_ORDER, 0);

			if (empty($matches) || count($matches) === 0) {
				$errorMsg .= 'Invalid Mobile Number. Must start with 04';
			}

		}

		if ($errorMsg !== '') {
			//Log the data into the DB
			$dbLogger = new \Entity\Logs();
			$dbLogger->setEvent('GMR link Error')
					 ->setTitle(LOG_TITLE_PARTNERS_API)
					 ->setSite($this->partner->getName())
					 ->setDescription('Error: Invalid Missing Data')
					 ->setRequest(GuzzleHttp\json_encode($this->post()))
					 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
					 ->setResponse(GuzzleHttp\json_encode(['error' => $errorMsg]));
			$this->em->persist($dbLogger);
			$this->em->flush($dbLogger);

			$this->set_response([
									'status'  => true,
									'message' => 'Invalid Missing Data',
								], REST_Controller::HTTP_NOT_ACCEPTABLE);
			exit;
		}

		$partnerGmr->setFname($firstName)
				   ->setMname($middleName)
				   ->setLname($lastName)
				   ->setMobile($mobile)
				   ->setEmail($email)
				   ->setSource($this->partner->getName())
				   ->setApiKey($this->partner->getApiKey());

		$this->load->model('PartnerApi_model');
		/** @var PartnerGmrModel $updatedPartnerGMR */
		$updatedPartnerGMR = $this->PartnerApi_model->createVedaCustomer($partnerGmr);
		if ($updatedPartnerGMR->getHashKey() !== '' || $updatedPartnerGMR->getHashKey() !== null) {
			//Log the data into the DB
			$dbLogger = new \Entity\Logs();
			$dbLogger->setEvent('GMR link Response Construction')
					 ->setTitle(LOG_TITLE_PARTNERS_API)
					 ->setSite($this->partner->getName())
					 ->setDescription('Generate GMR link Response')
					 ->setRequest(GuzzleHttp\json_encode($this->post()))
					 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
					 ->setResponse(GuzzleHttp\json_encode(['response' => $updatedPartnerGMR]));
			$this->em->persist($dbLogger);
			$this->em->flush($dbLogger);

			$returnUrl = $this->partner->getApiKey() === NF_TEST_API ? 'https://devgetmyrate.nowfinance.com.au/?return=' : 'https://getmyrate.nowfinance.com.au/?return=';

			//Send to Parsey only in Live Environment
			if ($this->partner->getApiKey() !== NF_TEST_API) {
				//Send Data to Parsey
				$parseyData = [
					'first_name'      => strtolower($updatedPartnerGMR->getFname()),
					'middle_name'     => strtolower($updatedPartnerGMR->getMname()),
					'last_name'       => strtolower($updatedPartnerGMR->getLname()),
					'email'           => strtolower($updatedPartnerGMR->getEmail()),
					'hash_key'        => strtolower($updatedPartnerGMR->getHashKey()),
					'partner'         => strtolower($updatedPartnerGMR->getSource()),
					'partner_api_key' => strtolower($updatedPartnerGMR->getApiKey()),
					'phone'           => $updatedPartnerGMR->getMobile(),
					'return_url'      => $returnUrl . $updatedPartnerGMR->getHashKey(),
				];

				//Log the data into the DB
				$dbLogger = new \Entity\Logs();
				$dbLogger->setEvent('GMR Link Parsey Request')
						 ->setTitle(LOG_TITLE_PARTNERS_API)
						 ->setSite($this->partner->getName())
						 ->setDescription('Sending To Parsey')
						 ->setRequest(GuzzleHttp\json_encode($this->post()))
						 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
						 ->setResponse('Sending');
				$this->em->persist($dbLogger);
				$this->em->flush($dbLogger);

				$response = $client->request('POST', 'https://parsey.com/app/h/q7h0tw', ['query' => $parseyData]);

				//Log the data into the DB
				$dbLogger = new \Entity\Logs();
				$dbLogger->setEvent('Response Received From Parsey')
						 ->setTitle(LOG_TITLE_PARTNERS_API)
						 ->setSite($this->partner->getName())
						 ->setDescription('Response From Parsey')
						 ->setRequest(GuzzleHttp\json_encode($this->post()))
						 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
						 ->setResponse($response->getStatusCode());
				$this->em->persist($dbLogger);
				$this->em->flush($dbLogger);

//			$response->getBody()->rewind();
//			parse_str($response->getBody()->getContents(), $parseyResult);
			}

			//Send this to the requester

			//Log the data into the DB
			$dbLogger = new \Entity\Logs();
			$dbLogger->setEvent('Response Sent')
					 ->setTitle(LOG_TITLE_PARTNERS_API)
					 ->setSite($this->partner->getName())
					 ->setDescription('Response Sent to the requester')
					 ->setRequest(GuzzleHttp\json_encode($this->post()))
					 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
					 ->setResponse(json_encode(['nfurl' => $returnUrl . $updatedPartnerGMR->getHashKey()]));
			$this->em->persist($dbLogger);
			$this->em->flush($dbLogger);

			$this->set_response([
									'status'  => true,
									'message' => GuzzleHttp\json_encode(['nfurl' => $returnUrl . $updatedPartnerGMR->getHashKey()]),
								], REST_Controller::HTTP_OK);
		} else {
			//Log the data into the DB
			$dbLogger = new \Entity\Logs();
			$dbLogger->setEvent('GMR link Error')
					 ->setTitle(LOG_TITLE_PARTNERS_API)
					 ->setSite($this->partner->getName())
					 ->setDescription('Generate GMR link Response Error')
					 ->setRequest(GuzzleHttp\json_encode($this->post()))
					 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
					 ->setResponse(GuzzleHttp\json_encode(['error' => 'Error while creating return URL']));
			$this->em->persist($dbLogger);
			$this->em->flush($dbLogger);

			$this->set_response([
									'status'  => true,
									'message' => 'Error in customer generation',
								], REST_Controller::HTTP_INTERNAL_SERVER_ERROR);
			exit;
		}

	}

	function processaccseeker_post()
	{
		$this->CI->load->library('CustomerAccessSeekerModel');
		$this->load->model('PartnerApi_model');

		$client = new \GuzzleHttp\Client(['verify' => false]);
		/** @var CustomerAccessSeekerModel $customer */
		$customer = new CustomerAccessSeekerModel();
		/** @var CustomerAccessSeekerModel $customerPartner */
		$customerPartner = new CustomerAccessSeekerModel();

		/** @var VedaCustomerModel $vedaCustomer */
		$vedaCustomer = new VedaCustomerModel();

		$errorMsg   = '';
		$session_id = md5(date("d:m:Y") . session_id() . date("H:i:s"));

		$driversLicenceNumber   = null;
		$driversLicenceState    = null;
		$driversLicenceExpiryDb = null;

		$passportNumber   = null;
		$passportExpiryDb = null;

		//Get all the posted details.

		$customerID       = $this->post('insertid');
		$firstName        = $this->post('firstname');
		$lastName         = $this->post('lastname');
		$middleName       = $this->post('middleName');
		$email            = $this->post('email');
		$mobile           = $this->post('mobile');
		$gender           = $this->post('gender');
		$dateOfBirthDb    = $this->post('dateOfBirthDb');
		$dateOfBirth      = $this->post('dateOfBirth');
		$address          = $this->post('address');
		$unitNumber       = $this->post('unit_number');
		$streetNo         = $this->post('street_no');
		$streetName       = $this->post('street_name');
		$streetType       = $this->post('street_type');
		$suburb           = $this->post('suburb');
		$state            = $this->post('state');
		$postcode         = $this->post('postcode');
		$addressNotFound  = $this->post('addressNotFound');
		$more3years       = $this->post('more3years');
		$prevsUnitNumber  = $this->post('prevs_unit_number');
		$prevStreetNo     = $this->post('prev_street_no');
		$prevStreetName   = $this->post('prev_street_name');
		$prevStreetType   = $this->post('prev_street_type');
		$prevSuburb       = $this->post('prev_suburb');
		$prevState        = $this->post('prev_state');
		$prevPostCode     = $this->post('prev_post_code');
		$primaryType      = $this->post('primarytype');
		$maritalStatus    = $this->post('maritalStatus');
		$source           = $this->post('traffic_source');
		$sourceMedium     = $this->post('source_medium');
		$ipAddress        = $this->post('ip_address');
		$step3ViewDisplay = $this->post('step3ViewDisplay');

		$idNumber = null;
		$idState  = null;
		$idExpiry = null;

		if (strtolower($primaryType) === strtolower('DriversLicence')) {
			$idNumber = $this->post('driversLicenceNumber');
			$idState  = $this->post('driversLicenceState');
			$idExpiry = $this->post('driversLicenceExpiryDb');
		}

		if (strtolower($primaryType) === strtolower('Passport')) {
			$idNumber = $this->post('passportNumber');
			$idExpiry = $this->post('passportExpiryDb');
		}


		$loanAmount      = $this->post('amount');
		$loanPurpose     = $this->post('loanPurpose');
		$loanPurposeText = $this->post('loanPurposeText');


		$discountType   = $this->post('discountType');
		$couponCode     = $this->post('couponCode') ?? '';
		$channelPartner = $this->post('channel_partner_id') ?? 'NF';

		$referredFirstName = $this->post('referredFirstname');
		$referredLastName  = $this->post('referredLastname');
		$referredEmail     = $this->post('referredEmail');
		$formCheck         = $this->post('formCheck');

		$couponCodeData     = null;
		$isJointApplication = 0;

		//Log the data into the DB
		$dbLogger = new \Entity\Logs();

		$dbLogger->setEvent('Access Seeker Request')
				 ->setTitle($email)
				 ->setSite($this->partner->getName())
				 ->setDescription('Request Received')
				 ->setRequest(GuzzleHttp\json_encode($this->post()))
				 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
				 ->setResponse(GuzzleHttp\json_encode([]));
		$this->em->persist($dbLogger);
		$this->em->flush($dbLogger);

		// check valid coupon code
		if ($couponCode) {
			$couponRepo     = $this->em->getRepository(\Entity\CouponDiscount::class);
			$couponCodeData = $couponRepo->findOneBy(['coupon_code' => $couponCode]);

			if ($couponCodeData instanceof \Entity\CouponDiscount === false) {
				//Coupon not valid
				//Log the data into the DB
				$dbLogger = new \Entity\Logs();

				$dbLogger->setEvent('Access Seeker Request Invalid Coupon Code')
						 ->setTitle($email)
						 ->setSite($this->partner->getName())
						 ->setDescription($couponCode)
						 ->setRequest(GuzzleHttp\json_encode($this->post()))
						 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
						 ->setResponse(GuzzleHttp\json_encode([]));
				$this->em->persist($dbLogger);
				$this->em->flush($dbLogger);

				$this->set_response([
										'status'  => false,
										'message' => 'Invalid Coupon Code:' . $couponCode,
									], REST_Controller::HTTP_OK);
				exit;
			}

		}


		$isJointApplication = $this->post('jointApplication');
		$prevAddress        = '';
		$address            = ($unitNumber ? ($unitNumber . '/') : '') . $streetNo . ' ' . $streetName . ' ' . $streetType;
		if ($prevStreetName) {
			$prevAddress = ($prevsUnitNumber ? ($prevsUnitNumber . '/') : '') . $prevStreetNo . ' ' . $prevStreetName . ' ' . $prevStreetType;
		}

		$hashRandom = ($customerID !== '' && $customerID !== null) ? $customerID : random_int(0, 1000);
		$hash       = md5("how now finance" . $hashRandom); //same as our GMR

		$customer->setFname($firstName)
				 ->setMname($middleName)
				 ->setLname($lastName)
				 ->setApiKey($this->partner->getApiKey())
				 ->setEmail($email)
				 ->setMobile($mobile)
				 ->setDob($dateOfBirthDb)
				 ->setCouponCode($couponCode)
				 ->setAddress($address)
				 ->setCurrentUnitNumber($unitNumber)
				 ->setCurrentStreetName($streetName)
				 ->setCurrentStreetNumber($streetNo)
				 ->setCurrentStreetType($streetType)
				 ->setCurrentPostCode($postcode)
				 ->setCurrentState($state)
				 ->setCurrentSuburb($suburb)
				 ->setPreviousAddress($prevAddress)
				 ->setPreviousUnitNumber($prevsUnitNumber)
				 ->setPreviousStreetName($prevStreetName)
				 ->setPreviousStreetNumber($streetNo)
				 ->setPreviousStreetType($streetType)
				 ->setPreviousPostCode($prevPostCode)
				 ->setPreviousState($prevState)
				 ->setPreviousSuburb($prevSuburb)
				 ->setIdNumber($idNumber)
				 ->setIdExpiry($idExpiry)
				 ->setHashKey($hash)
				 ->setIdState($idState)
				 ->setIdType($primaryType)
				 ->setCustomerId($customerID)
				 ->setGender(strtolower($gender))
				 ->setMaritalStatus($maritalStatus)
				 ->setLoanAmount($loanAmount)
				 ->setLoanPurpose($loanPurpose)
				 ->setChannelPartnerId($channelPartner)
				 ->setSource($source)
				 ->setSourceMedium($sourceMedium)
				 ->setIpAddress($ipAddress)
				 ->setReferredEmail($referredEmail)
				 ->setReferredLastName($referredLastName)
				 ->setReferredFirstName($referredFirstName)
				 ->setApiPartner($this->partner);

		//Get Score for Customer.
		$this->load->model('creditbureaus');
		$customerScoreResult = $this->creditbureaus->processGetScore($customer);
		$mainCustomer        = $customerScoreResult;

		//If its a Joint Application
		if ($isJointApplication === true) {
			$partnerFirstName       = $this->post('partnerFirstname');
			$partnerLastName        = stripslashes($this->post('partnerLastname'));
			$partnerMiddleName      = stripslashes($this->post('partnerMiddlename'));
			$partnerEmail           = $this->post('partnerEmail');
			$partnerMobile          = $this->post('partnerMobile');
			$partnerGender          = $this->post('partnerGender');
			$partnerDateOfBirthDb   = $this->post('partnerDateOfBirthDb');
			$partnerDateOfBirth     = $this->post('partnerDateOfBirthDb');
			$partnerAddress         = $address; //$this->post('partnerAddress');
			$partnerUnitNumber      = $unitNumber; //$this->post('partnerUnit_number');
			$partnerStreetNo        = $streetNo; //$this->post('partnerStreet_no');
			$partnerStreetName      = $streetName; //$this->post('partnerStreet_name');
			$partnerStreetType      = $streetType; //$this->post('partnerStreet_type');
			$partnerSuburb          = $suburb; //$this->post('partnerSuburb');
			$partnerState           = $state; //$this->post('partnerState');
			$partnerPostcode        = $postcode; //$this->post('partnerPostcode');
			$partnerAddressNotFound = $this->post('addressNotFound');
			$partnerMore3years      = $this->post('partnerMore3years');
			$partnerPrevsUnitNumber = $this->post('partnerPrevs_unit_number');
			$partnerPrevStreetNo    = $this->post('partnerPrev_street_no');
			$partnerPrevStreetName  = $this->post('partnerPrev_street_name');
			$partnerPrevStreetType  = $this->post('partnerPrev_street_type');
			$partnerPrevSuburb      = $this->post('partnerPrev_suburb');
			$partnerPrevState       = $this->post('partnerPrev_state');
			$partnerPrevPostCode    = $this->post('partnerPrev_post_code');
			$partnerPrimaryType     = $this->post('partnerPrimarytype');
			$partnerMaritalStatus   = $this->post('maritalStatus');
			$partnerSource          = $this->post('traffic_source');
			$partnerSourceMedium    = $this->post('source_medium');
			$partnerIpAddress       = $this->post('ip_address');

			$partnerPrevAddress = $this->post('partnerPrev_address');
			$partnerAddress     = ($partnerUnitNumber ? ($partnerUnitNumber . '/') : '') . $partnerStreetNo . ' ' . $partnerStreetName . ' ' . $partnerStreetType;
			if ($partnerPrevStreetName) {
				$partnerPrevAddress = ($partnerPrevsUnitNumber ? ($partnerPrevsUnitNumber . '/') : '') . $partnerPrevStreetNo . ' ' . $partnerPrevStreetName . ' ' . $partnerPrevStreetType;
			}

			$partnerIdNumber = null;
			$partnerIdState  = null;
			$partnerIdExpiry = null;

			if (strtolower($partnerPrimaryType) === strtolower('DriversLicence')) {
				$partnerIdNumber = $this->post('partnerDriversLicenceNumber');
				$partnerIdState  = $this->post('partnerDriversLicenceState');
				$partnerIdExpiry = $this->post('partnerDriversLicenceExpiryDb');
			}

			if (strtolower($partnerPrimaryType) === strtolower('Passport')) {
				$partnerIdNumber = $this->post('partnerPassportNumber');
				$partnerIdExpiry = $this->post('partnerPassportExpiryDb');
			}

			$customerPartner->setFname($partnerFirstName)
							->setMname($partnerMiddleName)
							->setLname($partnerLastName)
							->setApiKey($this->partner->getApiKey())
							->setEmail($partnerEmail)
							->setMobile($partnerMobile)
							->setDob($partnerDateOfBirthDb)
							->setCouponCode($couponCode)
							->setAddress($partnerAddress)
							->setCurrentUnitNumber($partnerUnitNumber)
							->setCurrentStreetName($partnerStreetName)
							->setCurrentStreetNumber($partnerStreetNo)
							->setCurrentStreetType($partnerStreetType)
							->setCurrentPostCode($partnerPostcode)
							->setCurrentState($partnerState)
							->setCurrentSuburb($partnerSuburb)
							->setPreviousAddress($partnerPrevAddress)
							->setPreviousUnitNumber($partnerPrevsUnitNumber)
							->setPreviousStreetName($partnerStreetName)
							->setPreviousStreetNumber($partnerPrevStreetNo)
							->setPreviousStreetType($partnerPrevStreetType)
							->setPreviousPostCode($partnerPrevPostCode)
							->setPreviousState($partnerPrevState)
							->setPreviousSuburb($partnerPrevSuburb)
							->setIdNumber($partnerIdNumber)
							->setIdExpiry($partnerIdExpiry)
							->setHashKey($hash)
							->setIdState($partnerIdState)
							->setIdType($partnerPrimaryType)
							->setCustomerId($customerID)
							->setGender(strtolower($partnerGender))
							->setMaritalStatus($maritalStatus)
							->setLoanAmount($loanAmount)
							->setLoanPurpose($loanPurpose)
							->setChannelPartnerId($channelPartner)
							->setSource($source)
							->setSourceMedium($sourceMedium)
							->setIpAddress($ipAddress)
							->setReferredEmail($referredEmail)
							->setReferredLastName($referredLastName)
							->setReferredFirstName($referredFirstName)
							->setApiPartner($this->partner);

			/** @var CustomerAccessSeekerModel $customerPartnerScoreResult */
			$customerPartnerScoreResult = $this->creditbureaus->processGetScore($customerPartner);

			//check whose score can be considered? main or partner
			// if partner score was from Veda and main applicant was from DB then make main score to be partner score
			// or if partner score is higher and not 'DB' then adjust main score to be partner score
			if (((int)$customerScoreResult->getCrScore() === -5500 || (int)$customerPartnerScoreResult->getApiCheck() !== DNB_TEXT) && ($customerScoreResult->getApiCheck() === DNB_TEXT || $customerPartnerScoreResult->getCrScore() > $customerScoreResult->getCrScore())) {
				$mainCustomer = $customerPartnerScoreResult;

			}
			// check if partner not eligible and if so adjust main scores accordingly
			if ($customerPartnerScoreResult->getCrScore() && (($customerPartnerScoreResult->getScore() >= 0 && $customerPartnerScoreResult->getScore() !== ''
															   && $customerPartnerScoreResult->getScore() < 530
															   && ($customerScoreResult->getApiCheck() === VEDA_TEXT || $customerScoreResult->getApiCheck() === EXPERIAN_TEXT))
															  || ($customerPartnerScoreResult->getScore() >= 0 && $customerPartnerScoreResult->getScore() <= 479))) {
				// note score_old must be above 480 otherwise not eligible
				$mainCustomer = $customerPartnerScoreResult;
			}

		}

		/**
		 * @var CustomerAccessSeekerModel $mainCustomer
		 */

		$mainCustomer = $this->PartnerApi_model->getDirectInterestRate($mainCustomer);


		$vedaCustomer->setCustomerId($customerID)
					 ->setCustomer($customer)
					 ->setCustomerPartner($customerPartner)
					 ->setIsJointApplication($isJointApplication)
					 ->setMainScore($mainCustomer->getCrScore())
					 ->setInterestRate($mainCustomer->getInterestRate())
					 ->setComparisonRate($mainCustomer->getComparisonRate())
					 ->setAppStatus($mainCustomer->getAppStatus())
					 ->setFormCheck($formCheck)
					 ->setStep3Display($step3ViewDisplay)
					 ->setPartnerId($mainCustomer->getChannelPartnerId())
					 ->setFStatus('F');


		echo '<pre>';
		echo 'Veda customer';
		var_dump($vedaCustomer);
		echo '</pre>';
		exit;

		//Update or Create Customer


	}

	/**
	 * returns rate, loan_amount, loan_term, repayments, etc
	 */
	function getnfrate_post()
	{
		$this->load->model('PartnerApi_model');
		$client          = new \GuzzleHttp\Client(['verify' => false]);
		$partnerCustomer = new \Entity\PartnersCustomer();
		$rawDob          = $this->post('date_of_birth');
		$dob             = null;
		$validDob        = null;
		if ($rawDob !== '' && $rawDob !== null) {
			$validDob     = DateTime::createFromFormat('d/m/Y', $rawDob)->format('Y-m-d');
			$formattedDOB = DateTime::createFromFormat('Y-m-d', $validDob);
		} else {
			$formattedDOB = null;
		}

		$partnerCustomer->setFname($this->post('first_name'))
						->setMname($this->post('middle_name'))
						->setLname($this->post('last_name'))
						->setEmail($this->post('email_address'))
						->setScore($this->post('score'))
						->setCouponCode($this->post('coupon_code'))
						->setPartner($this->partner);

		$rateResult = null;

//		Log the data into the DB
		$dbLogger = new \Entity\Logs();
		$dbLogger->setEvent('API Rate Request')
				 ->setTitle(LOG_TITLE_PARTNERS_API_RATE)
				 ->setSite($this->partner->getName())
				 ->setDescription('Incoming API Request to get rate.')
				 ->setRequest(GuzzleHttp\json_encode(['request' => $this->post()]))
				 ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
				 ->setResponse(GuzzleHttp\json_encode([]));
		$this->em->persist($dbLogger);
		$this->em->flush($dbLogger);


		//Score Cut off Check
		if ($partnerCustomer->getScore() <= 539) {
			$data = ['status' => 'failed', 'message' => 'Not Eligible'];
		} else {
			//Get all posted data and validate
			$result     = $this->validateMandatoryNfRateData($partnerCustomer);
			$rateResult = null;
			if ($result) {
				/**
				 * @var \Entity\PartnersCustomer $partnersCustomer
				 */
				$partnersCustomer = $this->PartnerApi_model->getPartnerInterestRateOnly($partnerCustomer);

				//Log the data into the DB
				$dbLogger1 = new \Entity\Logs();
				$dbLogger1->setEvent('API Rate Response')
						  ->setTitle(LOG_TITLE_PARTNERS_API_RATE)
						  ->setSite($this->partner->getName())
						  ->setDescription('Response Prepared to send')
						  ->setRequest(GuzzleHttp\json_encode(['request' => $this->post()]))
						  ->setCreatedon(new \DateTime('now'), new DateTimeZone('Australia/Melbourne'))
						  ->setResponse(GuzzleHttp\json_encode(['response' => (array)$partnersCustomer]));

				$this->em->persist($dbLogger1);
				$this->em->flush($dbLogger1);

				$this->em->flush();

				$apiReturnData = [
					'interestRate'   => $partnersCustomer->getInterestRate(),
					'comparisonRate' => $partnersCustomer->getComparisonRate(),
					'score'          => $partnersCustomer->getScore(),
				];

				$data = ['status' => 'success', 'message' => GuzzleHttp\json_encode($apiReturnData)];

			} else {
				$data = ['status' => 'failed', 'message' => 'Not Eligible'];
			}
		}

		$this->set_response($data, REST_Controller::HTTP_OK);
	}

}
