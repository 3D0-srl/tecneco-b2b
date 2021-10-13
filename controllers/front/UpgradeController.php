<?php
class UpgradeController extends FrontendController{
    function display(){
        $database = _obj('Database');


        $check = $database->select('*','back_orders',"1=1 limit 1");
        if( !okArray($check) ){
            $query1 = "CREATE TABLE back_orders (
                id bigint(20) UNSIGNED NOT NULL,
                product_id bigint(20) UNSIGNED NOT NULL,
                qnt int(11) NOT NULL,
                timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                user_id bigint(20) UNSIGNED NOT NULL,
                last_update timestamp NULL DEFAULT NULL,
                cart_id bigint(20) NOT NULL
              ) ENGINE=InnoDB DEFAULT CHARSET=latin1;";
            $database->execute($query1);

            $query2 = "ALTER TABLE back_orders ADD UNIQUE KEY id (id);";
            $database->execute($query2);

            $query3 = "ALTER TABLE back_orders MODIFY id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;";
            $database->execute($query3);
        }

        
    }

}