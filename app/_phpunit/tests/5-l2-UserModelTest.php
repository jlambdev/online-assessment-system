3
<?php

/**
 *  USERMODELTEST.PHP
 *  @author Jonathan Lamb
 */
class UserModelTest extends PHPUnit_Framework_TestCase {

  // store DB connection, SG access and instantiated class as instance variables
  private $_DB,
    $_SG,
    $_UserModel;

  /**
   *  Constructor
   *  Initialise instance variables
   */
  public function __construct() {

    $this->_UserModel = new UserModel();
    $this->_DB = DB::getInstance();
    $this->_SG = new SG();
  }

  /**
   *  @test
   */
  public function _confirmStart() {
    print_r(" - start of UserModel Test -  \n");
  }

  /**
   *  @test
   *  Confirm salt method returns unique character strings
   */
  public function makeSalt_createTwoUniqueSalts_valuesDifferent() {

    $saltOne = $this->_UserModel->makeSalt();
    $saltTwo = $this->_UserModel->makeSalt();
    $this->assertTrue($saltOne !== $saltTwo);
  }

  /**
   *  @test
   *  Confirm salt method returns 32 hexadecimal character string
   */
  public function makeSalt_createSaltMatchRegExp_value32HexadecimalCharacters() {

    $salt = $this->_UserModel->makeSalt();
    $this->assertRegExp('/^([a-z0-9]){32}$/', $salt);
  }

  /**
   *  @test
   *  Confirm hash method returns different string with different salt
   */
  public function makeHash_createHashesDifferentSalts_valuesDifferent() {

    $saltOne = $this->_UserModel->makeSalt();
    $saltTwo = $this->_UserModel->makeSalt();
    $originalPassword = "password";
    $hashOne = $this->_UserModel->makeHash(
      $originalPassword, $saltOne
    );
    $hashTwo = $this->_UserModel->makeHash(
      $originalPassword, $saltTwo
    );
    $this->assertTrue($hashOne !== $hashTwo);
  }

  /**
   *  @test
   *  Confirm hash method returns the same string with matching salt
   */
  public function makeHash_createHashesSameSalt_valuesMatch() {

    $salt = $this->_UserModel->makeSalt();
    $originalPassword = "password";
    $hashOne = $this->_UserModel->makeHash(
      $originalPassword, $salt
    );
    $hashTwo = $this->_UserModel->makeHash(
      $originalPassword, $salt
    );
    $this->assertSame($hashOne, $hashTwo);
  }

  /**
   *  @test
   *  Confirm hash method returns 64 hexadecimal character string
   */
  public function makeHash_createHashMatchRegExp_value64HexadecimalCharacters() {

    $salt = $this->_UserModel->makeSalt();
    $originalPassword = "password";
    $hash = $this->_UserModel->makeHash(
      $originalPassword, $salt
    );
    $this->assertRegExp('/^([a-z0-9]){64}$/', $hash);
  }

  /**
   *  @test
   *  Create user, confirm operation returns true
   */
  public function createUser_createSampleUser_methodReturnsTrue() {

    $username = "sample";
    $originalPassword = "password";
    $fullName = "Sample User";
    $result = $this->_UserModel->createUser($username, $originalPassword, $fullName);
    $this->assertTrue($result);
  }

  /**
   *  @test
   *  Create multiple users, operations return true
   */
  public function createUser_createMultipleUsers_methodsReturnTrue() {

    $this->assertTrue($this->_UserModel->createUser(
      "jeremy",
      "password",
      "Jeremy Bentham",
      "assessor"
    ));
    $this->assertTrue($this->_UserModel->createUser(
      "alan",
      "password",
      "Alan Partridge"
    ));
    $this->assertTrue($this->_UserModel->createUser(
      "custard",
      "password",
      "Custard Distributor"
    ));
  }

  /**
   *  @test
   *  Test username uniqueness
   */
  public function createUser_attemptCreateDuplicateUser_returnsSpecificString() {

    $username = "sample";
    $originalPassword = "password";
    $fullName = "Sample User";
    $result = $this->_UserModel->createUser($username, $originalPassword, $fullName);
    $this->assertSame(
      "Duplicate key: The user name 'sample' already exists.",
      $result
    );
  }

