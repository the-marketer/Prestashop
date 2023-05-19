{*
*  theMarketer module   
*  for Prestashop v1.7.X         
*  @author themarketer.com  
*  @copyright  2022-2023 theMarketer.com    
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
<script>
var alertordersfeed = "{l s='The feed link copying to clipboard was successful!' mod='themarketer'}";
{literal}
function copyLink() {
  var copyText = document.querySelector("#THEMARKETER_ORDERS_FEED_LINK"); 
  copyText.select(); document.execCommand("copy");
  
}
document.querySelector("#spanCopy").addEventListener("click", copyLink);

function copyLinkProducts() {
  var copyText = document.querySelector("#THEMARKETER_PRODUCTS_FEED_LINK"); 
  copyText.select(); document.execCommand("copy");
  
}
document.querySelector("#spanCopyProducts").addEventListener("click", copyLinkProducts);

function copyLinkProductsCron() {
  var copyText = document.querySelector("#THEMARKETER_PRODUCTS_FEED_CRON"); 
  copyText.select(); document.execCommand("copy");
  
}
document.querySelector("#spanCopyProductsCron").addEventListener("click", copyLinkProductsCron);

function copyLinkCats() {
  var copyText = document.querySelector("#THEMARKETER_CATEGORIES_FEED_LINK"); 
  copyText.select(); document.execCommand("copy");
  
}
document.querySelector("#spanCopyCats").addEventListener("click", copyLinkCats);

function copyLinkBrands() {
  var copyText = document.querySelector("#THEMARKETER_BRANDS_FEED_LINK"); 
  copyText.select(); document.execCommand("copy");
  
}
document.querySelector("#spanCopyBrands").addEventListener("click", copyLinkBrands);

function copyLinkReviews() {
  var copyText = document.querySelector("#THEMARKETER_REVIEWS_FEED_LINK"); 
  copyText.select(); document.execCommand("copy");
  
}
document.querySelector("#spanCopyReviews").addEventListener("click", copyLinkReviews);


//only read inputs
document.getElementById("THEMARKETER_ORDERS_FEED_LINK").setAttribute("readonly", "true");
document.getElementById("THEMARKETER_PRODUCTS_FEED_LINK").setAttribute("readonly", "true");
document.getElementById("THEMARKETER_PRODUCTS_FEED_CRON").setAttribute("readonly", "true");
document.getElementById("THEMARKETER_CATEGORIES_FEED_LINK").setAttribute("readonly", "true");
document.getElementById("THEMARKETER_BRANDS_FEED_LINK").setAttribute("readonly", "true");
document.getElementById("THEMARKETER_BRANDS_FEED_LINK").setAttribute("readonly", "true");
document.getElementById("THEMARKETER_REVIEWS_FEED_LINK").setAttribute("readonly", "true");
var enbl = document.getElementById("THEMARKETER_ENABLE_NOTIFICATIONS");
enbl.options[enbl.options.selectedIndex].selected = false;
{/literal}
</script>