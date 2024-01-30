<?php

namespace Drupal\cpd_con\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Database\Database;

/**
 * Provides a block with a simple text.
 *
 * @Block(
 *   id = "profile_block",
 *   admin_label = @Translation("Profile Block"),
 * )
 */
class ProfileBlock extends BlockBase {
  /**
   * {@inheritdoc}
   */
  public function build() {
    $url = strtok($_SERVER["REQUEST_URI"], '?');
  	$ids=explode("/", $url);
    $ids = $ids[2];
  $account = \Drupal\user\Entity\User::load($ids); 
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
   $q=\Drupal::database()->query("SELECT * FROM file_managed as fd INNER JOIN user__user_picture as pic ON fd.fid=pic.user_picture_target_id where pic.entity_id=$ids");
  foreach ($q as $key => $value) {
   // print_r($value->uri);
    $uri=$value->uri;
  }
  if(!empty($uri)){
  $absolute_path = \Drupal::service('file_system')->realpath($uri);
 // $absolute_path = file_create_url($uri);
}
  if(!empty($absolute_path)){
  //  $absolute_path = "/sites/default".$absolute_path;
    $absolute_path = file_create_url($uri);
  }
  else{
     $absolute_path = "/themes/custom/platon/images/profile.png";
  }
  	$output="<div class='profile iii'><p><img src='". $absolute_path."' height='200px' width='200px'></p>
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
    return [
      '#markup' => $this->t($output),
    ];
  }
}