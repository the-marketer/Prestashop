	/**
*  theMarketer V1.0.0 module   
*  for Prestashop v1.7.X         
*  @author themarketer.com  
*  @copyright  2022-2023 theMarketer.com    
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*/
//add to cart 
;jQuery(function ($) {

	//var siteurl = "https://click4fashion.com/";

    prestashop.on('updateCart', function (event) {
        setTimeout(function () {
            if(event.reason.cart === undefined){}else{
				var qty = event.reason.cart.products.reverse()[0].embedded_attributes.quantity;
				var skunum = event.reason.cart.products.reverse()[0].embedded_attributes.reference;
				var pid = event.reason.idProduct;
				var pidattr = 0;
				if (event.reason.idProductAttribute > 0) {
					var pidattr = event.reason.idProductAttribute;
					pid = pid + '_' + pidattr;
				}
				console.log(pid);
				dataLayer.push({
					event: "__sm__add_to_cart",
					product_id: pid,
					quantity: qty,
					variation: {
						id: pidattr,
						sku: skunum
					}
				});
            }
        }, 100);
    });

    jQuery(document).on('click','.remove-from-cart', function () {
		const di = new Date();
		let time = di.getTime();	  		
        pid = jQuery(this).data('id-product');
		
        pidatr = jQuery(this).data('id-product-attribute');
        if (pidatr > 0) {
            pida = pid + '_' + pidatr;
        } else {
            pida = pid;
			pidatr = '';
        }
		console.log(pida);
		jQuery.post( siteurl+"modules/themarketer/get-product.php?t="+time, { product_id: pid, comb_id: pidatr }, function( skuid ) {
			console.log(skuid);
					var qty = 1;
					dataLayer.push({
						event: "__sm__remove_from_cart",
						product_id: pida,
						quantity: qty,
						variation: {
							id: pidatr,
							sku: skuid
						}
					});
		});		
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
		if (pidatr > 0) {
		   comp = pidatr;
	   } else {
		  comp = 0;
	   }	
		jQuery.post( siteurl+"modules/themarketer/get-wishlist.php", { product_id: pid, comb_id: comp }, function( data ) {
			
		  if(data != 'no'){
		  const dataarr = data.split("@");
		  var statuswish = dataarr[0];
		  var reference = dataarr[1];
			if (pidatr > 0) {
				pida = pid + '_' + pidatr;
			} else {
				pida = pid;
			}

			if(wl_text == 'favorite_border'){
			dataLayer.push({
				event: "__sm__add_to_wishlist",
				product_id: pida,
				variation: {
					id: pidatr,
					sku: reference
				}
			});
				
			console.log('wl_added');
			} else if(wl_text == 'favorite'){
				dataLayer.push({
					event: "__sm__remove_from_wishlist",
					product_id: pida,
					variation: {
						id: pidatr,
						sku: reference
					}
				});				
			  
			console.log('wl_removed');
			}  
		  } else {}
		});
	});	
	
	//add-remove from wishlist
	jQuery('#products .wishlist-button-add,.page-home .wishlist-button-add,.wishlist-list-item').on('click', function (ev) {
		//alert(JSON.stringify(ev.target, null, 4));
		var wl_text = $(this).find('i:first').text();
		//alert(wl_text);
		var pid = jQuery(this).closest('article').data('id-product');
		var pidatr = jQuery(this).closest('article').data('id-product-attribute');
		if (pidatr > 0) {
		   comp = pidatr;
	   } else {
		  comp = 0;
	   }	
	   console.log(pid);console.log(pidatr);
		jQuery.post( siteurl+"modules/themarketer/get-wishlist.php", { product_id: pid, comb_id: comp }, function( data ) {
			
		  if(data != 'no'){
		  const dataarr = data.split("@");
		  var statuswish = dataarr[0];
		  var reference = dataarr[1];
			if (pidatr > 0) {
				pida = pid + '_' + pidatr;
			} else {
				pida = pid;
			}
			console.log(statuswish);
			if(wl_text == 'favorite_border'){
			dataLayer.push({
				event: "__sm__add_to_wishlist",
				product_id: pida,
				variation: {
					id: pidatr,
					sku: reference
				}
			});
			console.log('wl_added');
			} else if(wl_text == 'favorite'){
				dataLayer.push({
					event: "__sm__remove_from_wishlist",
					product_id: pida,
					variation: {
						id: pidatr,
						sku: reference
					}
				});				

			console.log('wl_removed');			  
			}  
		  } else {}
		});
	});

	//newsletter subscribe
	jQuery('input[name="submitNewsletter"]').on('click', function () {
		var nlemail = jQuery('#blockEmailSubscription_displayFooterBefore input[name="email"]').val();
		if(nlemail !=''){
			jQuery.post( siteurl+"modules/themarketer/add-subscribe.php", { email: nlemail }, function( data ) {console.log(data);});			
		}		
	});
	
	jQuery('#checkout-guest-form button.continue').on('click', function () {	
		if($('input[name="newsletter"]').prop('checked')){			
			var nlemail = $('#field-email').val();			
			jQuery.post("../modules/themarketer/add-subscribe.php", { email: nlemail }, function( data ) {console.log(data);});
		}		
	});

	//newsletter subscribe or unsubscribe based on checkbox plus __sm__set_email event
	jQuery('#customer-form .form-control-submit').on('click', function () {
		var nlchecked = jQuery('#customer-form input[name="newsletter"]').val();
		var nlemail = jQuery('#customer-form input[name="email"]').val();
		var tmfirstname = jQuery('#customer-form input[name="firstname"]').val();
		var tmlastname = jQuery('#customer-form input[name="lastname"]').val();
		
		if (jQuery('#customer-form input[name="newsletter"]').prop('checked') !== false) {
			jQuery.post( siteurl+"modules/themarketer/add-subscribe.php", { email: nlemail }, function( data ) {});	
		} else {
			jQuery.post( siteurl+"modules/themarketer/remove-subscribe.php", { email: nlemail }, function( data ) {});
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