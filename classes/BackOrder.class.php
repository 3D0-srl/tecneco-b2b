<?php
class BackOrder extends Base{
	
	// COSTANTI DI BASE
	const TABLE = 'back_orders'; // nome della tabella a cui si riferisce la classe
	const TABLE_PRIMARY_KEY = 'id'; //chiave primaria della tabella a cui si riferisce la classe
	const TABLE_LOCALE_DATA = ''; // nome della tabella del database che contiene i dati locali
	const TABLE_EXTERNAL_KEY = '';// / nome della chiave esterna alla tabella del database
	const PARENT_FIELD_TABLE = ''; //nome del campo padre
	const LOCALE_FIELD_TABLE = 'lang'; // nome del campo locale nella tabella contenente i dati locali
	const LOCALE_DEFAULT = 'it'; //il locale di dafault
	const LOG_ENABLED = true; //abilita i log
	const PATH_LOG = ''; // file  in cui verranno memorizzati i log
	const NOTIFY_ENABLED = false; // notifica all'amministratore
	const NOTIFY_ADMIN_EMAIL = 'ciro.napolitano87@gmail.com'; // email a cui inviare la notifica




    public static function add($sku,$qnt){
        $database = _obj('Database');
        $id = null;
        if( !$qnt ) $qnt = 0;
        $user = Marion::getUser();
        
        $check = $database->select('*','back_orders',"product_id={$sku} AND user_id={$user->id}");
       
        if( okArray($check) ){
            
            $back = $check[0];
            $id = $back['id'];
            
            if( $qnt == 0){
                $database->delete('back_orders',"product_id={$sku} AND user_id={$user->id}");
            }
            if( $back['qnt'] != $qnt ){
                $database->update('back_orders',"product_id={$sku} AND user_id={$user->id}",['qnt'=>$qnt,'last_update'=> date('Y-m-d H:i')]);
            } 
            
            
        }else{
            if( $qnt > 0){
                $cart = Cart::getCurrent();
                $id = $database->insert('back_orders',
                [
                    'qnt'=>$qnt,
                    'product_id' => $sku,
                    'user_id' => $user->id,
                    'cart_id' => $cart->id
                ]
                );
            }
        }
        return $id;
    }



    public static function getAll($id_user){
        $database = _obj('Database');
       
        
        $list = $database->select('*','back_orders',"user_id={$id_user}");
       
        $toreturn = [];
        foreach($list as $v){
            $toreturn[$v['product_id']] = $v;

        }
        return $toreturn;
    }


    public static function getRow($id_user,$id_product){
        $database = _obj('Database');
       
        
        $list = $database->select('*','back_orders',"user_id={$id_user} AND product_id={$id_product}");
        if( count($list) > 0){
            return $list[0];
        }else{
            return null;
        }
        
    
    }


    public static function getTotalItems($id_user){
        $database = _obj('Database');
        if( is_object($id_user) ){
            $user = $id_user;
        }else{
            $user = User::withId($id_user);
           
            $_SESSION['userdata'] =  $user;
        }

       
        
        $all = self::getAll($user->id);
        /*$cart = Cart::getCurrent();
        $currentOrders = $database->select('product,quantity','cartRow',"cart={$cart->id}");
        //debugga($all);
      
        foreach($currentOrders as $v){
            //debugga($v,'qui');exit;
            if( array_key_exists($v['product'],$all)){
                //debugga($all[$v['product']]);
                $all[$v['product']]['qnt'] -= $v['quantity'];
            }
        }*/

        $tot = 0;
        foreach($all as $v){
            if( $v['qnt'] > 0){
                $tot+=$v['qnt'];
            }
        }
       
        return $tot;
    }


    public static function getNuoviProdotti($id_user){

        if( is_object($id_user) ){
            $user = $id_user;
        }else{
            $user = User::withId($id_user);
           
            $_SESSION['userdata'] =  $user;
        }
        $cart = Cart::getCurrent();
       
        $all = self::getAll($user->id);
        
       
        $database = _obj('Database');
       

        $filtrati = array_filter($all,function($item){
            if( $item['cart_id'] != $cart->id){
                return true;
            }else{
                return false;
            }
        });
       
       if( okArray($filtrati) ){
            $tipologie = array();
            $tipologia_select = $database->select('*','catalogo_tipologia_prodotto');
            foreach($tipologia_select as $v){
                $tipologie[$v['id']] = $v['nome'];
            }
        
            $where = trim(array_reduce(
                array_map(function($item){
                    return $item['product_id'];
                },$filtrati),function($res,$id){
                return $res.="{$id},";
            },''),',');
            $list = $database->select('p.sku,p.id,i.quantity,cp.id_tipologia as tipologia','product_inventory as i join (product as p join catalogo_prodotto as cp on cp.sku_stat=p.sku) on p.id=i.id_product',"p.id IN ({$where})");
           
            if( okArray($list) ){
               foreach($list as $k => $v){
                   if( $filtrati[$v['id']]['qnt'] > $v['quantity'] ){
                        unset($list[$k]);
                   }else{
                       $list[$k]['tipologia'] = $tipologie[$v['tipologia']];
                   }
               }
              
               return $list;
           }
        }

        return null;
    }

}

?>