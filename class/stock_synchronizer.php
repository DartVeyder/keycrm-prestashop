<?php
use GuzzleHttp\Client;
class StockSynchronizer{
 
    private $client;


    public function __construct(){
        $this->client = new Client();
   
    }

    private function logs($path, $text){
        $file = fopen( $path, 'a+');
        fwrite($file, $text . "\n");
        fclose($file);
    }
    private function keycrm_products($order){
        $products = $order['products'];
        $order_id = $order['id'];
        $status_order_id = $order['status_id'];

        $is_status_reserv = [4,25,24,63,96,32,37,38,54,55,57,58,83,84,59,70,73,74];
        $is_status_sending = [8,10,20,46,45];
        foreach ($products as $key => $product) {
            $product_quantity = $product['offer']['quantity'];
            $stock_status = $product['stock_status'];
            $order_product_quantity = $product['quantity'];
            $sku =  $product['offer']['sku'];
            $name = $product['name'];
            $in_reserve = $product['offer']['in_reserve'];

            $quantity = $product_quantity - $in_reserve; 
            
             $result = $this->update_ps_product_stock($quantity,  $sku); 
            
            $result['name'] =  $name ;
            $result['order_id'] = $order_id;
            $result['status_order_id'] = $status_order_id; 
            $result['stock_status'] = $stock_status; 
            $result['order_product_quantity'] = $order_product_quantity; 
            $result['product_quantity'] = $product_quantity;
            $result['in_reserve'] = $in_reserve;

            $text =  json_encode($result,JSON_UNESCAPED_UNICODE);
            echo $text . " <br>";
            $this->logs('log.txt', $text);
            $this->logs('products/'.$sku.'.txt', $text);
        }
    }

    public function get_keycrm_order($order_id){
        // Make a GET request to a URL
        $response = $this->client->request('GET',  KEYCRM_URL_API."/order/$order_id?include=products.offer,status", [
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

    public function update_ps_product_stock($quantity, $reference){
        echo  $reference;
        $sql = "SELECT * FROM "._DB_PREFIX_."product  WHERE reference = '$reference'";
        $product = Db::getInstance()->executeS($sql)[0]; 
        if($product['product_type'] == 'standard' && $product || $product['product_type'] == '' && $product ){
            $sql = "
            SELECT 
                product.id_product, 
                product.reference as product_reference,
                stock_available.quantity as stock_available_quantity,
                id_stock_available,
                product.product_type
            FROM 
                "._DB_PREFIX_."product product 
            JOIN 
                "._DB_PREFIX_."stock_available stock_available
            ON 
                product.id_product = stock_available.id_product
            WHERE 
                product.reference = '$reference'
            ";
            $product = Db::getInstance()->executeS($sql)[0]; 
            
            $sql = "UPDATE "._DB_PREFIX_."stock_available SET quantity = $quantity  WHERE id_stock_available = $product[id_stock_available]";
            Db::getInstance()->executeS($sql); 
            
            if($product ){
                return [
                    'date' => date('Y-m-d H:i:s'),
                    'id_product' => $product['id_product'],
                    'product_reference' => $product['product_reference'],
                    'id_stock_available' => $product['id_stock_available'],
                    'stock_available_quantity' => $product['stock_available_quantity'],
                    'quantity' => $quantity,
                    'product_type' => $product['product_type'],
                    'status' => 'success'
                ];
            }else{
                return [
                    'date' => date('Y-m-d H:i:s'),
                    'product_attribute_reference' => $reference,
                    'product_type' => $product['product_type'],
                    'quantity' => $quantity,
                    'status' => 'failed'
                ];
            }

           

        }else{
            $sql = "
        SELECT 
            product_attribute.id_product_attribute, 
            product_attribute.id_product, 
            product_attribute.quantity as product_attribute_quantity, 
            stock_available.quantity as stock_available_quantity, 
            product_attribute.reference  as product_attribute_reference, 
            product.reference as product_reference,
            product.product_type,
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
            'product_type' => $product['product_type'],
            'status' => 'success'
        ];
    }else{
        return [
            'date' => date('Y-m-d H:i:s'),
            'product_attribute_reference' => $reference,
            'quantity' => $quantity,
            'product_type' => $product['product_type'],
            'status' => 'failed'
        ];
    }
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