<?php

/******************************************************************************************

  Christopher Lewis, 2010

  Reference Documentation: http://support.chargify.com/faqs/api/api-authentication

******************************************************************************************/

class ChargifyConnector
{
  protected $api_key        = 'LgMqA223nCdLaER6CPy0';
  protected $domain         = 'scalr';
  
  protected $test_domain    = 'ENTER THE TEST DOMAIN HERE';
  protected $test_api_key   = 'ENTER TEST API KEY HERE';
  
  protected $active_api_key;
  protected $active_domain;
  protected $test_mode;
  
  public function __construct($test_mode = false)
  {
    $this->test_mode = $test_mode;
    
    if($test_mode)
    {
      $this->active_api_key = $this->test_api_key;
      $this->active_domain  = $this->test_domain;
    }
    else
    {
      $this->active_api_key = $this->api_key;
      $this->active_domain  = $this->domain;
    }
  }
  
  /*
  public function retrieveAllCustomersXML($page_num = 1)
  {
    return $this->sendRequest('/customers.xml?page=' . $page_num);
  }
  
  public function retrieveCustomerXMLByID($id)
  {
    return $this->sendRequest('/customers/' . $id . '.xml');
  }
  
  public function retrieveCustomerXMLByReference($ref)
  {
    return $this->sendRequest('/customers/lookup.xml?reference=' . $ref);
  }
  
  public function retrieveSubscriptionsXMLByCustomerID($id)
  {
    return $this->sendRequest('/customers/' . $id . '/subscriptions.xml');
  }
  
  public function retrieveAllProductsXML()
  {
    return $this->sendRequest('/products.xml');
  }
  
  public function createCustomerAndSubscription($post_xml)
  {
    $xml = $this->sendRequest('/subscriptions.xml', $post_xml);

    $tree = new SimpleXMLElement($xml);

    if(isset($tree->error)) { return $tree; }
    else { $subscription = new ChargifySubscription($tree); }
    
    return $subscription;
  }
  
  public function getAllCustomers()
  {
    $xml = $this->retrieveAllCustomersXML();
    
    $all_customers = new SimpleXMLElement($xml);
   
    $customer_objects = array();
    
    foreach($all_customers as $customer)
    {
      $temp_customer = new ChargifyCustomer($customer);
      array_push($customer_objects, $temp_customer);
    }
    
    return $customer_objects;
  }
  

  public function getCustomerByID($id)
  {
    $xml = $this->retrieveCustomerXMLByID($id);
    
    $customer_xml_node = new SimpleXMLElement($xml);
    
    $customer = new ChargifyCustomer($customer_xml_node);
    
    return $customer;
  }
  
  public function getCustomerByReference($ref)
  {
    $xml = $this->retrieveCustomerXMLByReference($ref);

    $customer_xml_node = new SimpleXMLElement($xml);
    
    $customer = new ChargifyCustomer($customer_xml_node);
        
    return $customer;
  }
  
  public function getSubscriptionsByCustomerID($id)
  {
    $xml = $this->retrieveSubscriptionsXMLByCustomerID($id);
    
    $subscriptions = new SimpleXMLElement($xml);
    
    $subscription_objects = array();
    
    foreach($subscriptions as $subscription)
    {
      $temp_sub = new ChargifySubscription($subscription);
      
      array_push($subscription_objects, $temp_sub);
    }
    
    return $subscription_objects;
  }
  
  public function getAllProducts()
  {
    $xml = $this->retrieveAllProductsXML();
    
    $all_products = new SimpleXMLElement($xml);
   
    $product_objects = array();
    
    foreach($all_products as $product)
    {
      $temp_product = new ChargifyProduct($product);
      array_push($product_objects, $temp_product);
    }
    
    return $product_objects;
  }
  */
  
  
	public function cancelSubscription($subscription_id, $reason = 'Canceling the subscription via the API. Requested by customer.')
  	{
		$data = '<?xml version="1.0" encoding="UTF-8"?>
      <subscription>
        <cancellation_message>
          '.$reason.'
        </cancellation_message>
      </subscription>';
  		
  		$r = $this->sendRequest("/subscriptions/{$subscription_id}.xml", 'DELETE', $data);
		
  		if ($r->getResponseCode() == 200)
	  	{
	    	return true;
	  	}
	  	else
	  	{
	  		if ($r->getResponseCode() == 422)
	  		{
		  		$xml_node = new SimpleXMLElement($r->getResponseBody());
		  		if ($xml_node->error)
		  			return (string)$xml_node->error;
	  		}
	  		
	  		throw new Exception("Cannot get subscription info. Response code: ".$r->getResponseCode().". Response body: ".$r->getResponseBody());
	  	}
  	}
  
