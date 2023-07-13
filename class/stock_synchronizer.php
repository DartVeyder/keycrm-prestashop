<?php
use GuzzleHttp\Client;
class StockSynchronizer{
 
    private $client;


    public function __construct(){
        $this->client = new Client();
   
    }

    private function logs($text){
        $path = 'log.txt'; 
        $file = fopen( $path, 'a+');
        fwrite($file, $text . "\n");
        fclose($file);
    }
    private function keycrm_products($order){
        $products = $order['products'];
        $order_id = $order['id'];
        foreach ($products as $key => $product) {
            $quantity = $product['offer']['quantity'];
            $sku =  $product['offer']['sku'];
            $name = $product['name'];
            
            $result = $this->update_ps_product_stock($quantity,  $sku); 
            
            $result['name'] =  $name ;
            $result['order_id'] = $order_id;
            $text =  json_encode($result,JSON_UNESCAPED_UNICODE);
            echo $text . " <br>";
            $this->logs($text);
        }
    }

    public function get_keycrm_order($order_id){
        // Make a GET request to a URL
        $response = $this->client->request('GET',  KEYCRM_URL_API."/order/$order_id?include=products.offer", [
            'headers' => [
                'Authorization' => 'Bearer ' .KEYCRM_TOKEN,
                'Accept' => 'application/json', 
            ],
        ]);

        // Get the response body as a string
        $order = json_decode($response->getBody()->getContents(),1);
        $this->keycrm_products($order);
    }  

    public function webhook(){
         // fetch RAW input
        $json = file_get_contents('php://input'); 
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // fetch RAW input
        $json = file_get_contents('php://input'); 
        // decode json
        $object = json_decode($json, 1); 
        // expecting valid json
        if (json_last_error() !== JSON_ERROR_NONE) {
            die(header('HTTP/1.0 415 Unsupported Media Type'));
        } 
        $context = $object['context'];
        $order_id = $context['id'];
        $order_status = $context['status_id'];
        $status_changed_at = $context['status_changed_at'];		
        $this->get_keycrm_order($order_id);  
       /* $text =  "order id: $order_id, order_status: $order_status,  status_changed_at: $status_changed_at";

        $path = 'log.txt'; 
        $file = fopen( $path, 'a+');
        fwrite($file, $text . "\n");
        fclose($file); 	
        file_put_contents('test.txt', print_r($object, true));	 */
        }
    }

    private function update_ps_product_stock($quantity, $reference){

        $sql = "
        SELECT 
            product_attribute.id_product_attribute, 
            product_attribute.id_product, 
            product_attribute.quantity as product_attribute_quantity, 
            stock_available.quantity as stock_available_quantity, 
            product_attribute.reference  as product_attribute_reference, 
            product.reference as product_reference,
            id_stock_available
        FROM 
            "._DB_PREFIX_."product_attribute product_attribute
        
        JOIN 
            "._DB_PREFIX_."product product
        ON 
            product_attribute.id_product   = product.id_product
        JOIN 
            "._DB_PREFIX_."stock_available stock_available
        ON 
            product_attribute.id_product_attribute  = stock_available.id_product_attribute
        WHERE 
            product_attribute.reference = '".$reference."'
        LIMIT 1
        ";
        $product  = Db::getInstance()->executeS($sql)[0]; 
    
        $sql = "UPDATE "._DB_PREFIX_."stock_available SET quantity = $quantity  WHERE id_stock_available = $product[id_stock_available]";
        Db::getInstance()->executeS($sql); 
        
        $sql = "UPDATE "._DB_PREFIX_."product_attribute SET quantity = $quantity  WHERE id_product_attribute = $product[id_product_attribute]";
        Db::getInstance()->executeS($sql); 
        if($product ){
        return [
            'date' => date('Y-m-d H:i:s'),
            'product_attribute_reference' => $reference,
            'id_product_attribute' => $product["id_product_attribute"],
            'product_attribute_quantity' => $product['product_attribute_quantity'],
            'id_product' => $product['id_product'],
            'product_reference' => $product['product_reference'],
            'id_stock_available' => $product['id_stock_available'],
            'stock_available_quantity' => $product['stock_available_quantity'],
            'quantity' => $quantity,
            'status' => 'success'
        ];
    }else{
        return [
            'date' => date('Y-m-d H:i:s'),
            'product_attribute_reference' => $reference,
            'status' => 'failed'
        ];
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