<?php

class BCP_Invoice
{

    public function __construct($item)
    {
        $this->item = $item;
        //echo "<pre>";
        //print_r($this->item);

    }

    public function BCP_checkInvoiceStatus($orderID)
    {

        $post_fields = ($this->item->item_params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://' . $this->item->invoice_endpoint . '/' . $post_fields->invoiceID);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    public function BCP_createInvoice()
    {
       
       //echo $this->testmode;
      // echo "dgsdgdfg";
       // echo "<pre>";
        //print_r($this->item);

        $curl = curl_init();

            curl_setopt_array($curl, array(
              CURLOPT_URL => $this->item->api_url."payment/start",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => false,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS =>"{\n\t\n  \"sequence_id\": ".date('ymdhis').",\n  \"coin\": \"BTC\",\n  \"value_brl\": ".$this->item->price.",\n  \"api_key\": ".$this->item->api_key.",\n  \"secret_key\": ".$this->item->secret_key.",\n  \"client_version\": \"1.0.0.0\"\n\n}",
              CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
              ),
            ));
           // array('sequence_id'=>'','ts_event'=>'','coin_value'=>'','coin_rate_brl'=>'','coin_addr'=>'','payment_id'=>'','value_brl'=>'','coin'=>'')
            $response = curl_exec($curl);
            $this->invoiceData = $response;
            $err = curl_error($curl);

            curl_close($curl);

    }

    public function BCP_getInvoiceData()
    {
        return $this->invoiceData;
    }

    public function BCP_invoiceresponse()
    {
       
       //echo $this->testmode;
      // echo "dgsdgdfg";
        //echo "<pre>";
        //print_r($this->item);
        //echo $this->item->invoiceID;

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->item->api_url."payment/update",
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => false,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "POST",
          CURLOPT_POSTFIELDS =>"{\n  \"payment_id\": ".$this->item->invoiceID.",\n  \"api_key\": ".$this->item->api_key.",\n  \"secret_key\": ".$this->item->secret_key.",\n  \"client_version\": \"1.0.0.0\"\n}",
          CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json"
          ),
        ));

        $response = curl_exec($curl);
        $this->invoiceData = $response;
        $err = curl_error($curl);

        curl_close($curl);

      // $this->invoiceData = '{"sequence_id":null,"status":1,"ts_event":"20190408141846","payment_id":505,"confirmations":0,"coin_value":null,"coin_rate_brl":0.20,"coin_addr":"1P8WvKWQ1UB51hWT4YpgM8jmGVGUgbAdL","value_brl":0.20,"coin_received":0.00000873,"txid":null,"from":"1P8WvKWQ1UB51hWT4YpgM8jmGVGUgbAdL","coin":"BTC"}';

       

    }

    public function BCP_getInvoiceURL()
    {
        $data = json_decode($this->invoiceData);
        return $data->data->url;
    }


}
