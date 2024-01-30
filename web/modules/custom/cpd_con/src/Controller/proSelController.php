<?php
  
namespace Drupal\cpd_con\Controller;


use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use \Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\views\Controller;
use Drupal\media\Entity\Media;
use Drupal\file\Entity\File;
use Drupal\Core\Database\Database;
use Drupal\Core\Render\Markup;
use Drupal\profile\Entity\Profile;
use Drupal\user\Entity\User;

/**
 * Controller routines for AJAX example routes.
 */
class proSelController extends ControllerBase {

  function displayOutput(){

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'http://members-dev.slie-sl.org/v1/763c02b5bd1163ad7ae68119fafebd45/login',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => 'username=user&password=userpass',
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/x-www-form-urlencoded',
    'Cookie: PHPSESSID=2dcec329d2432919ee76944d866683e0'
  ),
));

$response = curl_exec($curl);

curl_close($curl);
 $result = json_decode($response, true);

//print_r($result);

$curl1 = curl_init();

curl_setopt_array($curl1, array(
  CURLOPT_URL => 'http://members-dev.slie-sl.org/v1/763c02b5bd1163ad7ae68119fafebd45/profile',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    'Cookie: PHPSESSID=2dcec329d2432919ee76944d866683e0'
  ),
));

$response1 = curl_exec($curl1);

curl_close($curl1);
 $result1 = json_decode($response1, true);
 //print_r($result1);
//die("stop");
 $account = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
$usrid=\Drupal::currentUser()->id();
  $user = $account->get('field_name')->value;
  $field_membership_number = $account->get('field_membership_number')->value;
  $field_yearly_pdu_target = $account->get('field_yearly_pdu_target')->value;
  $field_address = $account->get('field_address')->value;
  $field_date_of_birth = $account->get('field_date_of_birth')->value;  
  if(!empty($field_date_of_birth)){
    $field_date_of_birth = date('d/m/Y', strtotime($field_date_of_birth));
  }
  $field_place_of_birth = $account->get('field_place_of_birth')->value;
  $field_nationality = $account->get('field_nationality')->value;
  $field_present_employer = $account->get('field_present_employer')->value;
  $field_correspondence = $account->get('field_correspondence')->value;
  $mail = $account->get('mail')->value;
  $field_telephone = $account->get('field_telephone')->value;
  $field_bio = $account->get('field_bio')->value;
  $field_pdu_total = $account->get('field_pdu_total')->value;
  $field_membership__level = $account->get('field_membership__level')->target_id;
  if(!empty($field_membership__level)){
     $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($field_membership__level);
     $field_membership__level = $term->getName();
    }
  $field_membership_status = $account->get('field_membership_status_')->value;
  $field_discipline  = $account->get('field_discipline')->value;
  $field_next_payment_due_date  = $account->get('field_next_payment_due_date')->value;
  if(!empty($field_next_payment_due_date)){
    $field_next_payment_due_date = date('d/m/Y', strtotime($field_next_payment_due_date));
  }
  $field_next_renewal_date  = $account->get('field_next_renewal_date_')->value;
  if(!empty($field_next_renewal_date)){
    $field_next_renewal_date = date('d/m/Y', strtotime($field_next_renewal_date));
  }
  $q=\Drupal::database()->query("SELECT * FROM file_managed as fd INNER JOIN user__user_picture as pic ON fd.fid=pic.user_picture_target_id where pic.entity_id=$usrid");
  foreach ($q as $key => $value) {
   // print_r($value->uri);
    $uri=$value->uri;
  }
  if(!empty($uri)){
  $absolute_path = \Drupal::service('file_system')->realpath($uri);
}
  if(!empty($absolute_path)){
    //$absolute_path = "/sites/default".$absolute_path;
   $absolute_path = file_create_url($uri);
  }
  else{
     $absolute_path = "/themes/custom/platon/images/profile.png";
  }
