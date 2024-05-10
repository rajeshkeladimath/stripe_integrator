<?php

require_once($_SERVER["DOCUMENT_ROOT"] ."/libraries/stripe-php-master/init.php");

Class StripeInterface
{

  private static $stripeInterface = NULL;
 
  private static  $apiKey = null;

  public function __construct()
  {
  }

  public static function setApiKey($apiKeyValue)
  {
    self::$apiKey = $apiKeyValue;
  }

  public static function authorizeFromEnv()
  {
      \Stripe\Stripe::setApiKey(self::$apiKey);
  }

  /**
   * Retrieve customer
   */
  public static function retrieveCustomer($customerId)
  {
    self::authorizeFromEnv();
    try
    {
      $customer = \Stripe\Customer::retrieve($customerId);
    }
    catch (\Stripe\Error\Base $e) {
      $body = $e->getJsonBody();
      $err  = $body['error'];
      if($err != NULL && strlen($err['message']) > 0)
      {
        drupal_set_message($err['message'], 'warning');
      }
      return NULL;
    }
    catch (Exception $e)
    {
      drupal_set_message(t('We are sorry. There was an error storing your '.$source['source']['base'].' right now'), 'warning');
      return NULL;
    } 

    return $customer;
  }


 /**
   * Retrieve customer for quickpay and saving subscribing the plan for that
   */
  public static function retrieveCustomerQuick($customerId,$tokenCard)
  {
    self::authorizeFromEnv();
    try
    {
      $customer = \Stripe\Customer::retrieve($customerId);
      $customer->source = $tokenCard; // obtained using Stripe.js at client side
           $customer->save(); // save the source

    }
    catch (\Stripe\Error\Base $e) {
      $body = $e->getJsonBody();
      $err  = $body['error'];
  drupal_set_message("cusotm error " .$e->getMessage());
      if($err != NULL && strlen($err['message']) > 0)
      {
        drupal_set_message($err['message'], 'warning');
      }
      return NULL;
    }
    catch (Exception $e)
    {
      drupal_set_message(t('We are sorry. There was an error storing your '.$source['source']['base'].' right now'), 'warning');
      return NULL;
    }

    return $customer;
  }








  // Create a plan
  public static function createPlan($parameters)
  {
    self::authorizeFromEnv();
    try
    {
      $plan = \Stripe\Plan::create($parameters);
      return $plan;
    }
    catch (\Stripe\Error\Base $e) {
      $body = $e->getJsonBody();
      $err  = $body['error'];
      if($err != NULL && strlen($err['message']) > 0)
      {
        drupal_set_message($err['message'], 'warning');
      }
      return NULL;
    }
    catch (Exception $e)
    {
      drupal_set_message(t('We are sorry. There was an error setting recurring payment right now'), 'warning');
      return NULL;
    } 

  }

  // Subscribe a customer to a plan
  public static function subscribeCustomerToPlan($customerId, $planId, $metadata)
  {
    self::authorizeFromEnv();
    try
    {
      $subscription = \Stripe\Subscription::create(array(
        "customer" => $customerId,
        "plan" => $planId,
        "metadata" => $metadata,
        ));
      return $subscription;
    }
    catch (\Stripe\Error\Base $e) {
      $body = $e->getJsonBody();
      $err  = $body['error'];
      if($err != NULL && strlen($err['message']) > 0)
      {
        drupal_set_message($err['message'], 'warning');
      }
      return NULL;
    }
    catch (Exception $e)
    {
      drupal_set_message(t('We are sorry. There was an error setting recurring payment right now'), 'warning');
      return NULL;
    } 
  }
  /**
   * Delete a Card or Account
   */

   public static function deletePaymentProfile($customer, $sourceId)
   {
     self::authorizeFromEnv();
   
     $source = null;
     try
     {
       $source = $customer->sources->retrieve($sourceId);
       $response = $source->delete();
       if($response['deleted'] == 'true')
       {
         return TRUE;
       }
     }
     catch (Exception $e)
     {
       drupal_set_message(t('We are sorry. There was an error deleting the entry right now'), 'warning');
       return FALSE;
     } 

     return FALSE;
   }

  /**
   * Update a Card or Account
   */

   public static function updatePaymentProfile($paymentSource)
   {
     self::authorizeFromEnv();
    
     try
     {
       $response = $paymentSource->save();
       if ($response == null)
       {
         return FALSE; 
       }
       else
       {
         return TRUE;
       }
     }
     catch (Exception $e)
     {
       drupal_set_message(t('We are sorry. There was an error deleting the entry right now'), 'warning');
       return FALSE;
     } 

     return FALSE;
   }

  /**
    * Create a valid test customer.
    */
  public static function createCustomer(array $attributes = array())
  {
    self::authorizeFromEnv();

      try {
        $customer = \Stripe\Customer::create($attributes);
        return $customer;
      } catch(\Stripe\Error\Base $e) {
        // Since it's a decline, \Stripe\Error\Card will be caught
        $body = $e->getJsonBody();
	//    drupal_set_message($e->getMessage());
	\Drupal::messenger()->addMessage($e->getMessage());
        $err  = $body['error'];
        print('Status is:' . $e->getHttpStatus() . "\n");
        print('Type is:' . $err['type'] . "\n");
        print('Code is:' . $err['code'] . "\n");
        // param is '' in this case
        print('Param is:' . $err['param'] . "\n");
        print('Message is:' . $err['message'] . "\n");
      
      } catch (\Stripe\Error\RateLimit $e) {
        // Too many requests made to the API too quickly
      } catch (\Stripe\Error\InvalidRequest $e) {
        // Invalid parameters were supplied to Stripe's API
      } catch (\Stripe\Error\Authentication $e) {
        // Authentication with Stripe's API failed
        // (maybe you changed API keys recently)
      } catch (\Stripe\Error\ApiConnection $e) {
        // Network communication with Stripe failed
      } catch (\Stripe\Error\Base $e) {
        // Display a very generic error to the user, and maybe send
        // yourself an email
      } catch (Exception $e) {
        // Something else happened, completely unrelated to Stripe
        return NULL;
      }
  }

  /**
   * Create a Card or Bank Account for a customer already present
   */

  public static function createPaymentProfile($customerId, $source)
  {
    self::authorizeFromEnv();

    $customer = self::retrieveCustomer($customerId);
    $status = TRUE;
    if($customer != NULL)
    {
      try
      {
        $createdProfile = $customer->sources->create($source);

        if (is_array($source['source']) == true && array_key_exists('object', $source['source']) == true && $source['source']['object']  == 'bank_account')
        {
          $status = self::verifyAccount($createdProfile, array(32,45));
        }
      }
      catch (\Stripe\Error\Base $e) {
        $body = $e->getJsonBody();
        $err  = $body['error'];
        if($err != NULL && strlen($err['message']) > 0)
        {
          drupal_set_message($err['message'], 'warning');
        }
        $status = FALSE;
      }
      catch (Exception $e)
      {
        drupal_set_message(t('We are sorry. There was an error storing your '.$source['source']['base'].' right now'), 'warning');
        $status = FALSE;
      } 
      return $status;
    }
  }

  /**
  * Charge a customer's default or specified card
  */

  public static function createCharge($amount, $currency, $customerId, $sourceId, $metadata,$receipt_email)
  {
    try
    { $params = array(
        "amount" => $amount * 100, // Amount in cents
        "currency" => "usd",
        "customer" => $customerId,
      "metadata"=>$metadata,
      "receipt_email"=>$receipt_email,
        "source" => $sourceId,);

     /*i if(is_array($metadata) && array_key_exists("email", $metadata) == true)
      {
        $params['description'] = "Payment for ".$metadata['email'];
        $params['metadata'] = $metadata;
      }
*/
      self::authorizeFromEnv();
      $charge = \Stripe\Charge::create($params);

      return $charge;
    }
   /* catch(Exception $e)
    {
      drupal_set_message(t('We are sorry. There was an error processing your transaction right now'), 'warning');
      return FALSE;
    }*/

     catch(\Stripe\Error\Card $e) {
$body = $e->getJsonBody();
 $err  = $body['error'];
return $e;

}

  }


//create charge for applepay
 public static function createChargeApplePay($amount, $currency, $sourceId, $description, $email)
  {
    try
    {
      self::authorizeFromEnv();
      $charge = \Stripe\Charge::create(array(
        "amount" => $amount * 100, // Amount in cents
        "currency" => "usd",
        "metadata"=>array('site-name'=>$_SERVER['HTTP_HOST']),
        "source" => $sourceId,
        "receipt_email" =>  $email,
        "description" => $description)
        

      );

      return $charge;
    }
    catch(Exception $e)
    {
      drupal_set_message(t('We are sorry. There was an error processing your transaction right now'), 'warning');
      return FALSE;
    }
  }





  /**
  * Verify customer's bank account, after collecting microdeposit details back from him/her
  */

  public static function verifyAccount($bankAccount, $amountArray)
  {
    try
    {
      self::authorizeFromEnv();
      $verifiedAccount = $bankAccount->verify(array('amounts' => $amountArray));
      return TRUE;
    }
    catch(Exception $e)
    {
      drupal_set_message(t('We are sorry. Could not verify your account right now.'), 'warning');
      return FALSE;
    }
  }
}

