<?php
  
  class eMSResponse
  {
    var $response_id;
	var $err_code;
	var $err_message;
	var $redirect_url;
	var $merchant_refrence;
	var $response_signture;
	var $response_verification_type;
	var $merchant_key;
	
	function eMSResponse($responsexml,$merchant_key,$verification_type="SIMPLE")
	{
	   $xml = new SimpleXMLElement($responsexml);
	   $this->response_id =  $xml["ID"];
	   $this->response_signture = $xml['Signature'];
	   $this->merchant_refrence = $xml->Reference; 
	   $this->redirect_url = $xml->URL; 
	   $this->err_code = $xml->ErrCode;
	   $this->err_message = $xml->ErrText;
	   $this->response_verification_type = $verification_type;
	   $this->merchant_key = $merchant_key;
	}
	
	function CheckResponseSimple()
	{
	  $verification_string = $this->response_id.$this->merchant_refrence.$this->redirect_url.$this->err_code.$this->merchant_key;
	  $verification_signature = sha1($verification_string);
	  return ($verification_signature==$this->response_signture);
	}
	
	function CheckResponsePKI()
	{
		return false;
	}
	
	function IsValid()
	{
		$signture_ok = $this->response_verification_type=="SIMPLE"?$this->CheckResponseSimple():$this->CheckResponsePKI;
		return ($signture_ok and ($this->err_code=="0"));
	}
	
	function ToHtml()
	{
		echo("<html><head></head><body><form name='emsPHPdemo' action='".$this->redirect_url."' method='post'");
   echo ("<p>Vas zahtev za pokretanje procesa placanja je prosledjen eMS sistemu. </p> 
		  <p> eMS sistem je prosledio odgovor na vasu korpu <b>".$this->merchant_refrence."</b> i dodelio joj je sledeci identifikacioni broj: <b>".$this->response_id."</b>
		  <p> EMs sistem je vratio kod <b>".$this->err_code." [".$this->err_message."]</b>	
		  <p> Adresa za redirekciju je <b>".$this->redirect_url."</b>		  
		  <p>Klikom na dugme 'NASTAVI' mozete  preci na sledeci korak.</p>");
   echo ("<input type='submit' value='Nastavi'/></form></body></html>");
	}
  }
?>