// print_r($absolute_path);
// die("tets");
 $editProfile = '/user/'.$usrid.'/edit';
 if(!empty($result1['Profile Information'][0]['Email'])){
$output="<div class='float-right'><a class='button button-action button--primary button--small' href='".$editProfile."'> Edit profile</a></div><div class='profile'><p><img src='". $absolute_path."' height='200px' width='200px'></p>
<p><b>Name:</b> ". $user."</p>
<p><b>Membership Number:</b> ". $field_membership_number."</p>
<p><b>Membership Level:</b> ". $field_membership__level."</p>
<p><b>Membership Status:</b> ". $field_membership_status."</p>
<p><b>Yearly PDU Target	:</b> ". $field_yearly_pdu_target."</p>
<p><b>PDU Earned:</b> ". $field_pdu_total."</p>
<p><b>Address:</b> ". $field_address."</p>
<p><b>Date of Birth:</b> ". $field_date_of_birth."</p>
<p><b>Place of Birth:</b> ". $field_place_of_birth."</p>
<p><b>Discipline:</b> ". $field_discipline."</p>
<p><b>Nationality:</b> ". $field_nationality."</p>
<p><b>Present Employer:</b> ". $field_present_employer."</p>
<p><b>Correspondence:</b> ". $field_correspondence."</p>
<p><b>Email:</b> ". $mail."</p>
<p><b>Telephone:</b> ". $field_telephone."</p>
<p><b>Bio:</b> ". $field_bio."</p>
<p><b>Next payment Due Date:</b> ". $field_next_payment_due_date."</p>
<p><b>Next Renewal Date:</b> ". $field_next_renewal_date."</p></div>";
}  
else{
	$output="<div class='float-right'><a class='button button-action button--primary button--small' href='".$editProfile."'> Edit profile</a></div><div class='profile'><p><img src='". $absolute_path."' height='200px' width='200px'></p>
<p><b>Name:</b> ". $user."</p>
<p><b>Membership Number:</b> ". $field_membership_number."</p>
<p><b>Membership Level:</b> ". $field_membership__level."</p>
<p><b>Membership Status:</b> ". $field_membership_status."</p>
<p><b>Yearly PDU Target:</b> ". $field_yearly_pdu_target."</p>
<p><b>PDU Earned:</b> ". $field_pdu_total."</p>
<p><b>Address:</b> ". $field_address."</p>
<p><b>Date of Birth:</b> ". $field_date_of_birth."</p>
<p><b>Place of Birth:</b> ". $field_place_of_birth."</p>
<p><b>Discipline:</b> ". $field_discipline."</p>
<p><b>Nationality:</b> ". $field_nationality."</p>
<p><b>Present Employer:</b> ". $field_present_employer."</p>
<p><b>Correspondence:</b> ". $field_correspondence."</p>
<p><b>Email:</b> ". $mail."</p>
<p><b>Telephone:</b> ". $field_telephone."</p>
<p><b>Bio:</b> ". $field_bio."</p>
<p><b>Next payment Due Date:</b> ". $field_next_payment_due_date."</p>
<p><b>Next Renewal Date:</b> ". $field_next_renewal_date."</p></div>";
}

if(isset($result1['Profile Information'][0]['Email'])){
$mails=$result1['Profile Information'][0]['Email'];
$q=\Drupal::database()->query("SELECT * FROM users_field_data where mail='".$mails."'");
foreach ($q as $key => $value) {
$usrd=$value->uid;
}
if(isset($usrd)){

}
else{
  
  $user = User::create();
  $lang='en';
  // Mandatory.
  $user->setPassword('testtt');
  $user->enforceIsNew();
  $user->setEmail($result1['Profile Information'][0]['Email']);
  $user->setUsername($result1['Profile Information'][0]['Name']);
  $user->addRole('engineer');


  // Optional.
  $user->set('init', $result1['Profile Information'][0]['Email']);
  $user->set('langcode', $lang);
  $user->set('preferred_langcode', $lang);
  $user->set('preferred_admin_langcode', $lang);
  $user->set('field_bio', 'tttttttttttttt');
  $user->set('field_address', $result1['Profile Information'][0]['Address']);
  //$user->set('field_date_of_birth', $result1['Profile Information'][0]['Date of Birth']);
  $user->set('field_place_of_birth', $result1['Profile Information'][0]['Place of Birth']);
  $user->set('field_nationality', $result1['Profile Information'][0]['Nationality']);
  $user->set('field_present_employer', $result1['Profile Information'][0]['Present Employer']);
  $user->set('field_correspondence', $result1['Profile Information'][0]['Correspondence']);
  $user->set('field_telephone', $result1['Profile Information'][0]['Telephone']);
  $user->activate();

  // Save user account.
 // $user->save();
}

 } 

  // $profile = Profile::create([
  //   'type' => 'customer',
  //   'uid' => $user->id(),
  //   'field_agree_terms' => 1,
  //   'field_first_name' => $first_name,
  //   'field_last_name' => $last_name,
  // ]);

  // $profile->setDefault(TRUE);
  // $profile->save();
//print_r($result1);
return array('#markup' => Markup::create($output));
  }

   function regOutput(){
    $output="Register through SLIE";
    return array('#markup' => Markup::create($output));
  }
}
  