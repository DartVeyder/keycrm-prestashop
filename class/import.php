<?php

use GuzzleHttp\Client;



class Import{
    private $ps_lang_default;

    public function __construct(){
        $this->ps_lang_default = Configuration::get('PS_LANG_DEFAULT');
        $this->process_import();
        //$category = $this->upload_keycrm_categories()[301];
      //  dd($this->category($category));
       
    }

    private function upload_keycrm_products(){
        $client = new Client(); 
        $response = $client->request('GET',  KEYCRM_URL_API."/offers?include=product&filter[product_id]=1214", [
            'headers' => [
                'Authorization' => 'Bearer ' .KEYCRM_TOKEN, 
                'Accept' => 'application/json', 
            ],
        ]);

        $products = json_decode($response->getBody()->getContents(),1);

       
        return $products['data'];
    }
    private function findCategoryIdByName($categories, $name) {
        foreach ($categories as $category) {
            foreach ($category as $categoryId => $data) {
                if ($data['infos']['name'] === $name) {
                    return [
                        'id_category' => $data['infos']['id_category'],
                        'id_parent' => $data['infos']['id_parent'],
                    ];
                }
            }
        }
        return null; // якщо категорія з вказаною назвою не знайдена
    }
    private function category($keycrm_category){
       
        $category_info = $this->findCategoryIdByName(Category::getCategories(), $keycrm_category['parent_name']);
        if(!$category_info){
            $category = new Category();
            $category->name = [$this->ps_lang_default => $keycrm_category['parent_name']];
            $category->link_rewrite = [$this->ps_lang_default => $this->generateSlug($keycrm_category['parent_name'])];
            $category->id_parent = 2; // ID батьківської категорії (або 0, якщо немає батьківської категорії)
            $category->active = true; // Встановити категорію активною
            $category->add();
            
            $parent_id = $category->id;
        }else{
            $parent_id = $category_info['id_category'];
        }

        $category_info = $this->findCategoryIdByName(Category::getCategories(), $keycrm_category['name']);
        if(!$category_info){
            $category = new Category();
            $category->name = [$this->ps_lang_default => $keycrm_category['name']];
            $category->link_rewrite = [$this->ps_lang_default => $this->generateSlug($keycrm_category['name'])];
            $category->id_parent =  $parent_id; // ID батьківської категорії (або 0, якщо немає батьківської категорії)
            $category->active = true; // Встановити категорію активною
            $category->add();

            Category::updateFromShop($category->name,1);
            return $category->id;
        }else{
            return $category_info['id_category'];
        }
         
         
       
    }

