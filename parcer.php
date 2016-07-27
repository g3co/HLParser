<?php
/* Версия 1.0
 * Парсер прайс листа sex-opt.ru
 * для honeylovers.ru
 */
$start = microtime(true);
//define('ROOT', $_SERVER['PWD']);
define('ROOT', __DIR__);

include(ROOT.'/lib/includes.php');



if(isset($argv[1]) && $argv[1] == 'new'){
    echo "Вы собирайтесь очистить таблицы \n";
    while(true){   
    echo "Введите пожалуйста подтверждение (Y/N):";
    $clear_base_confirm = trim(fgets(STDIN));
        if($clear_base_confirm == "Y" || $clear_base_confirm == "y"){
            clearTables();
            break;
        }elseif($clear_base_confirm == "N" || $clear_base_confirm == "n"){
            echo "Таблицы не очищены \n";
            break;
        }else{
            echo "Неверный ключ \n";
        }
    }
}

$mail = new YandexSMTP;
$mail->stdTemplate = ROOT.'/templates/standart.tpl';

$imageClass = new imageClass;
$imageClass->imagePath = ROOT . '/../image/data/sexshop/';
$imageClass->pathToDB = 'data/sexshop/';

$paa = new PriceAndAvailable;
$titleGenerator = new titleGenerator(ROOT."/cfg/title.xml");
$atributes= new atributes($atributesArr,$wordlib->get_xml(ROOT."/atribut.xml"));
$atributes->filter->checkFirst();




if (($handle = fopen($file, "r")) !== FALSE) {
    // Часть первая формируем массив из строковых категорий
    
    foreach(QueryGet("SELECT * FROM oc_parcer_category") as $category){
        $categoryArrBase[$category['id']] = $category;
    }
    
    
    while (($data = fgetcsv($handle, 10000000, ";")) !== FALSE) {
        
        if(++$hundreedTimer >= 1000) {
            $hundreedTimer = 0;
        }
        
        $catId = NULL;
        $iteration++;
        $id = NULL;
        ++$i;
        $loseFlag = false;


        $request = QueryGet("SELECT P.product_id,P.sku,PC.category_id FROM `oc_product` P "
                            . "LEFT JOIN oc_product_to_category PC ON P.product_id = PC.product_id "
                            . "WHERE P.sku = '".$data[0]."' AND PC.main_category = 1 LIMIT 2");

        if(count($request)  > 1) {
            echo "Обнаружен дубль, скрипт остановлен \n"; print_r($request); print_r($data);
        }elseif(!count($request)){
            if ($i != 1 && $data[34]) {
                /* 	Создаём список категорий
                 */
                $products_count++;
             
                $remotePrice2 = $remotePrice = array(array_pad(array(), 36, NULL));
                $flag1 = $flag2 = 0;
                if($data[26]){
                    $remotePrice = QueryGet("SELECT * FROM `TABLE 121` "
                            . "WHERE `COL 15` = '".(int)$data[26]."'",MYSQLI_NUM);
                    
                    if(count($remotePrice) > 1){
                        print_r($remotePrice);
                        die('aaa');
                    }elseif(count($remotePrice) == 1){
                            $flag1 = 1;
                          $matchesR++;
                    }
                }
                

                $categoryArr = explode('/', $data[6]);
                if (isset($categoryArr[1])) {
                        insertCat('', $categoryArr[0], $data[6], 0);
                        insertCat('', $categoryArr[1], $data[6], getId($categoryArr[0]));
                        $catArr = getCatArr($data[6], true);
                        $catId = $catArr['target_id'];
                } else {
                        insertCat('', $categoryArr[0], $data[6], 0);
                        $catArr = getCatArr($data[6], false);
                        $catId = $catArr['target_id'];
                }
                
                $data[4] = ($data[4] != "Презервативы")?$data[4]:$data[4]." производитель";
                insertManufacturer($data[4]);

                /* 	Список товаров
                 */

                $productName = addslashes($data[2]);
                
                $instr = array("длина");
                $outstr   = array("длина ");
                
                if(!empty($remotePrice[0]) && $remotePrice[0][11]){$kit = " <br>Комплект: ".$remotePrice[0][11];} else {$kit = "";}
                if(!empty($remotePrice[0]) && $remotePrice[0][10]){
                    $moreInfo = "<br>".str_replace($instr, $outstr, $remotePrice[0][10]);
                }else{
                    $moreInfo = "";
                }
                
                $productDescription = addslashes($data[29]).$moreInfo.$kit;

                $keywords = implode(", ",explode(" ",$wordlib->clearstr($productName))).", ".implode(', ',$categoryArr);
                $atributesFromName = $atributes->fromName($data[2]);

                include(ROOT.'/atributesRule.php');    
                
                $sql = "INSERT INTO oc_product (`product_id`, `model`, `sku`, `upc`, `ean`, `jan`, `isbn`, `mpn`, `location`, `quantity`, `stock_status_id`, `image`, `manufacturer_id`, "
                        . "`shipping`, `price`, `points`, `tax_class_id`, `date_available`, `weight`, `weight_class_id`, `length`, `width`, `height`, `length_class_id`, `subtract`, "
                        . "`minimum`, `sort_order`, `status`, `date_added`, `date_modified`, `viewed`) VALUES (NULL, '".$productName."', '{$data[0]}', '{$data[1]}', '{$data[26]}', '',"
                        . " '', '', '', '1', '5', '".$imageClass->setImage($data[13])."', '".getManufacturerId ($data[4])."', '1', '{$data[11]}', '0', '0',"
                        . " '".date("Y-m-d")."', '0.00000000', '1', '0.00000000', '0.00000000', '0.00000000', '0', '1', '1', '0', '1',"
                        . "'".date("Y-m-d H:i:s")."', '".date("Y-m-d H:i:s")."', '1')";
                $id = QueryExec($sql,true);

                $pname = $productName;
                
                $title = $titleGenerator->getTitle(array($wordlib->clearTitle($pname)  ,
                                            (isset($categoryArr[1])) ? $categoryArr[1] : $categoryArr[0],
                                            $data[4],
                                            $wordlib->translitEN($wordlib->clearTitle($pname)),
                                            $atributes->getParam('color')
                                        ));
                  
                $sql = "INSERT INTO oc_product_description (`product_id` ,`language_id` ,"
                        . "`name` ,`description` ,`meta_description` ,"
                        . "`meta_keyword`,`seo_title` ,`seo_h1` ,`tag`) "
                        . "VALUES ({$id}, '1', '{$pname}', '{$productDescription}', '', '{$keywords}', "
                        . "'{$title}', '{$pname}', '')";
                QueryExec($sql,true);
                
                $sql = "INSERT INTO oc_product_to_store (`product_id` ,`store_id`) VALUES ({$id}, '0')";
                QueryExec($sql,true);

                $sql = "INSERT INTO oc_product_to_category (`product_id`, `category_id`, `main_category`) "
                        . "VALUES ('{$id}', '{$catId}', '1')";
                QueryExec($sql,true);

                if($data[14]){QueryExec("INSERT INTO `oc_product_image` (`product_id`, `image`, `sort_order`) "
                        . "VALUES ('{$id}', '".$imageClass->setImage($data[14])."', '0');",true);}
                if($data[15]){QueryExec("INSERT INTO `oc_product_image` (`product_id`, `image`, `sort_order`) "
                        . "VALUES ('{$id}', '".$imageClass->setImage($data[15])."', '0');",true);}


                makeUrl("product_id=".$id,$wordlib->translitRU($data[2])."_".$id);

                foreach($atributes->get() as $atributBit){
                    
                        QueryExec("INSERT INTO `oc_product_attribute` "
                                . "(`product_id`, `attribute_id`, `language_id`, `text`) "
                                . "VALUES ('{$id}', '{$atributBit['id']}', '1', '{$atributBit['value']}')",true);
                                
                       
                        $atributes->filter->set($atributBit,$id,$catId);         
                }

                /* Массив для отчёта
                 */
                $addedProducts[]=array('productId'=>$id,'image'=>$imageClass->setImage($data[13]),'name'=>$pname);
            }else{
//              print_r($data); die;
                $loseFlag = true;
            }
        }
          
        if(!$loseFlag){
           if(!$id && !empty($request[0])) { $id = $request[0]['product_id'];}
           if(!isset($catArr) && !empty($request[0])){ 
               $catArr = $categoryArrBase[$request[0]['category_id']]; 
           }
           $paa->writePA(array('productId'=>$id,'categoryArr'=>$catArr,'price'=>$data[11],'available'=>$data[34]));
        }
        
        unset($catArr);
    }
}