  /**
   *  @test
   *  Check if a user exists with UserModel's "find" method (MongoId object)
   */
  public function findUser_findSampleUserByMongoId_methodReturnsTrue() {

    $user = $this->_DB->read("users", array("user_name" => "sample"));
    $mongoIdObj = new MongoId(key($user));
    $result = $this->_UserModel->findUser($mongoIdObj);
    $this->assertTrue($result);
  }

  /**
   *  @test
   *  Check if a user exists with username as identifier
   */
  public function findUser_findSampleUserByUsername_methodReturnsTrue() {

    $result = $this->_UserModel->findUser("sample");
    $this->assertTrue($result);
  }

  /**
   *  @test
   *  Check for an inexistent user using "find" method
   */
  public function findUser_attemptToFindInexistentUser_methodReturnsFalse() {

    $junk = "12345";
    $result = $this->_UserModel->findUser($junk);
    $this->assertFalse($result);
  }

  /**
   *  @test
   *  Set Session value to sample user ID, construct Model, check if user data matches
   */
  public function constructAndGetUserData_checkDataMatch_methodReturnsMatchingData() {

    $user = $this->_DB->read("users", array("user_name" => "sample"));
    $this->_SG->session("user", "put", key($user));
    $user = array_pop($user);
    $newUserModel = new UserModel();
    $this->assertSame(
      $user["user_name"],
      $newUserModel->getUserData()->userName
    );
  }

  /**
   *  @test
   *  Set Session value to sample user ID, construct Model, check if login status === true
   */
  public function constructAndGetLoginStatus_checkLoginStatus_methodReturnsTrue() {

    $user = $this->_DB->read("users", array("user_name" => "sample"));
    $this->_SG->session("user", "put", key($user));
    $newUserModel = new UserModel();
    $this->assertTrue($newUserModel->getLoginStatus());
  }

  /**
   *  @test
   *  Construct Model, check if login status === false (no session)
   */
  public function constructAndGetLoginStatus_checkLoginStatusNoSession_methodReturnsFalse() {

    $newUserModel = new UserModel();
    $this->assertFalse($newUserModel->getLoginStatus());
  }

  /**
   *  @test
   *  Update user password hash
   */
  public function updateUser_changePassword_methodReturnsTrue() {

    // get sample user and update session superglobal
    $user = $this->_DB->read("users", array("user_name" => "sample"));
    $this->_SG->session("user", "put", key($user));
    $user = array_pop($user);

    // create new password hash
    $newPassword = "abc123def456";
    $newHash = $this->_UserModel->makeHash($newPassword, $user["salt"]);

    // create new User Model
    $newUserModel = new UserModel();
    $result = $newUserModel->updateUser("hash", $newHash);
    $this->assertTrue($result);
  }

  /**
   *  @test
   *  Attempt to update username (action not allowed)
   */
  public function updateUser_attemptUsernameChange_methodReturnsFalse() {

    $user = $this->_DB->read("users", array("user_name" => "sample"));
    $this->_SG->session("user", "put", key($user));
    $newUserModel = new UserModel();
    $result = $newUserModel->updateUser("user_name", "newUsername");
    $this->assertFalse($result);
  }

  /**
   *  @test
   *  Attempt to update user password when user is not logged in
   */
  public function updateUser_attemptPasswordChangeUserNotLoggedIn_methodReturnsFalse() {

    $result = $this->_UserModel->updateUser("hash", "12345");
    $this->assertFalse($result);
  }

  /**
   *  @test
   *  Log in user to private instance variable of UserModel
   */
  public function logUserIn_logInUserToUserModelInstanceVariable_methodReturnsTrue() {

    $result = $this->_UserModel->logUserIn("sample", "abc123def456");
    $this->assertTrue($result);
  }

  /**
   *  @test
   *  Attempt login of an inexistent user
   */
  public function logUserIn_attemptLogInWithInvalidUsername_methodReturnsFalse() {

    $newUserModel = new UserModel();
    $result = $newUserModel->logUserIn("jamSandwich", "abc123def456");
    $this->assertFalse($result);
  }

  /**
   *  @test
   *  Attempt login of an existing user with an incorrect password
   */
  public function logUserIn_attemptLogInWithInvalidPassword_methodReturnsFalse() {

    $newUserModel = new UserModel();
    $result = $newUserModel->logUserIn("sample", "openSesame");
    $this->assertFalse($result);
  }

  /**
   *  @test
   *  Check the login status of the private instance variable of UserModel
   */
  public function getLoginStatus_checkInstanceVariableOfUMIsLoggedIn_methodReturnsTrue() {

    $result = $this->_UserModel->logUserIn("sample", "abc123def456");
    $result = $this->_UserModel->getLoginStatus();
    $this->assertTrue($result);
  }