	/**
   * 
   * @param integer $subscription_id
   * @return ChargifySubscription
   */
  	public function getCustomerSubscriptionByCId($client_id)
  	{
  		$r = $this->sendRequest("/customers/{$client_id}/subscriptions.xml", 'GET');
  		
	  	if ($r->getResponseCode() == 200)
	  	{	  		
	  		$xml_node = new SimpleXMLElement($r->getResponseBody());
	    	$subscription = new ChargifySubscription($xml_node);
	    	return $subscription;
	  	}
	  	else
	  	{
	  		if ($r->getResponseCode() == 422)
	  		{
		  		$xml_node = new SimpleXMLElement($r->getResponseBody());
		  		if ($xml_node->error)
		  			return (string)$xml_node->error;
	  		}
	  		
	  		throw new Exception("Cannot get subscription info. Response code: ".$r->getResponseCode().". Response body: ".$r->getResponseBody());
	  	}
  	}
  	
  /**
   * 
   * @param integer $subscription_id
   * @return ChargifySubscription
   */
  	public function getCustomerSubscription($subscription_id)
  	{
  		$r = $this->sendRequest("/subscriptions/{$subscription_id}.xml", 'GET');
  		
	  	if ($r->getResponseCode() == 200)
	  	{
	    	$xml_node = new SimpleXMLElement($r->getResponseBody());
	    	$subscription = new ChargifySubscription($xml_node);
	    	return $subscription;
	  	}
	  	else
	  	{
	  		if ($r->getResponseCode() == 422)
	  		{
		  		$xml_node = new SimpleXMLElement($r->getResponseBody());
		  		if ($xml_node->error)
		  			return (string)$xml_node->error;
	  		}
	  		
	  		throw new Exception("Cannot get subscription info. Response code: ".$r->getResponseCode().". Response body: ".$r->getResponseBody());
	  	}
  	}
  
  
/*	public function listComponents($product_family_id) {
  	
  	$r = $this->sendRequest("/product_families/{$product_family_id}/components.xml", false);
  	
  	if ($r->getResponseCode() == 200)
  	{
    	var_dump($r->getResponseBody());
  		
  		return true;
  	}
  	else
  	{
  		if ($r->getResponseCode() == 422)
  		{
	  		$xml_node = new SimpleXMLElement($r->getResponseBody());
	  		if ($xml_node->error)
	  			return (string)$xml_node->error;
  		}
  		
  		var_dump($r->getResponseBody());
  		
  		throw new Exception("Cannot create usage. Response code: ".$r->getResponseCode().". Response body: ".$r->getResponseBody());
  	}
  	
  }
  
  public function createMeteredUsage($subscription_id, $component_id, $quantity, $memo) {
  	
  	$post_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
        <usage>
          <quantity>{$quantity}</quantity>
          <memo>{$memo}</memo>
        </usage>
  	";
  	
  	var_dump("/subscriptions/{$subscription_id}/components/{$component_id}/usages.xml");
  	var_dump($post_xml);
  	
  	$r = $this->sendRequest("/subscriptions/{$subscription_id}/components/{$component_id}/usages.xml", $post_xml);
  	
  	if ($r->getResponseCode() == 200)
  	{
    	var_dump($r->getResponseBody());
  		
  		return true;
  	}
  	else
  	{
  		if ($r->getResponseCode() == 422)
  		{
	  		$xml_node = new SimpleXMLElement($r->getResponseBody());
	  		if ($xml_node->error)
	  			return (string)$xml_node->error;
  		}
  		
  		var_dump($r->getResponseBody());
  		
  		throw new Exception("Cannot create usage. Response code: ".$r->getResponseCode().". Response body: ".$r->getResponseBody());
  	}
  	
  }
  */
  
	public function reactivateSubscription($subscription_id)
	{
  		$r = $this->sendRequest("/subscriptions/{$subscription_id}/reactivate.xml", 'PUT');
  		
	  	if ($r->getResponseCode() == 200)
	  	{
	    	$xml_node = new SimpleXMLElement($r->getResponseBody());
	    	$subscription = new ChargifySubscription($xml_node);
	    	return $subscription;
	  	}
	  	else
	  	{
	  		if ($r->getResponseCode() == 422)
	  		{
		  		$xml_node = new SimpleXMLElement($r->getResponseBody());
		  		if ($xml_node->error)
		  			return (string)$xml_node->error;
	  		}
	  		
	  		throw new Exception("Cannot get subscription info. Response code: ".$r->getResponseCode().". Response body: ".$r->getResponseBody());
	  	}
	}
  	
  public function upgradeSubscription($subscription_id, $product_code) {
	
  	$post_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
        <product_handle>{$product_code}</product_handle>
	";
  	
  	$r = $this->sendRequest("/subscriptions/{$subscription_id}/migrations.xml", 'POST', $post_xml);
  	
  	if ($r->getResponseCode() == 200)
  	{
    	$xml_node = new SimpleXMLElement($r->getResponseBody());
    	$subscription = new ChargifySubscription($xml_node);
    	return $subscription;
  	}
  	else
  	{
  		if ($r->getResponseCode() == 422)
  		{
	  		$xml_node = new SimpleXMLElement($r->getResponseBody());
	  		if ($xml_node->error)
	  			return (string)$xml_node->error;
  		}
  		
  		throw new Exception("Cannot add new client. Response code: ".$r->getResponseCode().". Response body: ".$r->getResponseBody());
  	}
  }
  