    private function generateSlug($text) {
        $transliteratedText = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '-', $transliteratedText);
        $slug = trim($slug, '-');
        $slug = strtolower($slug);
        return $slug;
    }

    private function upload_keycrm_categories(){
        $client = new Client(); 
        $categories = [];
        for ($i=1; $i <= 2 ; $i++) { 
            $response = $client->request('GET',  KEYCRM_URL_API."/products/categories?limit=50&page=$i", [
                'headers' => [
                    'Authorization' => 'Bearer ' .KEYCRM_TOKEN, 
                    'Accept' => 'application/json', 
                ],
            ]);
            $array = json_decode($response->getBody()->getContents(),1);
           
            $categories = array_merge( $categories, $array['data']) ;
        }
        foreach ($categories as &$item) {
            if (!empty($item['parent_id'])) {
                foreach ($categories as $parent) {
                    if ($parent['id'] === $item['parent_id']) {
                        $item['parent_name'] = $parent['name'];
                        break;
                    }
                }
            }
        }

                // Отримати масив з ключами id
        $keys = array_column($categories, 'id');

        // Змінити ключі елементів на id
        $categories = array_combine($keys, $categories);

        return  $categories;
    }


    private function process_import(){
        $products = $this->upload_keycrm_products();
        $this->replace_product($this->get_products($products)); 
    }

    private function get_products($products){
        $product = $products[0]['product'];
        $categories = $this->upload_keycrm_categories();
        $array = [
            'name' => $product['name'],
            'description' => $product['description'],
            'quantity' => $product['quantity'],
            'image' => $product['thumbnail_url'],
            'price' => $product['max_price'],
            'reference' => 'S'.str_pad($product['id'], 6, '0', STR_PAD_LEFT),
            'attrbutes' => $product['properties_agg'],
            'category' =>   $categories[$product['category_id']]
        ];

        foreach ($products as $key => $offer) {
             $product_combinations[] = [
                'reference' => $offer['sku'],
                'quantity' => $offer['quantity'],
                'attributes' => $offer['properties'],
                'price' => $offer['price'],
                
             ];
        }

        $array['product_combinations'] = $product_combinations;
       return $array;
    }
    

    private function replace_product($data){
        
        $reference = $data['reference'];
        $name = $data['name'];
        $default_lang = Configuration::get('PS_LANG_DEFAULT');
        $product = new Product();
        $product_id = $product->getIdByReference($reference);

        $this->add_atrribute($data['attrbutes']);
        $category_id = $this->category($data['category']);

        echo $category_id;
        if($product_id){

          
        }else{
            $product->name = [$default_lang => $name]; 

            $product->reference = $reference ;
            $product->price = $data['price'];
            $product->quantity = $data['quantity'];
            $product->category = [$category_id];
            $product->id_category_default =$category_id;
            
            if($product->add()){
                $product->updateCategories($product->category);
                foreach ($data['product_combinations'] as $key => $offer) {
                    
                    $combination = new Combination();
                    $combination->id_product = $product->id;
                     $combination->quantity =   $offer['quantity'];
                    $combination->reference = $offer['reference'];
                    $combination->save();
                    
                    StockAvailable::setQuantity((int)$product->id,  $combination->id , $offer['quantity'], Context::getContext()->shop->id);
                    foreach ($offer['attributes'] as $key => $attribute) {
                        $attribute_id = $this->is_attribute($attribute); 
     
                        Db::getInstance()->execute('
                            INSERT IGNORE INTO ' . _DB_PREFIX_ . 'product_attribute_combination (id_attribute, id_product_attribute)
                            VALUES (' . (int)  $attribute_id  . ',' . (int) $combination->id . ')', false);
                    }
                }
            }
        }
         
    }

    private function add_atrribute($attributes){
        $default_lang = Configuration::get('PS_LANG_DEFAULT');
        $ps_attributes = Attribute::getAttributes(Context::getContext()->language->id);
        foreach ($attributes as $key => $attribute) {
            $ps_is_group =  in_array( $key ,array_column($ps_attributes, 'attribute_group') ) ;
            if($ps_is_group){                
                foreach ($attribute as  $value) {
                    if(!in_array($value,array_column($ps_attributes, 'name') )){
                        $id_attribute_group = array_search($key, array_column($ps_attributes, 'attribute_group', 'id_attribute_group'));
                        $attributeValue = new Attribute();
                        $attributeValue->id_attribute_group = $id_attribute_group;
                        $attributeValue->name = [$default_lang => $value]; ;
                        $attributeValue->add();
                    } 
                }
            }else{
                $newGroup = new AttributeGroup();
                $newGroup->name = [$default_lang => $key];
                $newGroup->group_type = 'select';
                $newGroup->public_name =  [$default_lang =>  $key];
                $newGroup->position = Attribute::getHigherPosition($newGroup->id) + 1;
                $newGroup->add();
                foreach ($attribute as  $value) {
                    $id_attribute_group = array_search($key, array_column($ps_attributes, 'attribute_group', 'id_attribute_group'));
                    $attributeValue = new Attribute();
                    $attributeValue->id_attribute_group = $newGroup->id;
                    $attributeValue->name = [$default_lang => $value]; ;
                    $attributeValue->add();      
                }
            }
        }
    }

    private function is_attribute($attr){
        $default_lang = Configuration::get('PS_LANG_DEFAULT');
        $attributes = Attribute::getAttributes(Context::getContext()->language->id);
        foreach ($attributes as $key => $attribute) {

           if($attr['name'] ==  $attribute['attribute_group'] && $attr['value'] ==  $attribute['name']){
                $result =  $attribute['id_attribute'];
                break;
            }else{
                $result = false;
            }
          
        }
        return $result;
    }
}

function dd($data, $action = 0){
    echo "<pre>";
    print_r($data);
    if( $action == 0){
        exit;
    }
}