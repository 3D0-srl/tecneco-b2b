function salva(id, sku) {
    $.ajax({
        type: "GET",
        url: `index.php?ctrl=ProductPromo&mod=b2b&action=salva&ajax=1&id=${id}&sku=${sku}`,
        data: $('#form_' + id).serialize(),
        dataType: 'json',
        success: function (data) {
            console.log(data);
            if (data.success) {
                for (var k in data.dati) {
                    $('#' + k).val(data.dati[k]);
                }
                $('#btn_add').click();
				$('.rimuovi_'+id).show();
				$('.associa_'+id).hide();
                //alert('Dati salvati correttamente');
                return true;
            }
        },
        error: function(error){
            console.error(error);
        }
    });
}

function rimuovi(id) {
    $.ajax({
        type: "GET",
        url: `index.php?ctrl=ProductPromo&mod=b2b&action=rimuovi&ajax=1&id=${id}`,
        dataType: 'json',
        success: function (data) {
           
            if (data.success) {
				
                $('.rimuovi_'+data.id).hide();
				$('.associa_'+data.id).show();
                //alert('Dati salvati correttamente');
                return true;
            }
        },
        error: function(error){
            console.error(error);
        }
    });
}

$(document).ready(function(){
  $('.date-control .form-control').inputmask({"mask": "99/99/9999", "placeholder": 'dd/mm/yyyy'});
 });

function letterChange(letter){
    window.location.href = `http://catalogo.tecneco.com/backend/index.php?ctrl=ProductPromo&mod=b2b&action=list&letter=${letter}`;
}
