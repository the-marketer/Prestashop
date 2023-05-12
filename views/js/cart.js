/**
*  theMarketer V1.0.3 module   
*  for Prestashop v1.7.X         
*  @author themarketer.com  
*  @copyright  2022-2023 theMarketer.com    
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/

const mktr = {
	idProduct:0,
	idProductAttribute:0,
	qty:0
};

;jQuery(function ($) {
    prestashop.on('updateCart', function (event) {
		if(typeof event === "object" && typeof event.reason === "object" && event.reason.hasOwnProperty('linkAction'))
        {
			const di = new Date();
			let time = di.getTime();
			
			event.resp.quantity = event.resp.quantity ?? 1;

			if (event.reason.linkAction === "add-to-cart") {
				jQuery.post( window.mktr.siteurl+"modules/themarketer/get-product.php?t="+time, {
					product_id: event.reason.idProduct,
					comb_id: event.reason.idProductAttribute,
					qty: event.resp.quantity
				}, function( skuid ) {
					let data = JSON.parse(skuid);
					
					dataLayer.push({
						event: "__sm__add_to_cart",
						product_id: data.product_id,
						quantity: data.qty,
						variation: data.variation
					});
				});
			} else if (event.reason.linkAction === "delete-from-cart") {
				jQuery.post( window.mktr.siteurl+"modules/themarketer/get-product.php?t="+time, {
					product_id:event.reason.idProduct,
					comb_id:event.reason.idProductAttribute,
					qty:mktr.qty
				}, function( skuid ) {
					let data = JSON.parse(skuid);

					dataLayer.push({
						event: "__sm__remove_from_cart",
						product_id: data.product_id,
						quantity: data.qty,
						variation: data.variation
					});
				});	
			}
		}
    });
	
    jQuery(document).on('click','.remove-from-cart', function () {
		mktr.idProduct = jQuery(this).data('id-product');
		mktr.idProductAttribute = jQuery(this).data('id-product-attribute');
		mktr.qty = 1;
		
		for (let pro of prestashop.cart.products) {
			if (pro.id_product == mktr.idProduct &&
				pro.id_product_attribute == mktr.idProductAttribute) {
				mktr.qty = pro.quantity;
			}
		}
    });

	//check _initiate_checkout
	if(typeof tmpagename !== 'undefined' && tmpagename == 'order'){
		if(tmgetCookie('tm_initate_checkout') ==''){
			tmsetCookie('tm_initate_checkout', 1, 1);
			dataLayer.push({
				event: "__sm__initiate_checkout"
			});
		}
	}	

	jQuery('.product-information .wishlist-button-add').on('click', function () {
		var wl_text = $(this).find('i:first').text();
		var pid = jQuery('#product_page_product_id').val();
		var pidatr = jQuery('.product-information .product-variants.js-product-variants').find("select :selected").val();
		
		jQuery.post( window.mktr.siteurl+"modules/themarketer/get-wishlist.php", { product_id: pid, comb_id: pidatr }, function( d ) {
			let data = JSON.parse(d);
			
			if(wl_text == 'favorite_border'){
				dataLayer.push({
					event: "__sm__add_to_wishlist",
					product_id: data.product_id,
					variation: data.variation
				});
				console.log('wl_added');
			} else if(wl_text == 'favorite'){
				dataLayer.push({
					event: "__sm__remove_from_wishlist",
					product_id: data.product_id,
					variation: data.variation
				});				
				console.log('wl_removed');
			}
		});
	});	
	
	//add-remove from wishlist
	jQuery('#products .wishlist-button-add,.page-home .wishlist-button-add,.wishlist-list-item').on('click', function (ev) {
		var wl_text = $(this).find('i:first').text();
		
		var pid = jQuery(this).closest('article').data('id-product');
		var pidatr = jQuery(this).closest('article').data('id-product-attribute');
		
		jQuery.post( window.mktr.siteurl+"modules/themarketer/get-wishlist.php", { product_id: pid, comb_id: pidatr }, function( d ) {
			let data = JSON.parse(d);
		  
			if(wl_text == 'favorite_border'){
				dataLayer.push({
					event: "__sm__add_to_wishlist",
					product_id: data.product_id,
					variation: data.variation
				});
				console.log('wl_added');
			} else if(wl_text == 'favorite'){
				dataLayer.push({
					event: "__sm__remove_from_wishlist",
					product_id: data.product_id,
					variation: data.variation
				});
				console.log('wl_removed');			  
			}
		});
	});

	//newsletter subscribe
	jQuery('input[name="submitNewsletter"]').on('click', function () {
		var nlemail = jQuery('#blockEmailSubscription_displayFooterBefore input[name="email"]').val();
		if(nlemail !=''){
			jQuery.post( window.mktr.siteurl+"modules/themarketer/add-subscribe.php", { email: nlemail }, function( data ) {console.log(data);});			
		}		
	});
	
	jQuery('#checkout-guest-form button.continue').on('click', function () {	
		if($('input[name="newsletter"]').prop('checked')){			
			var nlemail = $('#field-email').val();			
			jQuery.post( window.mktr.siteurl+"modules/themarketer/add-subscribe.php", { email: nlemail }, function( data ) {console.log(data);});
		}		
	});

	//newsletter subscribe or unsubscribe based on checkbox plus __sm__set_email event
	jQuery('#customer-form .form-control-submit').on('click', function () {
		var nlchecked = jQuery('#customer-form input[name="newsletter"]').val();
		var nlemail = jQuery('#customer-form input[name="email"]').val();
		var tmfirstname = jQuery('#customer-form input[name="firstname"]').val();
		var tmlastname = jQuery('#customer-form input[name="lastname"]').val();
		
		if (jQuery('#customer-form input[name="newsletter"]').prop('checked') !== false) {
			jQuery.post( window.mktr.siteurl+"modules/themarketer/add-subscribe.php", { email: nlemail }, function( data ) {});	
		} else {
			jQuery.post( window.mktr.siteurl+"modules/themarketer/remove-subscribe.php", { email: nlemail }, function( data ) {});
		}

		dataLayer.push({
			event: "__sm__set_email",
			email_address: nlemail,
			firstname: tmfirstname,
			lastname: tmlastname});		
		});
});
//themarketer cookies set-check
function tmsetCookie(cname, cvalue, exdays) {
  const d = new Date();
  d.setTime(d.getTime() + (exdays*30*60*1000));
  let expires = "expires="+ d.toUTCString();
  document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
}
function tmgetCookie(cname) {
  let name = cname + "=";
  let ca = document.cookie.split(';');
  for(let i = 0; i < ca.length; i++) {
    let c = ca[i];
    while (c.charAt(0) == ' ') {
      c = c.substring(1);
    }
    if (c.indexOf(name) == 0) {
      return c.substring(name.length, c.length);
    }
  }
  return "";
}