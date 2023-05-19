{*
*  theMarketer module   
*  for Prestashop v1.7.X         
*  @author themarketer.com  
*  @copyright  2022-2023 theMarketer.com    
*  @license    http:// opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
{if $tm_enable == 1}

	<!-- Themarketer page name/data -->
	   <script>
	   {literal}
		function decodeHtml(html) {
			var txt = document.createElement('textarea');
			txt.innerHTML = html;
			return txt.value;
		}	   
	   window.dataLayer = window.dataLayer || [];
		var tm_page_name = "{/literal}{if isset($tm_page_name)}{$tm_page_name|cleanHtml nofilter}{/if}{literal}";
		{/literal}{if isset($tm_wishlist)}alert(1);{/if}{literal}
		// alert(tm_page_name);
		// start get page info
		if(tm_page_name == 'product'){
		let currentPage = location.href;

		//  listen for changes
			setInterval(function()
			{
				if (currentPage != location.href)
				{
					//  page has changed, set new page as 'current'
					currentPage = location.href;
				 //    alert(currentPage);
				}
			}, 500);
			{/literal}{if isset($tm_product_compination)}{literal}
			var compination = {/literal}{$tm_product_compination|cleanHtml nofilter}{literal};
			{/literal}{else}{literal}
			var compination = 0; 
			{/literal}{/if}{literal}
			
			{/literal}{if isset($tm_product_id)}
			{$tm_product_id = $tm_product_id}
			{else}
			{$tm_product_id = 0}
			{/if}{literal}
			
			if(compination > 0){
				var pid = "{/literal}{$tm_product_id}_{$tm_product_compination|cleanHtml nofilter}{literal}";
			} else {
				var pid = {/literal}{$tm_product_id|cleanHtml nofilter}{literal};
			}
		
			dataLayer.push({
				event: "__sm__view_product",
				product_id: pid
			});	
		} else if(tm_page_name == 'index'){
			dataLayer.push({
				event: "__sm__view_homepage"
			});		
		} 
		 else if(tm_page_name == 'category'){
			{/literal}{if isset($tm_category)}
			{$tm_category = $tm_category}
			{else}
			{$tm_category = ''}
			{/if}{literal}		 
			dataLayer.push({
				event: "__sm__view_category",
				category: "{/literal}{$tm_category|cleanHtml nofilter}{literal}"
			});	
		}			
		else if(tm_page_name == 'manufacturer'){
			{/literal}{if isset($page.meta.title)}
			{$page.meta.title = $page.meta.title}
			{else}
			{$page.meta.title= ''}
			{/if}{literal}		
			dataLayer.push({
				event: "__sm__view_brand",
				name: "{/literal}{$page.meta.title|cleanHtml nofilter}{literal}"
			});		
		} else if(tm_page_name == 'order'){
			dataLayer.push({
				event: "__sm__initiate_checkout"
			});		
		} else if(tm_page_name == 'order-confirmation'){
			dataLayer.push({
				{/literal}
					{if isset($tm_order) && $tm_order != ''}{$tm_order|cleanHtml nofilter}{/if}
				{literal}
			});		
		} else if(tm_page_name == 'search'){
			dataLayer.push({
				event: "__sm__search",
				search_term: "{/literal}{if isset($tm_search)}{$tm_search|cleanHtml nofilter}{/if}{literal}"
			});		
		}  else if(tm_page_name == 'my-account'){	
			{/literal}
			{if isset($tm_email)}
			var tmemail = "{$tm_email|cleanHtml nofilter}";
			var tmfirstname = "{$tm_firstname|cleanHtml nofilter}";
			var tmlastname = "{$tm_lastname|cleanHtml nofilter}";
			{/if}
			{literal}
			dataLayer.push({event: "__sm__set_email",email_address: tmemail,firstname: tmfirstname,lastname: tmlastname});
			{/literal}{if isset($tm_phone)} var tmphone = "{$tm_phone}";
			{literal}dataLayer.push({event: "__sm__set_phone",tmphone});{/literal}
			{/if}
		} 	 	
		{if isset($tm_login_email)}
			var tmemail = "{$tm_login_email|cleanHtml nofilter}";
			var tmfirstname = "{$tm_login_firstname|cleanHtml nofilter}";
			var tmlastname = "{$tm_login_lastname|cleanHtml nofilter}";
			{literal}dataLayer.push({event: "__sm__set_email",email_address: tmemail,firstname: tmfirstname,lastname: tmlastname});{/literal}
		{/if}	 	
		{if isset($tm_login_phone)}
			var tmephone = "{$tm_login_phone|cleanHtml nofilter}";
			{literal}dataLayer.push({event: "__sm__set_phone",tmphone});{/literal}
		{/if}
	  </script> 

	<!-- END Themarketer page name/data -->
  {/if}