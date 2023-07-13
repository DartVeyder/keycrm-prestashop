<?php 
include('../../config/config.inc.php');
include('/functions.php');
include('/header.inc.php');
echo "<pre>";
 
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
    product_attribute.reference = '".$_GET['reference']."'
LIMIT 1
";
$product  = Db::getInstance()->executeS($sql)[0]; 
$quanity  = 10;
$sql = "UPDATE "._DB_PREFIX_."stock_available SET quantity = $quanity  WHERE id_stock_available = $product[id_stock_available]";
Db::getInstance()->executeS($sql); 

$sql = "UPDATE "._DB_PREFIX_."product_attribute SET quantity = $quanity  WHERE id_product_attribute = $product[id_product_attribute]";
Db::getInstance()->executeS($sql); 
print_r($product);


?>