foreach(QueryGet("SELECT * FROM oc_parcer_category") as $categoryBit){
    if(!QueryGet("SELECT category_id FROM oc_category WHERE category_id = '".$categoryBit['id']."'")){
	makeCategory($categoryBit['id'],$categoryBit['parent'],$categoryBit['name'],$categoryBit['target_id']);
	makeUrl("category_id=".$categoryBit['id'],$wordlib->translitRU($categoryBit['name']));
    }
}

foreach(QueryGet("SELECT * FROM oc_parcer_manufacturer") as $manufacturerBit){
    if(!QueryGet("SELECT manufacturer_id FROM oc_manufacturer WHERE manufacturer_id = '".$manufacturerBit['manufacturer_id']."'")){
	makeManufacturer($manufacturerBit['manufacturer_id'],$manufacturerBit['group']);
	makeUrl("manufacturer_id=".$manufacturerBit['manufacturer_id'],$wordlib->translitRU($manufacturerBit['group']));
    }
}

$productReminder = new productReminder;
$reminders = $productReminder->check();
if(count($reminders)){
	foreach($productReminder->check() as $productReminderMessage){
		$mail->subject = "Появились товары, которые Вы ожидаете";
		$mail->to = array('email'=>$productReminderMessage['email'],'name'=>$productReminderMessage['email']);
		$mail->makeMessage($productReminderMessage['text']);
		$mail->sendMail(); 
	}
}

?> 

Добавлено товаров <?= $products_count . "\n" ?>
Всего итераций <?= $iteration . "\n" ?>
Страница создана за <?= round(microtime(true) - $start, 3) ?> сек.
Совпадений в прайсе <?= $matchesR ?>.
<?php

if($products_count){
    $mail->subject = "Новые товары на сайте honeylovers.ru";
    $mail->to = array('email'=>'kabisovvaleriy@gmail.com','name'=>'Валерий');
    $message = "Добавлено товаров: ".$products_count;
    $message .= "<table>";
    $addedProductsCounter = 0;
    $site="https://honeylovers.ru";
    foreach($addedProducts as $addedProduct){
        $message .= '<tr>';
            $message .= '<td>';
                $message .= '<img src="'.$site.'/image/'.$addedProduct['image'].'" style="width:50px;">'; 
            $message .= '</td>'; 
            $message .= '<td>';
                $message .= '<a href="'.$site.'/index.php?route=product/product&product_id='.
                            $addedProduct['productId'].'">'.$addedProduct['name'].'</a>'; 
            $message .= '</td>';
        $message .= '</tr>';
        if(++$addedProductsCounter > 30) break;
    }
    $message .= "</table>";
    $mail->makeMessage($message);
    $mail->sendMail(); 
}