  /**
   * @return ChargifySubscription
   * @param unknown_type $scalr_client_id
   * @param unknown_type $product_code
   * @param unknown_type $cc_number
   * @param unknown_type $cc_exp_month
   * @param unknown_type $cc_exp_year
   * @param unknown_type $cc_cvv
   */
  public function createSubscription($scalr_client_id, $product_code, $cc_number, $cc_exp_month, $cc_exp_year, $cc_cvv, $clientinfo = false) {

  	if ($clientinfo)
  	{
  		$post_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
	        <subscription>
	          <product_handle>{$product_code}</product_handle>
	          <customer_attributes>
	            <email>{$clientinfo['email']}</email>
				<first_name>{$clientinfo['first_name']}</first_name>
				<last_name>{$clientinfo['last_name']}</last_name>
				<organization>{$clientinfo['org']}</organization>
				<reference>{$clientinfo['id']}</reference>
	          </customer_attributes>
	          <credit_card_attributes>
	            <full_number>{$cc_number}</full_number>
	            <expiration_month>{$cc_exp_month}</expiration_month>
	            <expiration_year>{$cc_exp_year}</expiration_year>
	            <cvv>{$cc_cvv}</cvv>
	          </credit_card_attributes>
	        </subscription>
	  	";
  	}
  	else
  	{
	  	$post_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
	        <subscription>
	          <product_handle>{$product_code}</product_handle>
	          <customer_reference>{$scalr_client_id}</customer_reference>
	          <credit_card_attributes>
	            <full_number>{$cc_number}</full_number>
	            <expiration_month>{$cc_exp_month}</expiration_month>
	            <expiration_year>{$cc_exp_year}</expiration_year>
	            <cvv>{$cc_cvv}</cvv>
	          </credit_card_attributes>
	        </subscription>
	  	";
  	}
  	
  	$r = $this->sendRequest('/subscriptions.xml', 'POST', $post_xml);
  	
  	if ($r->getResponseCode() == 201)
  	{
    	$xml_node = new SimpleXMLElement($r->getResponseBody());
    	$subscription = new ChargifySubscription($xml_node);
    	return $subscription;
  	}
  	else
  	{
  		if ($r->getResponseCode() == 422)
  		{
	  		$xml_node = new SimpleXMLElement($r->getResponseBody());
	  		if ($xml_node->error)
	  			return (string)$xml_node->error;
  		}
  		
  		throw new Exception("Cannot add new client. Response code: ".$r->getResponseCode().". Response body: ".$r->getResponseBody());
  	}
  }
  
  /**
   * @return ChargifyCustomer
   */
  public function createCustomer($scalr_client_id, $email, $first_name, $last_name, $org = "") {
    
  	$post_xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
	<customer>
		<email>{$email}</email>
		<first_name>{$first_name}</first_name>
		<last_name>{$last_name}</last_name>
		<organization>{$org}</organization>
		<reference>{$scalr_client_id}</reference>
	</customer>";
  	
  	$r = $this->sendRequest('/customers.xml', 'POST', $post_xml);
  	
  	if ($r->getResponseCode() == 201)
  	{
    	$xml_node = new SimpleXMLElement($r->getResponseBody());
    	$customer = new ChargifyCustomer($xml_node);
    	return $customer;
  	}
  	else
  	{
  		if ($r->getResponseCode() == 422)
  		{
	  		$xml_node = new SimpleXMLElement($r->getResponseBody());
	  		if ($xml_node->error)
	  			return (string)$xml_node->error;
  		}
  		
  		throw new Exception("Cannot add new client. Response code: ".$r->getResponseCode().". Response body: ".$r->getResponseBody());
  	}
  }

	protected function sendRequest($uri, $method, $data = null) 
	{	
	  	try
	  	{
		  	$request = new HttpRequest("https://{$this->active_domain}.chargify.com{$uri}");
		  	$request->setHeaders(array('Content-Type' => 'application/xml'));
		  	$request->setOptions(array(
		  		'httpauth'	=> "{$this->active_api_key}:x",
		  		'timeout'	=> 45,
		  		'connecttimeout' => 45
		  	));
		  	
		  	$request->setMethod(constant("HTTP_METH_{$method}"));
		  	
		  	if ($data)
		  		$request->setRawPostData($data);
	  	}
	  	catch(Exception $e)
	  	{
			//TODO:
	  		throw $e;
	  	}
	
	  		
	  	$request->send();
	  	
	  	return $request;	
	}
}
?>