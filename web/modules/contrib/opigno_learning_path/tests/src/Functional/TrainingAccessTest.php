<?php

namespace Drupal\Tests\opigno_learning_path\Functional;

use Drupal\Core\Url;
use Drupal\opigno_learning_path\LearningPathAccess;

/**
 * Tests an access to Training.
 *
 * @group opigno_learning_path
 */
class TrainingAccessTest extends LearningPathBrowserTestBase {

  /**
   * Tests training visibility for users.
   */
  public function testTrainingVisibility() {
    // Test access if training is public.
    $group = $this->createGroup([
      'field_learning_path_visibility' => 'public',
      'field_learning_path_enable_forum' => 1,
    ]);
    $this->drupalLogout();
    // Create authenticated user to check training access.
    $authenticated = $this->createUser();
    $this->drupalLogin($authenticated);

    $url = Url::fromRoute('entity.group.canonical', ['group' => $group->id()]);

    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403, 'Authenticated user can not see an main page for unpublished training.');

    // Make training published.
    $group->set('field_learning_path_published', TRUE);
    $group->save();

    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200, 'Authenticated user can see an main page for published training.');
    // Authenticated user need to subscribe first.
    $this->assertSession()->linkExists('Subscribe to training');
    $this->drupalLogout();

    // Check access for anonymous user.
    // $anonymous_user = $this->createUser()->getAnonymousUser();
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Start');

    // Other links should not be visible for anonymous.
    $this->assertSession()->linkNotExists('Forum');
    $this->assertSession()->linkNotExists('Training Content');
    $this->assertSession()->linkNotExists('Documents Library');

    // Test access if training is semi-private.
    $group->set('field_learning_path_visibility', 'semiprivate');
    $group->save();

    // Check access for authenticated user.
    $this->drupalLogin($authenticated);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkExists('Subscribe to training');
    $this->drupalLogout();

    // Check access for anonymous user.
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('About this training');
    $this->assertSession()->linkNotExists('Subscribe to training');

    // Test access if training is hidden for anonymous user.
    $group->set('field_anonymous_visibility', 1);
    $group->save();

    $this->drupalGet($url);
    // Anonymous user should be redirected to login page.
    $this->assertSession()->pageTextContains('You are not authorized to access this page');
    // Test access if training is semi-private.
    $group->set('field_learning_path_visibility', 'private');
    $group->save();

    // Check access for authenticated user.
    $this->drupalLogin($authenticated);
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(403, 'Authenticated user can not see a private training.');
    $this->drupalLogout();
    // Check access for anonymous user.
    $this->drupalGet($url);
    // Anonymous user should be redirected to login page.
    $this->assertSession()->pageTextContains('You are not authorized to access this page');
  }

  /**
   * Tests which users can subscribe and start a training.
   */
  public function testTrainingStart() {
    // Test access if training is public.
    $group = $this->createGroup([
      'field_learning_path_visibility' => 'public',
      'field_learning_path_published' => 1,
    ]);
    // Log out privileged user.
    $this->drupalLogout();

    $subscribe_path = Url::fromRoute('entity.group.join', ['group' => $group->id()]);
    $start_path = Url::fromRoute('opigno_learning_path.steps.type_start', ['group' => $group->id()]);

    // Create authenticated user to check a training access.
    $user_one = $this->drupalCreateUser();
    $this->drupalLogin($user_one);

    // Authenticated user need to subscribe to a training first.
    $this->drupalGet($subscribe_path);
    $this->assertSession()->statusCodeEquals(200);
    $join_button = $this->assertSession()->buttonExists('Join training');
    $join_button->click();
    // Successful subscribed.
    $this->assertSession()->statusCodeEquals(200, 'Authenticated user can subscribe to a public training.');

    // Authenticated user can start training.
    $this->drupalGet($start_path);
    $this->assertSession()->statusCodeEquals(200, 'Authenticated user can start a public training.');
    $this->assertSession()->pageTextNotContains('You are not authorized to access this page');
    $this->drupalLogout();

    // Anonymous user can't join to a public training and can immediately start.
    $this->drupalGet($subscribe_path);
    $this->assertSession()->statusCodeEquals(403, 'Anonymous user can not subscribe to a public training. Redirected to login page.');
    $this->assertSession()->pageTextContains('You are not authorized to access this page');
    $this->drupalGet($start_path);
    $this->assertSession()->statusCodeEquals(200, 'Anonymous user can start a public training.');

    // Test access if a training is semi-private.
    $group->set('field_learning_path_visibility', 'semiprivate');
    $group->save();

    // Create authenticated user to check a training access.
    $user_two = $this->drupalCreateUser();
    $this->drupalLogin($user_two);

    // Authenticated user need to subscribe to a training first.
    $this->drupalGet($subscribe_path);
    $this->assertSession()->statusCodeEquals(200);
    $join_button = $this->assertSession()->buttonExists('Join training');
    $join_button->click();
    // Successful subscribed.
    $this->assertSession()->statusCodeEquals(200, 'Authenticated user can subscribe to a semi-private training.');

    // Authenticated user can start a training.
    $this->drupalGet($start_path);
    $this->assertSession()->pageTextContains('No first step assigned');
    $this->assertSession()->statusCodeEquals(200, 'Authenticated user can start a semi-private training.');
    $this->drupalLogout();

    // Anonymous user can't subscribe to a semi-private training.
    $this->drupalGet($subscribe_path);
    $this->assertSession()->statusCodeEquals(403, 'Anonymous user can not subscribe to a semi-private training.');
    // Anonymous user can't start a semi-private training.
    $this->resetAll();
    $this->drupalGet($start_path);
    $this->assertSession()->statusCodeEquals(403, 'Anonymous user can not start a semi-private training.');

    // Test access for a training
    // where user need to be accepted by training admin.
    $this->drupalLogin($user_two);
    $group->removeMember($user_two);
    $group->set('field_requires_validation', 1);
    $group->save();
    $this->drupalGet($subscribe_path);
    $this->assertSession()->statusCodeEquals(200);
    $join_button = $this->assertSession()->buttonExists('Join training');
    $join_button->click();
    $this->drupalGet($start_path);
    $this->assertSession()->statusCodeEquals(403, 'Authenticated user can not start a semi-private training if user validation is required.');
    $this->drupalLogout();

    // Test access if a training is private.
    $group->set('field_learning_path_visibility', 'private');
    LearningPathAccess::setVisibilityFields($group);
    $group->save();

    // Create authenticated user to check a training access.
    $user_three = $this->drupalCreateUser();
    $this->drupalLogin($user_three);

    // Authenticated user cant to subscribe to a training.
    $this->drupalGet($subscribe_path);
    $this->assertSession()->statusCodeEquals(403, 'Authenticated user can not subscribe to a private training.');
    // Authenticated user can not start a training.
    $this->drupalGet($start_path);
    $this->assertSession()->statusCodeEquals(403, 'Authenticated user can not start a private training.');
    $this->drupalLogout();

    // Anonymous user can't subscribe to a private training.
    $this->drupalGet($subscribe_path);
    $this->assertSession()->statusCodeEquals(403, 'Anonymous user can not subscribe to a private training.');
    // Anonymous user can't start a private training.
    $this->drupalGet($start_path);
    $this->assertSession()->statusCodeEquals(403, 'Anonymous user can not start a private training.');

  }

}
