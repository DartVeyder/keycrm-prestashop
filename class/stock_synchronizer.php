<?php
use GuzzleHttp\Client;
class StockSynchronizer{
 
    private $client;


    public function __construct($keycrm_token, $keycrm_url_api){
        $this->client = new Client();
        //$this->get_keycrm_order(32255);
        $this->get_ps_products();
    }

    private function keycrm_order_products($order){
        $order_products = $order['products'];
        
    }

    private function get_keycrm_order($order_id){
        // Make a GET request to a URL
        $response = $this->client->request('GET', KEYCRM_URL_API . "/order/$order_id?include=products.offer", [
            'headers' => [
                'Authorization' => 'Bearer ' .KEYCRM_TOKEN,
                'Accept' => 'application/json', 
            ],
        ]);

        // Get the response body as a string
        $order = json_decode($response->getBody()->getContents(),1);

        dd($order);
    } 

    private function get_ps_products(){
        try {
            // creating webservice access
            $webService = new PrestaShopWebservice(PS_SHOP_PATH, PS_WS_AUTH_KEY, true);
            $opt['resource'] = 'combinations/4024';
            
            // call to retrieve all customers
            $xml = $webService->get($opt);
           

            echo $xml;
        } catch (PrestaShopWebserviceException $ex) {
            // Shows a message related to the error
            echo 'Other error: <br />' . $ex->getMessage();
        }
    }
    
}

function dd($data, $action = 0){
    echo "<pre>";
    print_r($data);
    if( $action == 0){
        exit;
    }
}