  /**
   *  @test
   *  Log user out of the private instance variable of UserModel
   */
  public function logUserOut_logOutInstanceVariableUserModel_methodReturnsTrue() {

    $result = $this->_UserModel->logUserIn("sample", "abc123def456");
    $result = $this->_UserModel->logUserOut();
    $this->assertTrue($result);
  }

  /**
   *  @test
   *  Log user in with Model construction, then log user out with object method
   */
  public function logUserOut_logOutAfterObjectConstructionUsingSession_methodReturnsTrue() {

    $user = $this->_DB->read("users", array("user_name" => "sample"));
    $this->_SG->session("user", "put", key($user));
    $newUserModel = new UserModel();
    $result = $newUserModel->logUserOut();
    $this->assertTrue($result);
  }

  /**
   *  @test
   *  Attempt log out with no user currently logged in
   */
  public function logUserOut_logOutNoUserLoggedIn_methodReturnsFalse() {

    $result = $this->_UserModel->logUserOut();
    $this->assertFalse($result);
  }

  /**
   *  @test
   *  Confirm list of users matches limited subset of values
   */
  public function getListOfStudents_validRequest_methodReturnsMatchingValues() {

    // get user id's for comparison
    $this->_UserModel->findUser("sample");
    $userIdOne = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("alan");
    $userIdTwo = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("custard");
    $userIdThree = $this->_UserModel->getUserData()->userId;

    $this->assertSame(
      "{\"{$userIdOne}\":{\"user_name\":\"sample\",\"full_name\":\"Sample User\"}," .
      "\"{$userIdTwo}\":{\"user_name\":\"alan\",\"full_name\":\"Alan Partridge\"}," .
      "\"{$userIdThree}\":{\"user_name\":\"custard\",\"full_name\":\"Custard Distributor\"}}",
      $this->_UserModel->getListOfStudents()
    );
  }

  /**
   *  @test
   *  Create a distribution group
   */
  public function createGroup_createValidGroup_methodReturnsTrueDocumentCreated() {

    $this->_UserModel->findUser("sample");
    $userIdOne = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("alan");
    $userIdTwo = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("custard");
    $userIdThree = $this->_UserModel->getUserData()->userId;

    $studentIds = array($userIdOne, $userIdTwo, $userIdThree);
    $this->assertTrue(
      $this->_UserModel->createGroup("Test Group", $studentIds)
    );
  }

  /**
   *  @test
   *  Attempt to create group with an invalid student id
   */
  public function createGroup_arrayIncludesInexistentStudent_methodReturnsFalse() {

    $this->_UserModel->findUser("sample");
    $userIdOne = $this->_UserModel->getUserData()->userId;
    $userIdTwo = "fneiosnf3092hf982nf29o";
    $this->_UserModel->findUser("custard");
    $userIdThree = $this->_UserModel->getUserData()->userId;

    $badData = array($userIdOne, $userIdTwo, $userIdThree);
    $this->assertFalse(
      $this->_UserModel->createGroup("Test Group 2", $badData)
    );
  }

  /**
   *  @test
   *  Attempt to create group with an assessor id included
   */
  public function createGroup_arrayIncludesAssessorId_methodReturnsFalse() {

    $this->_UserModel->findUser("sample");
    $userIdOne = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("jeremy");    // assessor account
    $userIdTwo = $this->_UserModel->getUserData()->userId;
    $this->_UserModel->findUser("custard");
    $userIdThree = $this->_UserModel->getUserData()->userId;

    $notAllStudentIds = array($userIdOne, $userIdTwo, $userIdThree);
    $this->assertFalse(
      $this->_UserModel->createGroup("Test Group 3", $notAllStudentIds)
    );
  }

  /**
   *  @test
   *  Drop Users collection (reset for later testing)
   */
  public function _dropUserCollection_methodReturnsTrue() {

    $dropUsersResult = $this->_DB->delete("users", "DROP COLLECTION");
    $dropGroupsResult = $this->_DB->delete("groups", "DROP COLLECTION");
    $this->assertTrue($dropUsersResult && $dropGroupsResult);
  }

  /**
   *  @test
   */
  public function _confirmEnd() {
    print_r("\n  - end of UserModel Test -  \n\n");
  }
}
