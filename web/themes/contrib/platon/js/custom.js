Drupal.AjaxCommands.prototype.colorboxLoadOpen = function (ajax, response) {
        var ajaxDialogOption = null;
        if (typeof ajax.dialog  !== "undefined"){
        var ajaxDialogOption = JSON.parse(JSON.stringify(ajax.dialog));
        }
        jQuery.colorbox(jQuery.extend({}, drupalSettings.colorbox, ajaxDialogOption, {
          html: response.data,
        }));
        Drupal.attachBehaviors();
      };


(function (Drupal, drupalSettings) {
   var initialized; 
  Drupal.behaviors.platon = {
    attach: function (context, settings) {
       jQuery("#catalogued-custom-status-filter ").on("change","[id^=edit-custom-status]",function(){
           var selValue = jQuery(this).val(); 
           jQuery("[id^=edit-label]").val(selValue);
       }); console.log('hi');
    jQuery(".view-my-activity .group-member").each(function(){
     
    // string = jQuery(this).find(".activity-title").text();
    // query = '?title='+string;
    // href = jQuery(this).find(".reflect a").attr( 'href');
    // if (href) {
    //       jQuery(this).find(".reflect a").attr('href', href + query);
    //     }
    linktext = jQuery(this).find(".views-field.views-field-id a.changes").text();
    linkurl = jQuery(this).find(".views-field.views-field-id a.changes").attr('href');
    trimlinktext = jQuery.trim(linktext);
    if(trimlinktext == 'Change Status'){
      jQuery(this).find(".views-field.views-field-nothing-2 a").attr('href', linkurl);
      jQuery(this).find(".views-field.views-field-nothing-2 a").text(trimlinktext); 
    }

   //  linkhtml = jQuery(this).find(".views-field.views-field-id .reflected-link a").attr( 'href');
   //  id = jQuery(this).find(".views-field.views-field-id .reflected-link a").text();
   // trimid = jQuery.trim(id);
   // if(trimid == 'Reflected'){
  // jQuery(this).find(".reflect a").attr( 'href', linkhtml);
  // jQuery(this).find(".reflect a").text(id);
  // }
   
});

if (!initialized) {
        initialized = true;
      }
    }
  }
} (Drupal, drupalSettings));

jQuery('.public-facing-menu-list-items').mouseenter(function () {
       jQuery(this).find('.sf-sub-indicator').addClass('expand');
     
     });

 jQuery('.public-facing-menu-list-items').mouseleave(function () {
      jQuery(this).find('.sf-sub-indicator').removeClass('expand');
     } );
