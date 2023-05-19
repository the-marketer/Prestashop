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
			dataLayer.push({
				{/literal}
					{if isset($tm_order) && $tm_order != ''}{$tm_order|cleanHtml nofilter}{/if} 	
				{literal}
			});	{/literal}	
					{if isset($tm_login_email_order)}
						var tmemail_order = "{$tm_login_email_order|cleanHtml nofilter}";
						var tmfirstname_order = "{$tm_login_firstname_order|cleanHtml nofilter}";
						var tmlastname_order = "{$tm_login_lastname_order|cleanHtml nofilter}";
						{literal}dataLayer.push({event: "__sm__set_email",email_address: tmemail_order,firstname: tmfirstname_order,lastname: tmlastname_order});{/literal}
					{/if}	 	
					{if isset($tm_login_phone_order)}
						var tmphone_order = "{$tm_login_phone_order|cleanHtml nofilter}";
						{literal}dataLayer.push({event: "__sm__set_phone",phone: tmphone_order});{/literal}
					{/if}	
</script>
	<!-- END Themarketer page name/data -->
  {/if}