jQuery(document).ready(function() {
  if(jQuery('#block-platon-page-title h1').text() == 'User profile'){ console.log('ji');
   jQuery('#block-platon-page-title h1').text("my account") ;
}
  /*raqim js*/
  if(jQuery('body').hasClass('user-not-logged-in')){
   if(jQuery('#block-platon-branding a').attr('rel') == 'home'){
       jQuery('#block-platon-branding a').attr('href', '');
       jQuery('#block-platon-branding a').on('click', function(event){
        event.preventDefault();
       })
       
      var logo_path = jQuery('#block-platon-branding a img').attr("src").split("sites");
      var update_logo_path = 'sites/'+logo_path[1];
      if(logo_path[0]){ console.log('logo updated');
       jQuery('#block-platon-branding a img').attr("src", update_logo_path );
      }else{
        console.log('logo not updated');   
      }
   }
  
jQuery('.user-not-logged-in .switch-link a').on('click', function(event){
 event.preventDefault()
 var current_clicked_url = jQuery(this).attr('href');

var clicked_url = current_clicked_url.split("/"); 
if(clicked_url['2'] == 'register'){
 jQuery('.form-wrapper.user-register').css('display','block');
  jQuery('.form-wrapper.user-login').css('display','none');
   jQuery('.form-wrapper.user-password').css('display','none');
}
else if(clicked_url['2'] == 'login'){
 jQuery('.form-wrapper.user-register').css('display','none');
 jQuery('.form-wrapper.user-login').css('display','block');
 jQuery('.form-wrapper.user-password').css('display','none');
}
else{
   jQuery('.form-wrapper.user-register').css('display','none');
 jQuery('.form-wrapper.user-login').css('display','none');
 jQuery('.form-wrapper.user-password').css('display','block');
}
});


 }else{
    if( jQuery('body').hasClass('page-faq-view-list') ){
    if( !jQuery('body').hasClass('toolbar-fixed') ){
     jQuery('.view-display-id-page_1 .view-header').empty();
    }}
}

 
if(jQuery('div').hasClass('view-news')){
   jQuery('#views-exposed-form-news-page-1 .js-form-item-created-1-max label').text('To:');
  }
if(jQuery('div').hasClass('view-resources')){
 jQuery('.opd-download-resource li').each(function() {
   var link = jQuery(this).text();
   var file_name = link.split("/");
   var button_text = '<div class ="opd-down-wrapper"><span style="width:60%;display: inline-block;">'+file_name.reverse()[0]+'</span><a class="button button-action button--primary button--small"  href="'+link+'" download>Download</a></div>';
   jQuery(this).html(button_text);
}) 
} 
/*end raqim js*/

  jQuery(".navbar-toggler").click(function(){
     jQuery(".navbar-toggler").removeClass("open");
  document.getElementById("mySidenav").style.width = "320px";
});


 jQuery(".closebtn").click(function(){
  document.getElementById("mySidenav").style.width = "0";
});
if (jQuery(window).width() < 767) {
   var sides=jQuery("#sidebar-first").detach();
   jQuery( "#mySidenav" ).append(sides);
   jQuery( "#sidebar-first" ).css("margin-top","50px");  
}

//if(window.matchMedia("(max-width: 600px)").matches){
jQuery( "#block-gobackhistoryblock" ).before("<div id='site-header' class='res-header'><div id='header-right' class='responsive-header'></div>");
usernoti = jQuery( ".user-notifications.dropdown" ).clone();
userblock = jQuery( ".user-block.ml-3.dropdown" ).clone();
jQuery( "#header-right.responsive-header" ).append(usernoti);
jQuery( "#header-right.responsive-header" ).append(userblock);

   // } 

//change title of My audit content detail
var atitle = jQuery('.view-footer .audit-title').text();
var aconc = ' - ' + jQuery('.auditor p.a-conc').text();
var audititle = atitle + aconc;
if(audititle){
jQuery('.section-auditor .block-page_title_block h1').text(audititle);
}

quicktabs = jQuery( "#quicktabs-engineer_under_audit_tabs .quicktabs-tabs" ).detach();
jQuery( ".view-engineer-audit-search.view-display-id-block_1" ).after(quicktabs);

if(jQuery("body:has(#block-myauditordashboard)")){
jQuery('#block-myengineerdashboard').css("display","none");
}

setTimeout(function(){
 jQuery(".fields-content.is-member").each(function(){
string = jQuery(this).find(".activity-title").text();
query = '?title='+string;

href = jQuery(this).find(".reflect a").attr( 'href');
if (href) {
      jQuery(this).find(".reflect a").attr('href', href + query);
    }
    }); 



  jQuery(".view-activities .table tr").each(function(){
    linkhtml = jQuery(this).find(".views-field.views-field-nid .reflected-link a").attr( 'href');
    id = jQuery(this).find(".views-field.views-field-nid .reflected-link a").text();
    trimid = jQuery.trim(id);
    if(trimid == 'Reflected'){
      jQuery(this).find(".reflect a").attr( 'href', linkhtml);
      jQuery(this).find(".reflect a").text(id);
    }
  });


jQuery(".view-opigno-training-catalog .fields-content").each(function(){
progress = jQuery(this).find('.progress-value').text();
view = jQuery(this).find('.not-take-link-wrapper').attr( 'href');
trim = jQuery.trim(progress);
if(trim == '100%'){
jQuery(this).find('.use-ajax.continue-link').attr( 'href', view);
jQuery(this).find('.use-ajax.continue-link').text('view Activity');
}
});
//remove add to cart link from catalog listing//
jQuery(".view-opigno-training-catalog .fields-content.is-not-member").each(function(){
view = jQuery(this).find('.not-take-link-wrapper').attr( 'href');

notmember = jQuery(this).find('.views-field-opigno-lp-take-link .field-content a').attr( 'href', view);
});

//add id on conclude Audit button
var eid = jQuery(location).attr('pathname');
var eids = eid.split('/');
if(eids[2]){
var audit = jQuery('#block-concludeauditbutton .body a').attr( 'href');
var uid = '?uid='+eids[2];
jQuery("#block-concludeauditbutton .body a").attr('href', audit + uid);
}

}, 1000);



function GetParameterValues(param) {  
            var url = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');  
            for (var i = 0; i < url.length; i++) {  
                var urlparam = url[i].split('=');  
                if (urlparam[0] == param) {  
                    return urlparam[1];  
                }  
            }  
  }

        var name = GetParameterValues('title');  
        if(name){
  jQuery(".form-item-field-activity-0-target-id #edit-field-activity-0-target-id").val(decodeURIComponent(name));
  jQuery(".form-item-field-activity--0-target-id #edit-field-activity-0-target-id").val(decodeURIComponent(name));
  }

   var statustitle = GetParameterValues('id');  
        if(statustitle){
  jQuery(".form-item-field-activity-0-target-id #edit-field-activity-0-target-id").val(decodeURIComponent(statustitle));
  }

      var member = GetParameterValues('q'); 
      if(member == 'catalogued'){
jQuery('.group-member.views-row').css("display","none");
jQuery(".block-page_title_block h1").text('Select Catalogued Activity');

//add empty msg on catalogue?q=catalogued page
var totalCount=jQuery(".view-opigno-training-catalog.view-id-opigno_training_catalog .view-content > div").length;
var grupmen =jQuery(".view-opigno-training-catalog.view-id-opigno_training_catalog .view-content .group-member").length;
if(totalCount == grupmen){
var hhtml = "<div class='emptyres'>You have enrolled in all Activities.</div>"
jQuery( ".view-opigno-training-catalog.view-id-opigno_training_catalog .view-content").after(hhtml);
}
      } 




     var userid = GetParameterValues('uid'); 
      if(userid){
      jQuery(".page-node-add-audit #block-tabsforaddaudits").css("display","block");

        var count = 1;
       jQuery("#block-tabsforaddaudits .quicktabs-tabs li[id^=quicktabs-tab-engineer_under_audit_tabs] a").each(function(){
           var userlink = jQuery(this).attr( 'href');
           var tabid = '?location=tab'+count;
        jQuery(this).attr('href', userlink + userid + tabid);
        jQuery(this).addClass("tab" + count);
        count++
     });
       } 


   var engineerid = GetParameterValues('eid'); 
      if(engineerid){
        jQuery(".page-node-audit.entity-edit #block-tabsforaddaudits").css("display","block");
        var count = 1;
       jQuery("#block-tabsforaddaudits .quicktabs-tabs li[id^=quicktabs-tab-engineer_under_audit_tabs] a").each(function(){
           var userlink = jQuery(this).attr( 'href');
           var tabid = '?location=tab'+count;
        jQuery(this).attr('href', userlink + engineerid + tabid);
        jQuery(this).addClass("tab" + count);
        count++
     });
       } 

var loc = GetParameterValues('location');
 
if (loc == 'tab1') {
  jQuery(".quicktabs-tabs .profile-view a.quicktabs-loaded").click();
}
else if (loc == 'tab2') {
  jQuery(".quicktabs-tabs .cpd-action-plan a.quicktabs-loaded").click();
}
else if (loc == 'tab3') {
  jQuery(".quicktabs-tabs .view-booked-activities a.quicktabs-loaded").click();
}
else if (loc == 'tab4') {
  jQuery(".quicktabs-tabs .view-cpd-reflections a.quicktabs-loaded").click();
}
else if (loc == 'tab5') {
  jQuery(".quicktabs-tabs .view-previous-audits a.quicktabs-loaded").click();
}

if(location.search.indexOf('location=')<=0){
   jQuery(".quicktabs-tabs .profile-view a.quicktabs-loaded").click();
}

var pageURL = jQuery(location).attr('pathname');
var n = pageURL.split('/');
if(n[3] == 'complete'){
jQuery(".form-item-path-to-training a").attr("href", "/my-activities");
}
//add engineer id on edit auit link on detail page
setTimeout(function(){
  var engid = GetParameterValues('eid'); 
if(engid){
  jQuery(".page-node-audit.page-ready .list-inline-item a").each(function(){
       var audit = jQuery(this).attr( 'href');
       var eeid = '?eid='+engid;
       jQuery(this).attr('href', audit + eeid);
     });
  }
  }, 1000);
function openNav() {
  document.getElementById("mySidenav").style.width = "250px";
}

function closeNav() {
  document.getElementById("mySidenav").style.width = "0";
}
  });








     
    
    
   